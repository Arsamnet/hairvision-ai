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
        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $admin = User::query()->where('phone_normalized', '09217359491')->firstOrFail();
        $starterPlan = Plan::query()
            ->with('serviceEntitlements')
            ->where('name', 'پلن شروع')
            ->firstOrFail();

        $activeStarter = $this->activeStarterSubscription($tenant, $starterPlan);
        $now = now();

        $invoice = Invoice::query()->firstOrNew([
            'number' => 'HV-SEED-ARIA-STARTER',
        ]);

        $invoice->fill([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'plan_id' => $starterPlan->id,
            'status' => 'paid',
            'amount' => $starterPlan->price,
            'currency' => $starterPlan->currency,
            'due_at' => $now,
            'paid_at' => $now,
            'expires_at' => $now->copy()->addDays((int) $starterPlan->duration_days),
            'metadata_json' => [
                'seeded' => true,
                'clinic' => 'aria',
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

        $this->seedPayment($tenant, $admin, $starterPlan, $invoice->refresh());

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
            'user_id' => $admin->id,
            'plan_id' => $starterPlan->id,
            'invoice_id' => $subscription->invoice_id ?? $invoice->id,
            'status' => 'active',
            'usage_limit' => array_sum($starterPlan->serviceCredits()),
        ])->save();

        $this->syncSeededSubscriptionEntitlements(
            $tenant,
            $admin,
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

    private function seedPayment(Tenant $tenant, User $admin, Plan $starterPlan, Invoice $invoice): void
    {
        $gateway = PaymentGateway::query()->where('provider_code', 'local')->first()
            ?? PaymentGateway::query()->where('is_default', true)->first();

        if (! $gateway) {
            return;
        }

        Payment::query()->updateOrCreate(
            ['idempotency_key' => 'seed-aria-starter-plan-paid'],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $admin->id,
                'invoice_id' => $invoice->id,
                'payment_gateway_id' => $gateway->id,
                'status' => 'paid',
                'amount' => $starterPlan->price,
                'currency' => $starterPlan->currency,
                'authority' => 'SEED-ARIA-STARTER',
                'reference_id' => 'SEED-ARIA-PAID',
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
        User $admin,
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
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
