<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\PaymentGatewayService;
use App\Support\DebugStepLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Coordinates invoices, payments, and user subscription activation.
 *
 * @since 1.0.0
 */
class BillingService
{
    private int $debugStepCounter = 0;

    public function __construct(
        private readonly PaymentGatewayService $gateways,
        private readonly SubscriptionActivationService $activation,
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * Create or return the latest unpaid invoice for a user's plan purchase.
     */
    public function invoiceForPlan(User $user, Plan $plan, ?Tenant $tenant = null): Invoice
    {
        $this->resetDebugSteps();

        $scope = 'billing.invoice_for_plan';

        $this->debugStep($scope, 'Invoice for plan workflow started.', [
            'user_id' => $user->id,
            'plan' => $this->planContext($plan),
            'requested_tenant_id' => $tenant?->id,
        ]);

        try {
            $tenant = $this->billingTenantFor($user, $tenant, $scope);

            $this->debugStep($scope, 'Billing tenant resolved before purchase validation.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'plan_id' => $plan->id,
            ]);

            $this->activation->validatePurchase($user, $plan, $tenant);

            $this->debugStep($scope, 'Plan purchase validation passed.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'plan_id' => $plan->id,
            ]);

            $invoice = Invoice::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant?->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                ],
                [
                    'number' => $this->nextInvoiceNumber(),
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'due_at' => now()->addDays(7),
                    'expires_at' => now()->addDays(7),
                    'metadata_json' => [
                        'plan_type' => $plan->plan_type->value,
                        'service_key' => $plan->service_key,
                    ],
                ]
            );

            $this->debugStep($scope, 'Invoice for plan resolved.', [
                'invoice' => $this->invoiceContext($invoice),
                'was_recently_created' => $invoice->wasRecentlyCreated,
            ]);

            return $invoice;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Invoice for plan workflow failed.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'requested_tenant_id' => $tenant?->id,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Start payment for an unpaid invoice.
     */
    public function initiatePayment(Invoice $invoice): Payment
    {
        $this->resetDebugSteps();

        $scope = 'billing.initiate_payment';

        $this->debugStep($scope, 'Payment initiation workflow started.', [
            'invoice' => $this->invoiceContext($invoice),
        ]);

        try {
            if (! in_array($invoice->status, ['pending', 'failed'], true)) {
                $this->debugStep($scope, 'Payment initiation rejected because invoice is not payable.', [
                    'invoice_id' => $invoice->id,
                    'invoice_status' => $invoice->status,
                ]);

                throw ValidationException::withMessages([
                    'invoice' => __('payments.invoice_not_payable'),
                ]);
            }

            $gateway = $this->gateways->default();

            if (! $gateway) {
                $this->debugStep($scope, 'Payment initiation failed because no payment gateway is available.', [
                    'invoice_id' => $invoice->id,
                    'invoice_status' => $invoice->status,
                ]);

                throw ValidationException::withMessages([
                    'payment_gateway' => __('payments.no_gateway'),
                ]);
            }

            $this->debugStep($scope, 'Default payment gateway resolved.', [
                'invoice_id' => $invoice->id,
                'gateway' => $this->paymentGatewayContext($gateway),
            ]);

            $pendingPayment = $invoice->payments()
                ->where('payment_gateway_id', $gateway->id)
                ->where('status', 'pending')
                ->latest('id')
                ->first();

            if (
                $pendingPayment
                && filled($pendingPayment->authority)
                && filled(data_get($pendingPayment->gateway_response_json, 'redirect_url'))
            ) {
                $this->debugStep($scope, 'Reusable pending payment found.', [
                    'payment' => $this->paymentContext($pendingPayment),
                    'has_authority' => true,
                    'has_redirect_url' => true,
                ]);

                return $pendingPayment;
            }

            $this->debugStep($scope, 'Creating new pending payment record.', [
                'invoice_id' => $invoice->id,
                'tenant_id' => $invoice->tenant_id,
                'user_id' => $invoice->user_id,
                'payment_gateway_id' => $gateway->id,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
            ]);

            $payment = Payment::create([
                'tenant_id' => $invoice->tenant_id,
                'user_id' => $invoice->user_id,
                'invoice_id' => $invoice->id,
                'payment_gateway_id' => $gateway->id,
                'status' => 'pending',
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            $this->debugStep($scope, 'Pending payment record created.', [
                'payment' => $this->paymentContext($payment),
            ]);

            $payment = $this->gateways->initiate($payment);

            $this->debugStep($scope, 'Payment gateway initiation completed.', [
                'payment' => $this->paymentContext($payment),
                'gateway_response_keys' => is_array($payment->gateway_response_json)
                    ? array_keys($payment->gateway_response_json)
                    : [],
                'has_redirect_url' => filled(data_get($payment->gateway_response_json, 'redirect_url')),
            ]);

            return $payment;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Payment initiation workflow failed.', [
                'invoice_id' => $invoice->id,
                'invoice_status' => $invoice->status,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Verify a payment and activate its plan exactly once.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayment(Payment $payment, array $payload = []): Payment
    {
        $this->resetDebugSteps();

        $scope = 'billing.verify_payment';

        $this->debugStep($scope, 'Payment verification workflow started.', [
            'payment_id' => $payment->id,
            'payload' => $this->payloadContext($payload),
        ]);

        try {
            return DB::transaction(function () use ($payment, $payload, $scope): Payment {
                $payment = Payment::query()
                    ->with(['invoice.plan.serviceEntitlements', 'invoice.tenant', 'invoice.user'])
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                $this->debugStep($scope, 'Payment locked for verification.', [
                    'payment' => $this->paymentContext($payment),
                    'invoice_id' => $payment->invoice_id,
                    'has_invoice' => $payment->invoice !== null,
                    'has_plan' => $payment->invoice?->plan !== null,
                ]);

                if ($payment->status === 'paid' && $payment->verified_at) {
                    $this->debugStep($scope, 'Payment already verified as paid.', [
                        'payment' => $this->paymentContext($payment),
                        'invoice_id' => $payment->invoice_id,
                        'invoice_activated' => $payment->invoice?->activated_at !== null,
                    ]);

                    if ($payment->invoice?->plan && ! $payment->invoice->activated_at) {
                        $this->debugStep($scope, 'Invoice has not been activated yet; activating paid invoice.', [
                            'payment_id' => $payment->id,
                            'invoice_id' => $payment->invoice->id,
                            'plan_id' => $payment->invoice->plan_id,
                        ]);

                        $this->activation->activateInvoice($payment->invoice);
                    }

                    return $payment;
                }

                $this->debugStep($scope, 'Calling payment gateway verification.', [
                    'payment' => $this->paymentContext($payment),
                    'payload' => $this->payloadContext($payload),
                ]);

                $result = $this->gateways->verify($payment, $payload);

                $this->debugStep($scope, 'Payment gateway verification returned result.', [
                    'payment_id' => $payment->id,
                    'result_keys' => array_keys($result),
                    'has_reference_id' => filled($result['reference_id'] ?? null),
                    'response_keys' => is_array($result['response'] ?? null) ? array_keys($result['response']) : [],
                ]);

                $payment->forceFill([
                    'status' => 'paid',
                    'reference_id' => $result['reference_id'],
                    'gateway_response_json' => $result['response'],
                    'verified_at' => now(),
                ])->save();

                $this->debugStep($scope, 'Payment marked as paid.', [
                    'payment' => $this->paymentContext($payment),
                    'has_reference_id' => filled($payment->reference_id),
                    'gateway_response_keys' => is_array($payment->gateway_response_json)
                        ? array_keys($payment->gateway_response_json)
                        : [],
                ]);

                $invoice = $payment->invoice;

                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                ])->save();

                $this->debugStep($scope, 'Invoice marked as paid.', [
                    'invoice' => $this->invoiceContext($invoice),
                ]);

                if ($invoice->plan) {
                    $this->debugStep($scope, 'Activating invoice plan after successful payment verification.', [
                        'invoice_id' => $invoice->id,
                        'plan_id' => $invoice->plan_id,
                        'tenant_id' => $invoice->tenant_id,
                        'user_id' => $invoice->user_id,
                    ]);

                    $this->activation->activateInvoice($invoice);
                }

                $payment->refresh();

                $this->debugStep($scope, 'Payment verification workflow completed successfully.', [
                    'payment' => $this->paymentContext($payment),
                ]);

                return $payment;
            });
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Payment verification workflow failed.', [
                'payment_id' => $payment->id,
                'payload' => $this->payloadContext($payload),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Mark a gateway attempt as failed without exposing gateway payloads to users.
     *
     * @param  array<string, mixed>  $payload
     */
    public function markPaymentFailed(Payment $payment, array $payload = []): Payment
    {
        $this->resetDebugSteps();

        $scope = 'billing.mark_payment_failed';

        $this->debugStep($scope, 'Mark payment failed workflow started.', [
            'payment_id' => $payment->id,
            'payload' => $this->payloadContext($payload),
        ]);

        try {
            return DB::transaction(function () use ($payment, $payload, $scope): Payment {
                $payment = Payment::query()
                    ->with('invoice')
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                $this->debugStep($scope, 'Payment locked before marking as failed.', [
                    'payment' => $this->paymentContext($payment),
                    'has_invoice' => $payment->invoice !== null,
                ]);

                if ($payment->status === 'paid') {
                    $this->debugStep($scope, 'Mark payment failed skipped because payment is already paid.', [
                        'payment' => $this->paymentContext($payment),
                    ]);

                    return $payment;
                }

                $payment->forceFill([
                    'status' => 'failed',
                    'gateway_response_json' => array_filter([
                        'provider' => data_get($payment->gateway_response_json, 'provider'),
                        'status' => 'failed',
                        'authority_hash' => filled($payment->authority) ? hash('sha256', (string) $payment->authority) : null,
                        'callback_status' => $payload['Status'] ?? $payload['status'] ?? null,
                    ]),
                ])->save();

                $this->debugStep($scope, 'Payment marked as failed.', [
                    'payment' => $this->paymentContext($payment),
                    'gateway_response_keys' => is_array($payment->gateway_response_json)
                        ? array_keys($payment->gateway_response_json)
                        : [],
                    'has_authority_hash' => filled(data_get($payment->gateway_response_json, 'authority_hash')),
                    'has_callback_status' => filled(data_get($payment->gateway_response_json, 'callback_status')),
                ]);

                if ($payment->invoice && $payment->invoice->status !== 'paid') {
                    $payment->invoice->forceFill(['status' => 'failed'])->save();

                    $this->debugStep($scope, 'Invoice marked as failed after payment failure.', [
                        'invoice' => $this->invoiceContext($payment->invoice),
                    ]);
                }

                $payment->refresh();

                $this->debugStep($scope, 'Mark payment failed workflow completed.', [
                    'payment' => $this->paymentContext($payment),
                ]);

                return $payment;
            });
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Mark payment failed workflow failed.', [
                'payment_id' => $payment->id,
                'payload' => $this->payloadContext($payload),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Replace any active user plan and activate the newly purchased one.
     */
    public function activatePlan(User $user, Plan $plan, Invoice $invoice, ?Tenant $tenant = null): Subscription
    {
        $this->resetDebugSteps();

        $scope = 'billing.activate_plan';

        $this->debugStep($scope, 'Plan activation workflow started.', [
            'user_id' => $user->id,
            'plan' => $this->planContext($plan),
            'invoice' => $this->invoiceContext($invoice),
            'requested_tenant_id' => $tenant?->id,
        ]);

        try {
            $tenant = $this->billingTenantFor($user, $tenant, $scope);

            $this->debugStep($scope, 'Billing tenant resolved before invoice activation.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
            ]);

            $invoice->forceFill([
                'tenant_id' => $tenant?->id ?? $invoice->tenant_id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $invoice->status === 'pending' ? 'paid' : ($invoice->status ?: 'paid'),
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();

            $this->debugStep($scope, 'Invoice prepared for activation.', [
                'invoice' => $this->invoiceContext($invoice),
            ]);

            $subscription = $this->activation->activateInvoice($invoice);

            if ($subscription instanceof Subscription) {
                $this->debugStep($scope, 'Invoice activation returned subscription.', [
                    'subscription' => $this->subscriptionContext($subscription),
                    'invoice_id' => $invoice->id,
                ]);

                return $subscription;
            }

            $this->debugStep($scope, 'Invoice activation did not return subscription; checking active subscription fallback.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'invoice_id' => $invoice->id,
                'plan_id' => $plan->id,
            ]);

            $subscription = $this->findActiveSubscription($user, $tenant, $scope);

            if ($subscription instanceof Subscription) {
                $this->debugStep($scope, 'Active subscription fallback resolved.', [
                    'subscription' => $this->subscriptionContext($subscription),
                ]);

                return $subscription;
            }

            $this->debugStep($scope, 'Plan activation failed because no active subscription was resolved.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'invoice_id' => $invoice->id,
                'plan_id' => $plan->id,
            ]);

            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.activation_failed'),
            ]);
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Plan activation workflow failed.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
                'requested_tenant_id' => $tenant?->id,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Mark subscriptions expired by time or usage.
     */
    public function expireEndedSubscriptions(): int
    {
        $this->resetDebugSteps();

        $scope = 'billing.expire_ended_subscriptions';

        $this->debugStep($scope, 'Expire ended subscriptions workflow started.');

        try {
            $expiredCount = $this->entitlements->expireEndedSubscriptions();

            $this->debugStep($scope, 'Expire ended subscriptions workflow completed.', [
                'expired_count' => $expiredCount,
            ]);

            return $expiredCount;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Expire ended subscriptions workflow failed.', [
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Consume legacy aggregate usage without expiring the base subscription.
     */
    public function consumeUsage(User $user, int $amount = 1): ?Subscription
    {
        $this->resetDebugSteps();

        $scope = 'billing.consume_usage';

        $this->debugStep($scope, 'Legacy aggregate usage consumption workflow started.', [
            'user_id' => $user->id,
            'amount' => $amount,
        ]);

        try {
            return DB::transaction(function () use ($user, $amount, $scope): ?Subscription {
                $subscription = $user->subscriptions()
                    ->where(function (Builder $query): void {
                        $query->whereNull('tenant_id')
                            ->orWhereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'));
                    })
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->latest('starts_at')
                    ->lockForUpdate()
                    ->first();

                if (! $subscription) {
                    $this->debugStep($scope, 'Legacy aggregate usage consumption skipped because no active subscription exists.', [
                        'user_id' => $user->id,
                        'amount' => $amount,
                    ]);

                    return null;
                }

                $this->debugStep($scope, 'Active subscription locked for legacy usage consumption.', [
                    'subscription' => $this->subscriptionContext($subscription),
                    'amount' => $amount,
                    'usage_used_before' => (int) $subscription->usage_used,
                ]);

                $subscription->forceFill([
                    'usage_used' => (int) $subscription->usage_used + $amount,
                ])->save();

                $subscription->refresh();

                $this->debugStep($scope, 'Legacy aggregate usage consumed successfully.', [
                    'subscription' => $this->subscriptionContext($subscription),
                    'amount' => $amount,
                    'usage_used_after' => (int) $subscription->usage_used,
                ]);

                return $subscription;
            });
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Legacy aggregate usage consumption workflow failed.', [
                'user_id' => $user->id,
                'amount' => $amount,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * Return the active subscription for a user, expiring stale rows first.
     */
    public function activeSubscription(User $user, ?Tenant $tenant = null): ?Subscription
    {
        $this->resetDebugSteps();

        $scope = 'billing.active_subscription';

        $this->debugStep($scope, 'Active subscription lookup workflow started.', [
            'user_id' => $user->id,
            'requested_tenant_id' => $tenant?->id,
        ]);

        try {
            $tenant = $this->billingTenantFor($user, $tenant, $scope);

            $this->debugStep($scope, 'Billing tenant resolved before active subscription lookup.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
            ]);

            $expiredCount = $this->entitlements->expireEndedSubscriptions();

            $this->debugStep($scope, 'Ended subscriptions expired before active subscription lookup.', [
                'expired_count' => $expiredCount,
            ]);

            $subscription = $this->findActiveSubscription($user, $tenant, $scope);

            $this->debugStep($scope, 'Active subscription lookup workflow completed.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'resolved' => $subscription instanceof Subscription,
                'subscription' => $subscription instanceof Subscription ? $this->subscriptionContext($subscription) : null,
            ]);

            return $subscription;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Active subscription lookup workflow failed.', [
                'user_id' => $user->id,
                'requested_tenant_id' => $tenant?->id,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function findActiveSubscription(User $user, ?Tenant $tenant, string $scope): ?Subscription
    {
        $this->debugStep($scope, 'Building active subscription query.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
        ]);

        $query = $user->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->whereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'));

        if ($tenant) {
            $query->where(function (Builder $query) use ($tenant): void {
                $query->where('tenant_id', $tenant->id)
                    ->orWhereNull('tenant_id');
            });
        }

        $subscription = $query->latest('starts_at')->first();

        $this->debugStep($scope, 'Active subscription query executed.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'resolved' => $subscription instanceof Subscription,
            'subscription_id' => $subscription?->id,
        ]);

        return $subscription;
    }

    private function nextInvoiceNumber(): string
    {
        return 'HV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function billingTenantFor(User $user, ?Tenant $tenant = null, string $scope = 'billing.tenant_resolution'): ?Tenant
    {
        if ($tenant) {
            $this->debugStep($scope, 'Billing tenant resolved from explicit tenant argument.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            return $tenant;
        }

        if ($user->isSuperAdmin()) {
            $this->debugStep($scope, 'Billing tenant resolved as null for super admin user.', [
                'user_id' => $user->id,
            ]);

            return null;
        }

        $tenant = $user->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.id')
            ->first();

        $this->debugStep($scope, 'Billing tenant resolved from active user tenants.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'resolved' => $tenant instanceof Tenant,
        ]);

        return $tenant;
    }

    private function resetDebugSteps(): void
    {
        $this->debugStepCounter = 0;
    }

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }

    private function planContext(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'plan_type' => $this->planTypeValue($plan),
            'service_key' => $plan->service_key,
            'currency' => $plan->currency,
            'status' => $plan->status,
        ];
    }

    private function invoiceContext(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'user_id' => $invoice->user_id,
            'plan_id' => $invoice->plan_id,
            'status' => $invoice->status,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'is_paid' => $invoice->paid_at !== null,
            'is_activated' => $invoice->activated_at !== null,
            'metadata_keys' => is_array($invoice->metadata_json) ? array_keys($invoice->metadata_json) : [],
        ];
    }

    private function paymentContext(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'tenant_id' => $payment->tenant_id,
            'user_id' => $payment->user_id,
            'invoice_id' => $payment->invoice_id,
            'payment_gateway_id' => $payment->payment_gateway_id,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'has_authority' => filled($payment->authority),
            'authority_hash' => filled($payment->authority) ? hash('sha256', (string) $payment->authority) : null,
            'has_reference_id' => filled($payment->reference_id),
            'is_verified' => $payment->verified_at !== null,
        ];
    }

    private function subscriptionContext(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'user_id' => $subscription->user_id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'usage_used' => $subscription->usage_used,
            'starts_at_set' => $subscription->starts_at !== null,
            'ends_at_set' => $subscription->ends_at !== null,
        ];
    }

    private function paymentGatewayContext(mixed $gateway): array
    {
        return [
            'id' => data_get($gateway, 'id'),
            'provider_code' => data_get($gateway, 'provider_code'),
            'status' => data_get($gateway, 'status'),
            'class' => is_object($gateway) ? $gateway::class : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadContext(array $payload): array
    {
        return [
            'payload_keys' => array_keys($payload),
            'payload_count' => count($payload),
            'has_status' => array_key_exists('Status', $payload) || array_key_exists('status', $payload),
        ];
    }

    private function planTypeValue(Plan $plan): mixed
    {
        $planType = $plan->plan_type;

        if (is_object($planType) && property_exists($planType, 'value')) {
            return $planType->value;
        }

        return $planType;
    }
}