<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\PaymentGatewayService;
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
    public function __construct(private readonly PaymentGatewayService $gateways) {}

    /**
     * Create or return the latest unpaid invoice for a user's plan purchase.
     */
    public function invoiceForPlan(User $user, Plan $plan): Invoice
    {
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages([
                'plan' => __('plans.inactive_plan'),
            ]);
        }

        $activeSubscription = $this->activeSubscription($user);

        if ($activeSubscription?->plan_id === $plan->id) {
            throw ValidationException::withMessages([
                'plan' => __('plans.same_active_plan_blocked'),
            ]);
        }

        return Invoice::query()->firstOrCreate(
            [
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
            $payment = Payment::query()->with(['invoice.plan'])->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === 'paid' && $payment->verified_at) {
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
                $this->activatePlan($invoice->user, $invoice->plan, $invoice);
            }

            return $payment->refresh();
        });
    }

    /**
     * Replace any active user plan and activate the newly purchased one.
     */
    public function activatePlan(User $user, Plan $plan, Invoice $invoice): Subscription
    {
        Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update([
                'status' => 'replaced',
                'ends_at' => now(),
            ]);

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'invoice_id' => $invoice->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays($plan->duration_days),
            'usage_limit' => $plan->usage_limit,
            'usage_used' => 0,
        ]);
    }

    /**
     * Mark subscriptions expired by time or usage.
     */
    public function expireEndedSubscriptions(): int
    {
        return Subscription::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('ends_at', '<=', now())
                    ->orWhereColumn('usage_used', '>=', 'usage_limit');
            })
            ->update(['status' => 'expired']);
    }

    /**
     * Consume plan usage and expire the plan once the credit is finished.
     */
    public function consumeUsage(User $user, int $amount = 1): ?Subscription
    {
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('starts_at')
            ->first();

        if (! $subscription) {
            return null;
        }

        $subscription->increment('usage_used', $amount);
        $subscription->refresh();

        if ($subscription->usage_used >= $subscription->usage_limit) {
            $subscription->forceFill(['status' => 'expired'])->save();
        }

        return $subscription;
    }

    /**
     * Return the active subscription for a user, expiring stale rows first.
     */
    public function activeSubscription(User $user): ?Subscription
    {
        $this->expireEndedSubscriptions();

        return $user->subscriptions()
            ->with('plan')
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('starts_at')
            ->first();
    }

    private function nextInvoiceNumber(): string
    {
        return 'HV-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
    }
}
