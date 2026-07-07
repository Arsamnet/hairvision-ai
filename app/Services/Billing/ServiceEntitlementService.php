<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Support\DebugStepLogger;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves subscription and service credit availability.
 *
 * @since 1.0.0
 */
class ServiceEntitlementService
{
    public function __construct(private readonly ServiceCatalog $services) {}

    /**
     * Return the active base subscription for a clinic.
     */
    public function activeBaseSubscriptionForTenant(?Tenant $tenant, bool $lock = false): ?Subscription
    {
        $scope = 'billing.entitlement.active_base_subscription';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'has_tenant' => $tenant !== null,
            'tenant_id' => $tenant?->id,
            'lock' => $lock,
        ]);

        if (! $tenant) {
            $this->workflowStep($scope, $step, 'Tenant missing', [
                'has_tenant' => false,
            ]);

            return null;
        }

        $expiredCount = $this->expireEndedSubscriptions($tenant);

        $step = $this->workflowStep($scope, $step, 'Expired subscriptions checked', [
            'tenant_id' => $tenant->id,
            'expired_count' => $expiredCount,
        ]);

        $query = Subscription::query()
            ->with(['plan', 'serviceEntitlements'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('ends_at', '>', JalaliDate::nowDateTimeString())
            ->whereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'))
            ->latest('starts_at')
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();

            $step = $this->workflowStep($scope, $step, 'Lock applied to subscription query', [
                'tenant_id' => $tenant->id,
            ]);
        }

        $subscription = $query->first();

        $this->workflowStep($scope, $step, 'Subscription lookup completed', [
            'tenant_id' => $tenant->id,
            'subscription_found' => $subscription !== null,
            'subscription_id' => $subscription?->id,
            'plan_id' => $subscription?->plan_id,
            'status' => $subscription?->status,
            'service_entitlements_count' => $subscription?->serviceEntitlements?->count(),
        ]);

        return $subscription;
    }

    /**
     * Mark active subscriptions expired when their duration has ended.
     */
    public function expireEndedSubscriptions(?Tenant $tenant = null): int
    {
        $scope = 'billing.entitlement.expire_ended_subscriptions';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'has_tenant' => $tenant !== null,
            'tenant_id' => $tenant?->id,
        ]);

        $expiredCount = Subscription::query()
            ->when($tenant, fn (Builder $query) => $query->where('tenant_id', $tenant->id))
            ->where('status', 'active')
            ->where('ends_at', '<=', JalaliDate::nowDateTimeString())
            ->update(['status' => 'expired']);

        $this->workflowStep($scope, $step, 'Expired subscriptions updated', [
            'tenant_id' => $tenant?->id,
            'expired_count' => $expiredCount,
        ]);

        return $expiredCount;
    }

    /**
     * Return active service entitlement for a clinic and service.
     */
    public function activeEntitlement(Tenant $tenant, string $serviceKey, bool $lock = false): ?SubscriptionServiceEntitlement
    {
        $scope = 'billing.entitlement.active_entitlement';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'tenant_id' => $tenant->id,
            'service_key' => $serviceKey,
            'lock' => $lock,
        ]);

        $subscription = $this->activeBaseSubscriptionForTenant($tenant, $lock);

        if (! $subscription) {
            $this->workflowStep($scope, $step, 'Active subscription missing', [
                'tenant_id' => $tenant->id,
                'service_key' => $serviceKey,
            ]);

            return null;
        }

        $step = $this->workflowStep($scope, $step, 'Active subscription resolved', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'service_key' => $serviceKey,
        ]);

        $query = SubscriptionServiceEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->where('subscription_id', $subscription->id)
            ->where('service_key', $serviceKey)
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', JalaliDate::nowDateTimeString());
            });

        if ($lock) {
            $query->lockForUpdate();

            $step = $this->workflowStep($scope, $step, 'Lock applied to entitlement query', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'service_key' => $serviceKey,
            ]);
        }

        $entitlement = $query->first();

        $this->workflowStep($scope, $step, 'Entitlement lookup completed', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'service_key' => $serviceKey,
            'entitlement_found' => $entitlement !== null,
            'entitlement_id' => $entitlement?->id,
            'remaining_credit' => $entitlement?->remainingCredit(),
            'used_credit' => $entitlement?->used_credit,
            'total_credit' => $entitlement?->totalCredit(),
        ]);

        return $entitlement;
    }

    /**
     * Resolve a user-facing service availability state.
     *
     * @return array{available: bool, status: string, message_key: string, subscription: ?Subscription, entitlement: ?SubscriptionServiceEntitlement, remaining_credit: int}
     */
    public function stateFor(Tenant $tenant, string $serviceKey, int $amount = 1): array
    {
        $scope = 'billing.entitlement.state_for';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'tenant_id' => $tenant->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
        ]);

        $subscription = $this->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            $this->workflowStep($scope, $step, 'State resolved as subscription required', [
                'tenant_id' => $tenant->id,
                'service_key' => $serviceKey,
                'amount' => $amount,
                'status' => 'subscription_required',
            ]);

            return $this->state(false, 'subscription_required', 'subscriptions.errors.required');
        }

        $step = $this->workflowStep($scope, $step, 'Subscription resolved', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'service_key' => $serviceKey,
        ]);

        $entitlement = $this->activeEntitlement($tenant, $serviceKey);

        if (! $entitlement) {
            $this->workflowStep($scope, $step, 'State resolved as service unavailable', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'service_key' => $serviceKey,
                'amount' => $amount,
                'status' => 'service_unavailable',
            ]);

            return $this->state(false, 'service_unavailable', 'subscriptions.errors.service_unavailable', $subscription);
        }

        $remainingCredit = $entitlement->remainingCredit();

        $step = $this->workflowStep($scope, $step, 'Entitlement credit resolved', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'entitlement_id' => $entitlement->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
            'remaining_credit' => $remainingCredit,
            'used_credit' => $entitlement->used_credit,
            'total_credit' => $entitlement->totalCredit(),
        ]);

        if ($remainingCredit < $amount) {
            $this->workflowStep($scope, $step, 'State resolved as credit finished', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'entitlement_id' => $entitlement->id,
                'service_key' => $serviceKey,
                'amount' => $amount,
                'remaining_credit' => $remainingCredit,
                'status' => 'credit_finished',
            ]);

            return $this->state(false, 'credit_finished', 'credits.errors.finished', $subscription, $entitlement, $remainingCredit);
        }

        $this->workflowStep($scope, $step, 'State resolved as available', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'entitlement_id' => $entitlement->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
            'remaining_credit' => $remainingCredit,
            'status' => 'available',
        ]);

        return $this->state(true, 'available', 'subscriptions.statuses.available', $subscription, $entitlement, $remainingCredit);
    }

    /**
     * Require that a service can be used by the clinic.
     *
     * @throws ValidationException
     */
    public function assertCanUse(Tenant $tenant, string $serviceKey, int $amount = 1): SubscriptionServiceEntitlement
    {
        $scope = 'billing.entitlement.assert_can_use';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'tenant_id' => $tenant->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
        ]);

        $state = $this->stateFor($tenant, $serviceKey, $amount);

        $step = $this->workflowStep($scope, $step, 'State resolved', [
            'tenant_id' => $tenant->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
            'available' => $state['available'],
            'status' => $state['status'],
            'subscription_id' => $state['subscription']?->id,
            'entitlement_id' => $state['entitlement']?->id,
            'remaining_credit' => $state['remaining_credit'],
        ]);

        if (! $state['available'] || ! $state['entitlement']) {
            $this->workflowStep($scope, $step, 'Service usage rejected', [
                'tenant_id' => $tenant->id,
                'service_key' => $serviceKey,
                'amount' => $amount,
                'status' => $state['status'],
                'message_key' => $state['message_key'],
            ]);

            throw ValidationException::withMessages([
                'service_access' => __($state['message_key'], [
                    'service' => $this->services->label($serviceKey),
                ]),
            ]);
        }

        $this->workflowStep($scope, $step, 'Service usage allowed', [
            'tenant_id' => $tenant->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
            'entitlement_id' => $state['entitlement']->id,
            'remaining_credit' => $state['remaining_credit'],
        ]);

        return $state['entitlement'];
    }

    /**
     * Return all entitlement states for display on dashboards.
     *
     * @return Collection<int, array{service_key: string, label: string, remaining_credit: int, used_credit: int, total_credit: int, status: string}>
     */
    public function summaryForSubscription(?Subscription $subscription): Collection
    {
        $scope = 'billing.entitlement.summary_for_subscription';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'has_subscription' => $subscription !== null,
            'subscription_id' => $subscription?->id,
            'tenant_id' => $subscription?->tenant_id,
        ]);

        if (! $subscription) {
            $this->workflowStep($scope, $step, 'Subscription missing, empty summary returned', [
                'has_subscription' => false,
            ]);

            return collect();
        }

        $summary = $subscription->serviceEntitlements()
            ->orderBy('service_key')
            ->get()
            ->map(fn (SubscriptionServiceEntitlement $entitlement): array => [
                'service_key' => $entitlement->service_key,
                'label' => $this->services->label($entitlement->service_key),
                'remaining_credit' => $entitlement->remainingCredit(),
                'used_credit' => $entitlement->used_credit,
                'total_credit' => $entitlement->totalCredit(),
                'status' => $entitlement->is_active && $entitlement->remainingCredit() > 0 ? 'available' : 'credit_finished',
            ]);

        $this->workflowStep($scope, $step, 'Subscription summary prepared', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'items_count' => $summary->count(),
            'service_keys' => $summary->pluck('service_key')->values()->all(),
        ]);

        return $summary;
    }

    /**
     * Build a normalized entitlement state.
     *
     * @return array{available: bool, status: string, message_key: string, subscription: ?Subscription, entitlement: ?SubscriptionServiceEntitlement, remaining_credit: int}
     */
    private function state(
        bool $available,
        string $status,
        string $messageKey,
        ?Subscription $subscription = null,
        ?SubscriptionServiceEntitlement $entitlement = null,
        int $remainingCredit = 0,
    ): array {
        return [
            'available' => $available,
            'status' => $status,
            'message_key' => $messageKey,
            'subscription' => $subscription,
            'entitlement' => $entitlement,
            'remaining_credit' => $remainingCredit,
        ];
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
    private function workflowStep(string $scope, int $step, string $message, array $context = []): int
    {
        $step++;

        DebugStepLogger::workflowStep($scope, $step, $message, $context);

        return $step;
    }
}
