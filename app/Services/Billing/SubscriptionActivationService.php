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
use App\Services\AI\ClinicAiModelProvisioningService;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

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
        private readonly ClinicAiModelProvisioningService $modelProvisioning,
    ) {}

    /**
     * Validate purchase rules before an invoice is issued.
     *
     * @throws ValidationException
     */
    public function validatePurchase(?User $user, Plan $plan, ?Tenant $tenant, ?array $modelAccessIds = null): void
    {
        $this->resetDebugSteps();

        $scope = 'billing.subscription_activation.validate_purchase';

        try {
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
                PlanType::BaseSubscription => $this->validateBasePlanPurchase($plan, $tenant, $scope),
                PlanType::ServiceAddOn => $this->validateServiceAddOnPurchase($plan, $tenant, $scope),
                PlanType::CreditTopUp => $this->validateCreditTopUpPurchase($plan, $tenant, $scope),
            };

            $this->modelProvisioning->assertPlanProvisionable($plan, $modelAccessIds);

        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Return the validated plan-model selection to snapshot on an invoice.
     *
     * @return array<int, int>
     */
    public function modelAccessIdsForPlan(Plan $plan): array
    {
        return $this->modelProvisioning->modelAccessIdsForPlan($plan);
    }

    /**
     * Activate a paid invoice exactly once.
     *
     * @throws ValidationException
     */
    public function activateInvoice(Invoice $invoice): ?Subscription
    {
        $this->resetDebugSteps();

        $scope = 'billing.subscription_activation.activate_invoice';

        try {
            return DB::transaction(function () use ($invoice, $scope): ?Subscription {
                $invoice = Invoice::query()
                    ->with(['plan.serviceEntitlements', 'tenant', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($invoice->id);

                if ($invoice->activated_at || ! $invoice->plan) {
                    $subscription = $this->entitlements->activeBaseSubscriptionForTenant($invoice->tenant);

                    return $subscription;
                }

                $subscription = match ($invoice->plan->plan_type) {
                    PlanType::BaseSubscription => $this->activateBasePlan($invoice->user, $invoice->tenant, $invoice->plan, $invoice, $scope),
                    PlanType::ServiceAddOn => $this->activateServiceAddOn($invoice->user, $invoice->tenant, $invoice->plan, $invoice, $scope),
                    PlanType::CreditTopUp => $this->activateCreditTopUp($invoice->user, $invoice->tenant, $invoice->plan, $invoice, $scope),
                };

                $invoice->forceFill(['activated_at' => now()])->save();

                return $subscription;
            });
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    private function validateBasePlanPurchase(Plan $plan, Tenant $tenant, string $scope = 'billing.subscription_activation.validate_base_plan'): void
    {
        $active = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if ($active?->plan_id === $plan->id) {
            throw ValidationException::withMessages([
                'plan' => __('plans.same_active_plan_blocked'),
            ]);
        }

        $serviceKeys = $plan->serviceKeys();

        if ($active && $serviceKeys !== []) {
            $alreadyAvailable = collect($serviceKeys)
                ->every(fn (string $serviceKey): bool => (bool) $this->entitlements->activeEntitlement($tenant, $serviceKey));
            $activeTotalCredit = $this->entitlements->totalCreditForSubscription($active);

            if ($alreadyAvailable && $activeTotalCredit >= (int) $plan->usage_limit) {
                throw ValidationException::withMessages([
                    'plan' => __('plans.service_access_already_available'),
                ]);
            }
        }
    }

    private function validateServiceAddOnPurchase(Plan $plan, Tenant $tenant, string $scope = 'billing.subscription_activation.validate_service_add_on'): void
    {
        $serviceKeys = $plan->serviceKeys();

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.add_on_requires_base'),
            ]);
        }

        foreach ($serviceKeys as $serviceKey) {
            if ($this->entitlements->activeEntitlement($tenant, $serviceKey)) {
                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.service_duplicate', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }
        }

    }

    private function validateCreditTopUpPurchase(Plan $plan, Tenant $tenant, string $scope = 'billing.subscription_activation.validate_credit_top_up'): void
    {
        $serviceKeys = $plan->serviceKeys();

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.top_up_requires_base'),
            ]);
        }

        foreach ($serviceKeys as $serviceKey) {
            if (! $this->entitlements->activeEntitlement($tenant, $serviceKey)) {
                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.top_up_requires_service', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }
        }

    }

    private function activateBasePlan(?User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice, string $scope = 'billing.subscription_activation.activate_base_plan'): Subscription
    {
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $replacedCount = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->whereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'))
            ->update([
                'status' => 'replaced',
                'ends_at' => JalaliDate::nowDateTimeString(),
            ]);

        $serviceCredits = $plan->serviceCredits();
        $serviceKeys = array_keys($serviceCredits);
        $totalCredit = (int) $plan->usage_limit;

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'plan_id' => $plan->id,
            'invoice_id' => $invoice->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays((int) $plan->duration_days),
            'usage_limit' => $totalCredit,
            'usage_used' => 0,
        ]);

        $grantEntitlement = null;

        foreach ($serviceKeys as $serviceKey) {
            $entitlement = SubscriptionServiceEntitlement::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
                'source_type' => ServiceEntitlementSource::BasePlan->value,
                'source_id' => $plan->id,
                'included_credit' => $serviceCredits[$serviceKey] ?? 0,
                'purchased_credit' => 0,
                'used_credit' => 0,
                'is_active' => true,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
            ]);

            $grantEntitlement ??= $entitlement;
        }

        if ($grantEntitlement && $totalCredit > 0) {
            $this->ledger->grant(
                $grantEntitlement,
                $totalCredit,
                CreditTransactionType::Grant,
                $invoice,
                $user,
                __('credits.descriptions.base_grant', ['plan' => $plan->name])
            );
        }

        $this->modelProvisioning->provisionPlan(
            $tenant,
            $plan,
            $user,
            $invoice->aiModelAccessIdsSnapshot(),
        );

        $subscription->refresh();

        return $subscription;
    }

    private function activateServiceAddOn(?User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice, string $scope = 'billing.subscription_activation.activate_service_add_on'): Subscription
    {
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $this->validateServiceAddOnPurchase($plan, $tenant, $scope);

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.add_on_requires_base'),
            ]);
        }

        $serviceCredits = $plan->serviceCredits();
        $serviceKeys = array_keys($serviceCredits);
        $totalCredit = (int) $plan->usage_limit;
        $grantEntitlement = null;

        foreach ($serviceKeys as $serviceKey) {
            $entitlement = SubscriptionServiceEntitlement::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
                'source_type' => ServiceEntitlementSource::AddOn->value,
                'source_id' => $plan->id,
                'included_credit' => $serviceCredits[$serviceKey] ?? 0,
                'purchased_credit' => 0,
                'used_credit' => 0,
                'is_active' => true,
                'starts_at' => now(),
                'ends_at' => $subscription->ends_at,
            ]);

            $grantEntitlement ??= $entitlement;
        }

        if ($grantEntitlement && $totalCredit > 0) {
            $this->ledger->grant(
                $grantEntitlement,
                $totalCredit,
                CreditTransactionType::Grant,
                $invoice,
                $user,
                __('credits.descriptions.add_on_grant', ['plan' => $plan->name])
            );
        }

        $this->modelProvisioning->provisionPlan(
            $tenant,
            $plan,
            $user,
            $invoice->aiModelAccessIdsSnapshot(),
        );

        $subscription->refresh();

        return $subscription;
    }

    private function activateCreditTopUp(?User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice, string $scope = 'billing.subscription_activation.activate_credit_top_up'): Subscription
    {
        if (! $tenant) {
            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $this->validateCreditTopUpPurchase($plan, $tenant, $scope);

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

        if (! $subscription) {
            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.top_up_requires_base'),
            ]);
        }

        $serviceKey = $plan->serviceKeys()[0] ?? $this->services->defaultServiceKey();
        $credit = (int) $plan->usage_limit;

        if ($credit > 0) {
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

        $subscription->refresh();

        return $subscription;
    }

    private function resetDebugSteps(): void {}

    private function planContext(Plan $plan): array
    {
        $serviceCredits = $plan->serviceCredits();

        return [
            'id' => $plan->id,
            'plan_type' => $this->planTypeValue($plan),
            'service_key' => $plan->service_key,
            'status' => $plan->status,
            'duration_days' => $plan->duration_days,
            'usage_limit' => $plan->usage_limit,
            'service_credit_keys' => array_keys($serviceCredits),
            'service_credit_count' => count($serviceCredits),
            'service_credit_sum' => array_sum($serviceCredits),
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
            'is_paid' => $invoice->paid_at !== null,
            'is_activated' => $invoice->activated_at !== null,
            'metadata_keys' => is_array($invoice->metadata_json) ? array_keys($invoice->metadata_json) : [],
        ];
    }

    private function subscriptionContext(Subscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'user_id' => $subscription->user_id,
            'plan_id' => $subscription->plan_id,
            'invoice_id' => $subscription->invoice_id,
            'status' => $subscription->status,
            'usage_limit' => $subscription->usage_limit,
            'usage_used' => $subscription->usage_used,
            'starts_at_set' => $subscription->starts_at !== null,
            'ends_at_set' => $subscription->ends_at !== null,
        ];
    }

    private function entitlementContext(SubscriptionServiceEntitlement $entitlement): array
    {
        return [
            'id' => $entitlement->id,
            'tenant_id' => $entitlement->tenant_id,
            'subscription_id' => $entitlement->subscription_id,
            'plan_id' => $entitlement->plan_id,
            'service_key' => $entitlement->service_key,
            'source_type' => $entitlement->source_type,
            'source_id' => $entitlement->source_id,
            'included_credit' => $entitlement->included_credit,
            'purchased_credit' => $entitlement->purchased_credit,
            'used_credit' => $entitlement->used_credit,
            'is_active' => (bool) $entitlement->is_active,
            'starts_at_set' => $entitlement->starts_at !== null,
            'ends_at_set' => $entitlement->ends_at !== null,
        ];
    }

    private function planTypeValue(Plan $plan): mixed
    {
        if ($plan->plan_type instanceof PlanType) {
            return $plan->plan_type->value;
        }

        return $plan->plan_type;
    }
}
