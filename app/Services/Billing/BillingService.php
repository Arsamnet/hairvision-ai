<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Settings\SystemSettingService;
use App\Support\JalaliDate;
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
    public function __construct(
        private readonly PaymentGatewayService $gateways,
        private readonly SubscriptionActivationService $activation,
        private readonly ServiceEntitlementService $entitlements,
        private readonly SystemSettingService $settings,
    ) {}

    /**
     * Create or return the latest unpaid invoice for a user's plan purchase.
     */
    public function invoiceForPlan(User $user, Plan $plan, ?Tenant $tenant = null): Invoice
    {
        $this->resetDebugSteps();

        $scope = 'billing.invoice_for_plan';

        try {
            $tenant = $this->billingTenantFor($user, $tenant, $scope);

            $this->activation->validatePurchase($user, $plan, $tenant);

            $invoice = Invoice::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant?->id,
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                ],
                [
                    'number' => $this->nextInvoiceNumber($plan),
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                    'due_at' => now()->addDays($this->invoiceDueDays()),
                    'expires_at' => now()->addDays($this->invoiceDueDays()),
                    'metadata_json' => [
                        'plan_type' => $plan->plan_type->value,
                        'service_key' => $plan->service_key,
                    ],
                ]
            );

            return $invoice;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Create or return a pending clinic-owned invoice for a plan without tying it to a user.
     */
    public function invoiceForClinicPlan(Plan $plan, Tenant $tenant, ?User $actor = null, int $discountAmount = 0): Invoice
    {
        $this->resetDebugSteps();

        $scope = 'billing.invoice_for_clinic_plan';

        try {
            if ($plan->status !== 'active') {
                throw ValidationException::withMessages([
                    'plan' => __('plans.inactive_plan'),
                ]);
            }
            $discountAmount = max(0, min($discountAmount, (int) $plan->price));
            $invoiceAmount = max(0, (int) $plan->price - $discountAmount);

            $invoice = Invoice::query()->firstOrNew(
                [
                    'tenant_id' => $tenant->id,
                    'user_id' => null,
                    'plan_id' => $plan->id,
                    'status' => 'pending',
                ],
                [
                    'number' => $this->nextInvoiceNumber($plan),
                    'amount' => $invoiceAmount,
                    'currency' => $plan->currency,
                    'due_at' => now()->addDays($this->invoiceDueDays()),
                    'expires_at' => now()->addDays($this->invoiceDueDays()),
                    'metadata_json' => [
                        'plan_type' => $plan->plan_type->value,
                        'service_key' => $plan->service_key,
                        'created_by' => $actor?->id,
                        'source' => 'clinic_manual_assignment',
                        'original_amount' => (int) $plan->price,
                        'discount_amount' => $discountAmount,
                    ],
                ]
            );
            $invoice->fill([
                'amount' => $invoiceAmount,
                'currency' => $plan->currency,
                'metadata_json' => array_replace($invoice->metadata_json ?? [], [
                    'plan_type' => $plan->plan_type->value,
                    'service_key' => $plan->service_key,
                    'created_by' => $actor?->id,
                    'source' => 'clinic_manual_assignment',
                    'original_amount' => (int) $plan->price,
                    'discount_amount' => $discountAmount,
                ]),
            ]);
            $invoice->save();

            return $invoice;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Mark a clinic-owned plan invoice paid and activate its linked plan.
     */
    public function activateClinicPlanInvoice(Invoice $invoice, ?User $actor = null): ?Subscription
    {
        $this->resetDebugSteps();

        $scope = 'billing.activate_clinic_plan_invoice';

        try {
            $invoice->forceFill([
                'status' => 'paid',
                'paid_at' => $invoice->paid_at ?? now(),
                'metadata_json' => array_replace($invoice->metadata_json ?? [], [
                    'approved_by' => $actor?->id,
                ]),
            ])->save();

            return $this->activation->activateInvoice($invoice);
        } catch (Throwable $exception) {
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

        try {
            if (! in_array($invoice->status, ['pending', 'failed'], true)) {
                throw ValidationException::withMessages([
                    'invoice' => __('payments.invoice_not_payable'),
                ]);
            }

            $gateway = $this->gateways->default();

            if (! $gateway) {
                throw ValidationException::withMessages([
                    'payment_gateway' => __('payments.no_gateway'),
                ]);
            }

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
                return $pendingPayment;
            }

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

            $payment = $this->gateways->initiate($payment);

            return $payment;
        } catch (Throwable $exception) {
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

        try {
            return DB::transaction(function () use ($payment, $payload): Payment {
                $payment = Payment::query()
                    ->with(['invoice.plan.serviceEntitlements', 'invoice.tenant', 'invoice.user'])
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ($payment->status === 'paid' && $payment->verified_at) {
                    if ($payment->invoice?->plan && ! $payment->invoice->activated_at) {
                        $this->activation->activateInvoice($payment->invoice);
                    }

                    return $payment;
                }

                $result = $this->gateways->verify($payment, $payload);

                $payment->forceFill([
                    'status' => 'paid',
                    'reference_id' => $result['reference_id'],
                    'gateway_response_json' => $result['response'],
                    'verified_at' => now(),
                ])->save();

                $invoice = $payment->invoice;

                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => now(),
                ])->save();

                if ($invoice->plan) {
                    $this->activation->activateInvoice($invoice);
                }

                $payment->refresh();

                return $payment;
            });
        } catch (Throwable $exception) {
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

        try {
            return DB::transaction(function () use ($payment, $payload): Payment {
                $payment = Payment::query()
                    ->with('invoice')
                    ->lockForUpdate()
                    ->findOrFail($payment->id);

                if ($payment->status === 'paid') {
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

                if ($payment->invoice && $payment->invoice->status !== 'paid') {
                    $payment->invoice->forceFill(['status' => 'failed'])->save();

                }

                $payment->refresh();

                return $payment;
            });
        } catch (Throwable $exception) {
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

        try {
            $tenant = $this->billingTenantFor($user, $tenant, $scope);

            $invoice->forceFill([
                'tenant_id' => $tenant?->id ?? $invoice->tenant_id,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => $invoice->status === 'pending' ? 'paid' : ($invoice->status ?: 'paid'),
                'paid_at' => $invoice->paid_at ?? now(),
            ])->save();

            $subscription = $this->activation->activateInvoice($invoice);

            if ($subscription instanceof Subscription) {
                return $subscription;
            }

            $subscription = $this->findActiveSubscription($user, $tenant, $scope);

            if ($subscription instanceof Subscription) {
                return $subscription;
            }

            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.activation_failed'),
            ]);
        } catch (Throwable $exception) {
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

        try {
            $expiredCount = $this->entitlements->expireEndedSubscriptions();

            return $expiredCount;
        } catch (Throwable $exception) {
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

        try {
            return DB::transaction(function () use ($user, $amount): ?Subscription {
                $subscription = $user->subscriptions()
                    ->where(function (Builder $query): void {
                        $query->whereNull('tenant_id')
                            ->orWhereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'));
                    })
                    ->where('status', 'active')
                    ->where('ends_at', '>', JalaliDate::nowDateTimeString())
                    ->latest('starts_at')
                    ->lockForUpdate()
                    ->first();

                if (! $subscription) {
                    return null;
                }

                $subscription->forceFill([
                    'usage_used' => (int) $subscription->usage_used + $amount,
                ])->save();

                $subscription->refresh();

                return $subscription;
            });
        } catch (Throwable $exception) {
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

        try {
            $tenant = $this->billingTenantFor($user, $tenant, $scope);

            $expiredCount = $this->entitlements->expireEndedSubscriptions();

            $subscription = $this->findActiveSubscription($user, $tenant, $scope);

            return $subscription;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    private function findActiveSubscription(User $user, ?Tenant $tenant, string $scope): ?Subscription
    {
        $query = $user->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('ends_at', '>', JalaliDate::nowDateTimeString())
            ->whereHas('plan', fn (Builder $query) => $query
                ->where('plan_type', 'base_subscription')
                ->where('status', 'active'));

        if ($tenant) {
            $query->where(function (Builder $query) use ($tenant): void {
                $query->where('tenant_id', $tenant->id)
                    ->orWhereNull('tenant_id');
            });
        }

        $subscription = $query->latest('starts_at')->first();

        return $subscription;
    }

    private function nextInvoiceNumber(?Plan $plan = null): string
    {
        $prefix = match ($plan?->plan_type?->value) {
            'base_subscription' => 'BS',
            'service_add_on' => 'SA',
            'credit_top_up' => 'CT',
            default => 'IN',
        };

        $next = ((int) Invoice::query()->max('id')) + 1;

        do {
            $number = $prefix.'-'.$next;
            $next++;
        } while (Invoice::query()->where('number', $number)->exists());

        return $number;
    }

    private function billingTenantFor(User $user, ?Tenant $tenant = null, string $scope = 'billing.tenant_resolution'): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        $tenant = $user->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.id')
            ->first();

        return $tenant;
    }

    private function resetDebugSteps(): void {}

    private function invoiceDueDays(): int
    {
        return max(1, min(90, (int) $this->settings->get('billing.invoice_due_days', 7)));
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
