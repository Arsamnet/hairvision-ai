<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
    ) {}

    /**
     * Create or return the latest unpaid invoice for a user's plan purchase.
     */
    public function invoiceForPlan(User $user, Plan $plan, ?Tenant $tenant = null): Invoice
    {
        $tenant = $this->billingTenantFor($user, $tenant);
        $this->activation->validatePurchase($user, $plan, $tenant);

        return Invoice::query()->firstOrCreate(
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
    }

    /**
     * Start payment for an unpaid invoice.
     */
    public function initiatePayment(Invoice $invoice): Payment
    {
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

        return $this->gateways->initiate($payment);
    }

    /**
     * Verify a payment and activate its plan exactly once.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyPayment(Payment $payment, array $payload = []): Payment
    {
        return DB::transaction(function () use ($payment, $payload): Payment {
            $payment = Payment::query()->with(['invoice.plan.serviceEntitlements', 'invoice.tenant', 'invoice.user'])->lockForUpdate()->findOrFail($payment->id);

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

            return $payment->refresh();
        });
    }

    /**
     * Replace any active user plan and activate the newly purchased one.
     */
    public function activatePlan(User $user, Plan $plan, Invoice $invoice, ?Tenant $tenant = null): Subscription
    {
        $tenant = $this->billingTenantFor($user, $tenant);
        $invoice->forceFill([
            'tenant_id' => $tenant?->id ?? $invoice->tenant_id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => $invoice->status === 'pending' ? 'paid' : ($invoice->status ?: 'paid'),
            'paid_at' => $invoice->paid_at ?? now(),
        ])->save();

        return $this->activation->activateInvoice($invoice)
            ?? $this->activeSubscription($user, $tenant)
            ?? throw ValidationException::withMessages(['plan' => __('subscriptions.errors.activation_failed')]);
    }

    /**
     * Mark subscriptions expired by time or usage.
     */
    public function expireEndedSubscriptions(): int
    {
        return $this->entitlements->expireEndedSubscriptions();
    }

    /**
     * Consume legacy aggregate usage without expiring the base subscription.
     */
    public function consumeUsage(User $user, int $amount = 1): ?Subscription
    {
        $subscription = $user->subscriptions()
            ->where(function (Builder $query): void {
                $query->whereNull('tenant_id')
                    ->orWhereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'));
            })
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('starts_at')
            ->first();

        if (! $subscription) {
            return null;
        }

        $subscription->increment('usage_used', $amount);

        return $subscription->refresh();
    }

    /**
     * Return the active subscription for a user, expiring stale rows first.
     */
    public function activeSubscription(User $user, ?Tenant $tenant = null): ?Subscription
    {
        $tenant = $this->billingTenantFor($user, $tenant);
        $this->expireEndedSubscriptions();

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

        return $query->latest('starts_at')->first();
    }

    private function nextInvoiceNumber(): string
    {
        return 'HV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }

    private function billingTenantFor(User $user, ?Tenant $tenant = null): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        return $user->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.id')
            ->first();
    }
}
