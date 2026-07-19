<?php

namespace Database\Seeders;

use App\Models\CreditTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionActivationService;
use App\Support\JalaliDate;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $aryaAdmin = User::query()->where('phone_normalized', '09217359491')->firstOrFail();
        $demoAdmin = User::query()->where('phone_normalized', '09004666673')->firstOrFail();

        $this->seedStarterSubscription(
            tenantSlug: 'arya',
            planName: 'پلن شروع',
            invoiceNumber: 'BS-1',
            paymentKey: 'seed-arya-starter-plan-paid',
            authority: 'SEED-ARYA-STARTER',
            referenceId: 'SEED-ARYA-PAID',
            user: $aryaAdmin,
        );

        $this->seedStarterSubscription(
            tenantSlug: 'noja',
            planName: 'پلن شروع کاشت مو',
            invoiceNumber: 'BS-2',
            paymentKey: 'seed-noja-hair-starter-plan-paid',
            authority: 'SEED-NOJA-HAIR-STARTER',
            referenceId: 'SEED-NOJA-HAIR-PAID',
            user: null,
        );

        $this->seedStarterSubscription(
            tenantSlug: 'demo',
            planName: 'پلن شروع',
            invoiceNumber: 'BS-3',
            paymentKey: 'seed-demo-starter-plan-paid',
            authority: 'SEED-DEMO-STARTER',
            referenceId: 'SEED-DEMO-PAID',
            user: $demoAdmin,
        );
    }

    private function seedStarterSubscription(
        string $tenantSlug,
        string $planName,
        string $invoiceNumber,
        string $paymentKey,
        string $authority,
        string $referenceId,
        ?User $user,
    ): void {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();
        $starterPlan = Plan::query()
            ->with('serviceEntitlements')
            ->where('name', $planName)
            ->firstOrFail();

        $activeStarter = $this->activeStarterSubscription($tenant, $starterPlan);
        $now = now();

        $invoice = Invoice::query()->firstOrNew([
            'number' => $invoiceNumber,
        ]);

        $invoice->fill([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'plan_id' => $starterPlan->id,
            'status' => 'paid',
            'amount' => $starterPlan->price,
            'currency' => $starterPlan->currency,
            'due_at' => $now,
            'paid_at' => $now,
            'expires_at' => $now->copy()->addDays((int) $starterPlan->duration_days),
            'metadata_json' => [
                'seeded' => true,
                'clinic' => $tenantSlug,
                'plan_type' => $starterPlan->plan_type->value,
                'service_credits' => $starterPlan->serviceCredits(),
            ],
        ]);

        if (! $activeStarter) {
            $invoice->activated_at = null;
        } elseif (! $invoice->activated_at) {
            $invoice->activated_at = $activeStarter->starts_at;
        }

        $invoice->save();

        $this->seedPayment($tenant, $user, $starterPlan, $invoice->refresh(), $paymentKey, $authority, $referenceId);

        $subscription = $activeStarter;

        if (! $subscription) {
            $subscription = app(SubscriptionActivationService::class)
                ->activateInvoice($invoice->refresh());
        }

        if (! $subscription) {
            return;
        }

        $subscription->forceFill([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'plan_id' => $starterPlan->id,
            'invoice_id' => $subscription->invoice_id ?? $invoice->id,
            'status' => 'active',
            'usage_limit' => array_sum($starterPlan->serviceCredits()),
        ])->save();

        $this->syncSeededSubscriptionEntitlements(
            $tenant,
            $user,
            $starterPlan,
            $subscription->refresh(),
            $invoice->refresh()
        );
    }

    private function activeStarterSubscription(Tenant $tenant, Plan $starterPlan): ?Subscription
    {
        return Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('plan_id', $starterPlan->id)
            ->where('status', 'active')
            ->where('ends_at', '>', JalaliDate::nowDateTimeString())
            ->latest('starts_at')
            ->latest('id')
            ->first();
    }

    private function seedPayment(
        Tenant $tenant,
        ?User $user,
        Plan $starterPlan,
        Invoice $invoice,
        string $paymentKey,
        string $authority,
        string $referenceId,
    ): void {
        $gateway = PaymentGateway::query()->where('provider_code', 'local')->first()
            ?? PaymentGateway::query()->where('is_default', true)->first();

        if (! $gateway) {
            return;
        }

        Payment::query()->updateOrCreate(
            ['idempotency_key' => $paymentKey],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user?->id,
                'invoice_id' => $invoice->id,
                'payment_gateway_id' => $gateway->id,
                'status' => 'paid',
                'amount' => $starterPlan->price,
                'currency' => $starterPlan->currency,
                'authority' => $authority,
                'reference_id' => $referenceId,
                'gateway_response_json' => [
                    'provider' => $gateway->provider_code,
                    'seeded' => true,
                ],
                'verified_at' => now(),
            ]
        );
    }

    private function syncSeededSubscriptionEntitlements(
        Tenant $tenant,
        ?User $user,
        Plan $starterPlan,
        Subscription $subscription,
        Invoice $invoice,
    ): void {
        foreach ($starterPlan->fresh('serviceEntitlements')->serviceCredits() as $serviceKey => $credit) {
            $entitlement = SubscriptionServiceEntitlement::query()->updateOrCreate(
                [
                    'subscription_id' => $subscription->id,
                    'service_key' => $serviceKey,
                    'source_type' => 'base_plan',
                ],
                [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $starterPlan->id,
                    'source_id' => $starterPlan->id,
                    'included_credit' => $credit,
                    'purchased_credit' => 0,
                    'is_active' => true,
                    'starts_at' => $subscription->starts_at,
                    'ends_at' => $subscription->ends_at,
                ]
            );

            CreditTransaction::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'subscription_service_entitlement_id' => $entitlement->id,
                    'service_key' => $serviceKey,
                    'type' => 'grant',
                    'related_type' => $invoice->getMorphClass(),
                    'related_id' => $invoice->id,
                ],
                [
                    'amount' => $credit,
                    'balance_after' => $entitlement->remainingCredit(),
                    'description' => __('credits.descriptions.base_grant', ['plan' => $starterPlan->name]),
                    'created_by' => $user?->id,
                ]
            );
        }
    }
}
