<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Enums\PlanType;
use App\Enums\ServiceEntitlementSource;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Activates paid plan invoices into subscriptions, entitlements, and credits.
 *
 * @since 1.0.0
 */
class SubscriptionActivationService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly CreditLedgerService $ledger,
        private readonly ServiceCatalog $services,
    ) {}

    /**
     * Validate purchase rules before an invoice is issued.
     *
     * @throws ValidationException
     */
    public function validatePurchase(User $user, Plan $plan, ?Tenant $tenant): void
    {
        if ($plan->status !== 'active') {
            throw ValidationException::withMessages([
                'plan' => __('plans.inactive_plan'),
            ]);
        }

        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        match ($plan->plan_type) {
            PlanType::BaseSubscription => $this->validateBasePlanPurchase($plan, $tenant),
            PlanType::ServiceAddOn => $this->validateServiceAddOnPurchase($plan, $tenant),
            PlanType::CreditTopUp => $this->validateCreditTopUpPurchase($plan, $tenant),
        };
    }

    /**
     * Activate a paid invoice exactly once.
     *
     * @throws ValidationException
     */
    public function activateInvoice(Invoice $invoice): ?Subscription
    {
        return DB::transaction(function () use ($invoice): ?Subscription {
            $invoice = Invoice::query()
                ->with(['plan.serviceEntitlements', 'tenant', 'user'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->activated_at || ! $invoice->plan) {
                return $this->entitlements->activeBaseSubscriptionForTenant($invoice->tenant);
            }

            $subscription = match ($invoice->plan->plan_type) {
                PlanType::BaseSubscription => $this->activateBasePlan($invoice->user, $invoice->tenant, $invoice->plan, $invoice),
                PlanType::ServiceAddOn => $this->activateServiceAddOn($invoice->user, $invoice->tenant, $invoice->plan, $invoice),
                PlanType::CreditTopUp => $this->activateCreditTopUp($invoice->user, $invoice->tenant, $invoice->plan, $invoice),
            };

            $invoice->forceFill(['activated_at' => now()])->save();

            return $subscription;
        });
    }

    private function validateBasePlanPurchase(Plan $plan, Tenant $tenant): void
    {
        $active = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if ($active?->plan_id === $plan->id) {
            throw ValidationException::withMessages([
                'plan' => __('plans.same_active_plan_blocked'),
            ]);
        }
    }

    private function validateServiceAddOnPurchase(Plan $plan, Tenant $tenant): void
    {
        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.add_on_requires_base'),
            ]);
        }

        foreach (array_keys($plan->serviceCredits()) as $serviceKey) {
            if ($this->entitlements->activeEntitlement($tenant, $serviceKey)) {
                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.service_duplicate', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }
        }
    }

    private function validateCreditTopUpPurchase(Plan $plan, Tenant $tenant): void
    {
        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.top_up_requires_base'),
            ]);
        }

        foreach (array_keys($plan->serviceCredits()) as $serviceKey) {
            if (! $this->entitlements->activeEntitlement($tenant, $serviceKey)) {
                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.top_up_requires_service', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }
        }
    }

    private function activateBasePlan(User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice): Subscription
    {
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'))
            ->update([
                'status' => 'replaced',
                'ends_at' => now(),
            ]);

        $totalCredit = array_sum($plan->serviceCredits());

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'invoice_id' => $invoice->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) $plan->duration_days),
            'usage_limit' => $totalCredit,
            'usage_used' => 0,
        ]);

        foreach ($plan->serviceCredits() as $serviceKey => $credit) {
            $entitlement = SubscriptionServiceEntitlement::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
                'source_type' => ServiceEntitlementSource::BasePlan->value,
                'source_id' => $plan->id,
                'included_credit' => $credit,
                'purchased_credit' => 0,
                'used_credit' => 0,
                'is_active' => true,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
            ]);

            $this->ledger->grant(
                $entitlement,
                $credit,
                CreditTransactionType::Grant,
                $invoice,
                $user,
                __('credits.descriptions.base_grant', ['plan' => $plan->name])
            );
        }

        return $subscription->refresh();
    }

    private function activateServiceAddOn(User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice): Subscription
    {
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $this->validateServiceAddOnPurchase($plan, $tenant);
        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.add_on_requires_base'),
            ]);
        }

        foreach ($plan->serviceCredits() as $serviceKey => $credit) {
            $entitlement = SubscriptionServiceEntitlement::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
                'source_type' => ServiceEntitlementSource::AddOn->value,
                'source_id' => $plan->id,
                'included_credit' => $credit,
                'purchased_credit' => 0,
                'used_credit' => 0,
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => $subscription->ends_at,
            ]);

            $this->ledger->grant(
                $entitlement,
                $credit,
                CreditTransactionType::Grant,
                $invoice,
                $user,
                __('credits.descriptions.add_on_grant', ['plan' => $plan->name])
            );
        }

        return $subscription->refresh();
    }

    private function activateCreditTopUp(User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice): Subscription
    {
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $this->validateCreditTopUpPurchase($plan, $tenant);
        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.top_up_requires_base'),
            ]);
        }

        foreach ($plan->serviceCredits() as $serviceKey => $credit) {
            $entitlement = $this->entitlements->activeEntitlement($tenant, $serviceKey, lock: true);

            if (! $entitlement) {
                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.top_up_requires_service', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }

            $this->ledger->topUp(
                $entitlement,
                $credit,
                $invoice,
                $user,
                __('credits.descriptions.top_up', ['plan' => $plan->name])
            );
        }

        return $subscription->refresh();
    }
}
