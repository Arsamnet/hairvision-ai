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
use App\Support\DebugStepLogger;
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
    private int $debugStepCounter = 0;

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
        $this->resetDebugSteps();

        $scope = 'billing.subscription_activation.validate_purchase';

        $this->debugStep($scope, 'Subscription purchase validation started.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'plan' => $this->planContext($plan),
        ]);

        try {
            if ($plan->status !== 'active') {
                $this->debugStep($scope, 'Subscription purchase validation failed because plan is inactive.', [
                    'user_id' => $user->id,
                    'tenant_id' => $tenant?->id,
                    'plan_id' => $plan->id,
                    'plan_status' => $plan->status,
                ]);

                throw ValidationException::withMessages([
                    'plan' => __('plans.inactive_plan'),
                ]);
            }

            if (! $tenant) {
                $this->debugStep($scope, 'Subscription purchase validation failed because tenant is missing.', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'plan_type' => $this->planTypeValue($plan),
                ]);

                throw ValidationException::withMessages([
                    'tenant' => __('subscriptions.errors.tenant_required'),
                ]);
            }

            $this->debugStep($scope, 'Dispatching validation by plan type.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_type' => $this->planTypeValue($plan),
            ]);

            match ($plan->plan_type) {
                PlanType::BaseSubscription => $this->validateBasePlanPurchase($plan, $tenant, $scope),
                PlanType::ServiceAddOn => $this->validateServiceAddOnPurchase($plan, $tenant, $scope),
                PlanType::CreditTopUp => $this->validateCreditTopUpPurchase($plan, $tenant, $scope),
            };

            $this->debugStep($scope, 'Subscription purchase validation completed successfully.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'plan_type' => $this->planTypeValue($plan),
            ]);
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Subscription purchase validation workflow failed.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant?->id,
                'plan_id' => $plan->id,
                'plan_type' => $this->planTypeValue($plan),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
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

        $this->debugStep($scope, 'Invoice activation workflow started.', [
            'invoice_id' => $invoice->id,
        ]);

        try {
            return DB::transaction(function () use ($invoice, $scope): ?Subscription {
                $invoice = Invoice::query()
                    ->with(['plan.serviceEntitlements', 'tenant', 'user'])
                    ->lockForUpdate()
                    ->findOrFail($invoice->id);

                $this->debugStep($scope, 'Invoice locked for activation.', [
                    'invoice' => $this->invoiceContext($invoice),
                    'has_plan' => $invoice->plan !== null,
                    'has_tenant' => $invoice->tenant !== null,
                    'has_user' => $invoice->user !== null,
                ]);

                if ($invoice->activated_at || ! $invoice->plan) {
                    $this->debugStep($scope, 'Invoice activation skipped because invoice is already activated or plan is missing.', [
                        'invoice' => $this->invoiceContext($invoice),
                        'already_activated' => $invoice->activated_at !== null,
                        'has_plan' => $invoice->plan !== null,
                    ]);

                    $subscription = $this->entitlements->activeBaseSubscriptionForTenant($invoice->tenant);

                    $this->debugStep($scope, 'Active base subscription returned after skipped invoice activation.', [
                        'tenant_id' => $invoice->tenant_id,
                        'resolved' => $subscription instanceof Subscription,
                        'subscription' => $subscription instanceof Subscription ? $this->subscriptionContext($subscription) : null,
                    ]);

                    return $subscription;
                }

                $this->debugStep($scope, 'Dispatching invoice activation by plan type.', [
                    'invoice' => $this->invoiceContext($invoice),
                    'plan' => $this->planContext($invoice->plan),
                    'service_credit_keys' => array_keys($invoice->plan->serviceCredits()),
                    'service_credit_count' => count($invoice->plan->serviceCredits()),
                ]);

                $subscription = match ($invoice->plan->plan_type) {
                    PlanType::BaseSubscription => $this->activateBasePlan($invoice->user, $invoice->tenant, $invoice->plan, $invoice, $scope),
                    PlanType::ServiceAddOn => $this->activateServiceAddOn($invoice->user, $invoice->tenant, $invoice->plan, $invoice, $scope),
                    PlanType::CreditTopUp => $this->activateCreditTopUp($invoice->user, $invoice->tenant, $invoice->plan, $invoice, $scope),
                };

                $invoice->forceFill(['activated_at' => now()])->save();

                $this->debugStep($scope, 'Invoice marked as activated.', [
                    'invoice' => $this->invoiceContext($invoice),
                    'subscription' => $subscription instanceof Subscription ? $this->subscriptionContext($subscription) : null,
                ]);

                return $subscription;
            });
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Invoice activation workflow failed.', [
                'invoice_id' => $invoice->id,
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function validateBasePlanPurchase(Plan $plan, Tenant $tenant, string $scope = 'billing.subscription_activation.validate_base_plan'): void
    {
        $this->debugStep($scope, 'Base subscription purchase validation started.', [
            'tenant_id' => $tenant->id,
            'plan' => $this->planContext($plan),
        ]);

        $active = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        $this->debugStep($scope, 'Active base subscription lookup completed for base plan validation.', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'has_active_subscription' => $active instanceof Subscription,
            'active_subscription_id' => $active?->id,
            'active_plan_id' => $active?->plan_id,
        ]);

        if ($active?->plan_id === $plan->id) {
            $this->debugStep($scope, 'Base subscription purchase validation failed because same plan is already active.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'active_subscription_id' => $active->id,
            ]);

            throw ValidationException::withMessages([
                'plan' => __('plans.same_active_plan_blocked'),
            ]);
        }

        $this->debugStep($scope, 'Base subscription purchase validation completed successfully.', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);
    }

    private function validateServiceAddOnPurchase(Plan $plan, Tenant $tenant, string $scope = 'billing.subscription_activation.validate_service_add_on'): void
    {
        $serviceCredits = $plan->serviceCredits();

        $this->debugStep($scope, 'Service add-on purchase validation started.', [
            'tenant_id' => $tenant->id,
            'plan' => $this->planContext($plan),
            'service_credit_keys' => array_keys($serviceCredits),
            'service_credit_count' => count($serviceCredits),
        ]);

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            $this->debugStep($scope, 'Service add-on purchase validation failed because active base subscription is missing.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
            ]);

            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.add_on_requires_base'),
            ]);
        }

        $this->debugStep($scope, 'Active base subscription found for service add-on validation.', [
            'tenant_id' => $tenant->id,
            'subscription' => $this->subscriptionContext($subscription),
        ]);

        foreach (array_keys($serviceCredits) as $serviceKey) {
            $this->debugStep($scope, 'Checking duplicate active entitlement for service add-on.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
            ]);

            if ($this->entitlements->activeEntitlement($tenant, $serviceKey)) {
                $this->debugStep($scope, 'Service add-on purchase validation failed because entitlement already exists.', [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'service_key' => $serviceKey,
                ]);

                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.service_duplicate', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }
        }

        $this->debugStep($scope, 'Service add-on purchase validation completed successfully.', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'service_credit_keys' => array_keys($serviceCredits),
        ]);
    }

    private function validateCreditTopUpPurchase(Plan $plan, Tenant $tenant, string $scope = 'billing.subscription_activation.validate_credit_top_up'): void
    {
        $serviceCredits = $plan->serviceCredits();

        $this->debugStep($scope, 'Credit top-up purchase validation started.', [
            'tenant_id' => $tenant->id,
            'plan' => $this->planContext($plan),
            'service_credit_keys' => array_keys($serviceCredits),
            'service_credit_count' => count($serviceCredits),
        ]);

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            $this->debugStep($scope, 'Credit top-up purchase validation failed because active base subscription is missing.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
            ]);

            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.top_up_requires_base'),
            ]);
        }

        $this->debugStep($scope, 'Active base subscription found for credit top-up validation.', [
            'tenant_id' => $tenant->id,
            'subscription' => $this->subscriptionContext($subscription),
        ]);

        foreach (array_keys($serviceCredits) as $serviceKey) {
            $this->debugStep($scope, 'Checking active entitlement for credit top-up.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
            ]);

            if (! $this->entitlements->activeEntitlement($tenant, $serviceKey)) {
                $this->debugStep($scope, 'Credit top-up purchase validation failed because active service entitlement is missing.', [
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'service_key' => $serviceKey,
                ]);

                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.top_up_requires_service', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }
        }

        $this->debugStep($scope, 'Credit top-up purchase validation completed successfully.', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'service_credit_keys' => array_keys($serviceCredits),
        ]);
    }

    private function activateBasePlan(User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice, string $scope = 'billing.subscription_activation.activate_base_plan'): Subscription
    {
        $this->debugStep($scope, 'Base plan activation started.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'plan' => $this->planContext($plan),
            'invoice_id' => $invoice->id,
        ]);

        if (! $tenant) {
            $this->debugStep($scope, 'Base plan activation failed because tenant is missing.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
            ]);

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

        $this->debugStep($scope, 'Existing active base subscriptions replaced.', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'replaced_count' => $replacedCount,
        ]);

        $serviceCredits = $plan->serviceCredits();
        $totalCredit = array_sum($serviceCredits);

        $this->debugStep($scope, 'Base plan service credits calculated.', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'service_credit_keys' => array_keys($serviceCredits),
            'service_credit_count' => count($serviceCredits),
            'total_credit' => $totalCredit,
        ]);

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

        $this->debugStep($scope, 'Base subscription created.', [
            'subscription' => $this->subscriptionContext($subscription),
            'invoice_id' => $invoice->id,
        ]);

        foreach ($serviceCredits as $serviceKey => $credit) {
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

            $this->debugStep($scope, 'Base plan service entitlement created.', [
                'entitlement' => $this->entitlementContext($entitlement),
                'credit_amount' => $credit,
            ]);

            $this->ledger->grant(
                $entitlement,
                $credit,
                CreditTransactionType::Grant,
                $invoice,
                $user,
                __('credits.descriptions.base_grant', ['plan' => $plan->name])
            );

            $this->debugStep($scope, 'Base plan credit grant recorded.', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'entitlement_id' => $entitlement->id,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'service_key' => $serviceKey,
                'credit_amount' => $credit,
                'transaction_type' => CreditTransactionType::Grant->value,
            ]);
        }

        $subscription->refresh();

        $this->debugStep($scope, 'Base plan activation completed successfully.', [
            'subscription' => $this->subscriptionContext($subscription),
            'invoice_id' => $invoice->id,
        ]);

        return $subscription;
    }

    private function activateServiceAddOn(User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice, string $scope = 'billing.subscription_activation.activate_service_add_on'): Subscription
    {
        $this->debugStep($scope, 'Service add-on activation started.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'plan' => $this->planContext($plan),
            'invoice_id' => $invoice->id,
        ]);

        if (! $tenant) {
            $this->debugStep($scope, 'Service add-on activation failed because tenant is missing.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
            ]);

            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $this->validateServiceAddOnPurchase($plan, $tenant, $scope);

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

        if (! $subscription) {
            $this->debugStep($scope, 'Service add-on activation failed because active base subscription is missing after lock.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
            ]);

            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.add_on_requires_base'),
            ]);
        }

        $this->debugStep($scope, 'Active base subscription locked for service add-on activation.', [
            'subscription' => $this->subscriptionContext($subscription),
            'invoice_id' => $invoice->id,
        ]);

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

            $this->debugStep($scope, 'Service add-on entitlement created.', [
                'entitlement' => $this->entitlementContext($entitlement),
                'credit_amount' => $credit,
            ]);

            $this->ledger->grant(
                $entitlement,
                $credit,
                CreditTransactionType::Grant,
                $invoice,
                $user,
                __('credits.descriptions.add_on_grant', ['plan' => $plan->name])
            );

            $this->debugStep($scope, 'Service add-on credit grant recorded.', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'entitlement_id' => $entitlement->id,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'service_key' => $serviceKey,
                'credit_amount' => $credit,
                'transaction_type' => CreditTransactionType::Grant->value,
            ]);
        }

        $subscription->refresh();

        $this->debugStep($scope, 'Service add-on activation completed successfully.', [
            'subscription' => $this->subscriptionContext($subscription),
            'invoice_id' => $invoice->id,
        ]);

        return $subscription;
    }

    private function activateCreditTopUp(User $user, ?Tenant $tenant, Plan $plan, Invoice $invoice, string $scope = 'billing.subscription_activation.activate_credit_top_up'): Subscription
    {
        $this->debugStep($scope, 'Credit top-up activation started.', [
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'plan' => $this->planContext($plan),
            'invoice_id' => $invoice->id,
        ]);

        if (! $tenant) {
            $this->debugStep($scope, 'Credit top-up activation failed because tenant is missing.', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
            ]);

            throw ValidationException::withMessages([
                'tenant' => __('subscriptions.errors.tenant_required'),
            ]);
        }

        $this->validateCreditTopUpPurchase($plan, $tenant, $scope);

        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

        if (! $subscription) {
            $this->debugStep($scope, 'Credit top-up activation failed because active base subscription is missing after lock.', [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
            ]);

            throw ValidationException::withMessages([
                'plan' => __('subscriptions.errors.top_up_requires_base'),
            ]);
        }

        $this->debugStep($scope, 'Active base subscription locked for credit top-up activation.', [
            'subscription' => $this->subscriptionContext($subscription),
            'invoice_id' => $invoice->id,
        ]);

        foreach ($plan->serviceCredits() as $serviceKey => $credit) {
            $this->debugStep($scope, 'Resolving active entitlement for credit top-up.', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'invoice_id' => $invoice->id,
                'service_key' => $serviceKey,
                'credit_amount' => $credit,
            ]);

            $entitlement = $this->entitlements->activeEntitlement($tenant, $serviceKey, lock: true);

            if (! $entitlement) {
                $this->debugStep($scope, 'Credit top-up activation failed because active entitlement is missing after lock.', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $plan->id,
                    'invoice_id' => $invoice->id,
                    'service_key' => $serviceKey,
                ]);

                throw ValidationException::withMessages([
                    'plan' => __('subscriptions.errors.top_up_requires_service', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }

            $this->debugStep($scope, 'Active entitlement locked for credit top-up.', [
                'entitlement' => $this->entitlementContext($entitlement),
                'credit_amount' => $credit,
            ]);

            $this->ledger->topUp(
                $entitlement,
                $credit,
                $invoice,
                $user,
                __('credits.descriptions.top_up', ['plan' => $plan->name])
            );

            $this->debugStep($scope, 'Credit top-up recorded.', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'entitlement_id' => $entitlement->id,
                'invoice_id' => $invoice->id,
                'user_id' => $user->id,
                'service_key' => $serviceKey,
                'credit_amount' => $credit,
            ]);
        }

        $subscription->refresh();

        $this->debugStep($scope, 'Credit top-up activation completed successfully.', [
            'subscription' => $this->subscriptionContext($subscription),
            'invoice_id' => $invoice->id,
        ]);

        return $subscription;
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
