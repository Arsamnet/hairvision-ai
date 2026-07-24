<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Resolves subscription and service credit availability.
 *
 * @since 1.0.0
 */
class ServiceEntitlementService
{
    /**
     * Create a new service entitlement service instance.
     *
     * @param  ServiceCatalog  $services  The services value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly ServiceCatalog $services) {}

    /**
     * Return the active base subscription for a clinic.
     *
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     * @param  bool  $lock  The lock value.
     * @return Subscription|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function activeBaseSubscriptionForTenant(?Tenant $tenant, bool $lock = false): ?Subscription
    {
        $scope = 'billing.entitlement.active_base_subscription';
        $step = 0;

        $step++;
        if (! $tenant) {
            return null;
        }

        $expiredCount = $this->expireEndedSubscriptions($tenant);

        $step++;
        $query = Subscription::query()
            ->with(['plan', 'serviceEntitlements'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('ends_at', '>', JalaliDate::nowDateTimeString())
            ->whereHas('plan', fn (Builder $query) => $query
                ->where('plan_type', 'base_subscription')
                ->where('status', 'active'))
            ->latest('starts_at')
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();

            $step++;
        }

        $subscription = $query->first();

        return $subscription;
    }

    /**
     * Mark active subscriptions expired when their duration has ended.
     *
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function expireEndedSubscriptions(?Tenant $tenant = null): int
    {
        $scope = 'billing.entitlement.expire_ended_subscriptions';
        $step = 0;

        $step++;
        $expiredCount = Subscription::query()
            ->when($tenant, fn (Builder $query) => $query->where('tenant_id', $tenant->id))
            ->where('status', 'active')
            ->where('ends_at', '<=', JalaliDate::nowDateTimeString())
            ->update(['status' => 'expired']);

        return $expiredCount;
    }

    /**
     * Return active service entitlement for a clinic and service.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $serviceKey  The service key value.
     * @param  bool  $lock  The lock value.
     * @return SubscriptionServiceEntitlement|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function activeEntitlement(Tenant $tenant, string $serviceKey, bool $lock = false): ?SubscriptionServiceEntitlement
    {
        $scope = 'billing.entitlement.active_entitlement';
        $step = 0;

        $step++;
        $subscription = $this->activeBaseSubscriptionForTenant($tenant, $lock);

        if (! $subscription) {
            return null;
        }

        $step++;
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

            $step++;
        }

        $entitlement = $query->first();

        return $entitlement;
    }

    /**
     * Resolve a user-facing service availability state.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $serviceKey  The service key value.
     * @param  int  $amount  The amount value.
     * @return array{available: bool, status: string, message_key: string, subscription: ?Subscription, entitlement: ?SubscriptionServiceEntitlement, remaining_credit: int}
     *
     * @since 1.0.0
     */
    public function stateFor(Tenant $tenant, string $serviceKey, int $amount = 1): array
    {
        $scope = 'billing.entitlement.state_for';
        $step = 0;

        $step++;
        $subscription = $this->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            return $this->state(false, 'subscription_required', 'subscriptions.errors.required');
        }

        $step++;
        $entitlement = $this->activeEntitlement($tenant, $serviceKey);

        if (! $entitlement) {
            return $this->state(false, 'service_unavailable', 'subscriptions.errors.service_unavailable', $subscription);
        }

        $remainingCredit = $this->remainingCreditForSubscription($subscription);

        $step++;
        if ($remainingCredit < $amount) {
            return $this->state(false, 'credit_finished', 'credits.errors.finished', $subscription, $entitlement, $remainingCredit);
        }

        return $this->state(true, 'available', 'subscriptions.statuses.available', $subscription, $entitlement, $remainingCredit);
    }

    /**
     * Require that a service can be used by the clinic.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $serviceKey  The service key value.
     * @param  int  $amount  The amount value.
     * @return SubscriptionServiceEntitlement Resolved operation result.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    public function assertCanUse(Tenant $tenant, string $serviceKey, int $amount = 1): SubscriptionServiceEntitlement
    {
        $scope = 'billing.entitlement.assert_can_use';
        $step = 0;

        $step++;
        $state = $this->stateFor($tenant, $serviceKey, $amount);

        $step++;
        if (! $state['available'] || ! $state['entitlement']) {
            throw ValidationException::withMessages([
                'service_access' => __($state['message_key'], [
                    'service' => $this->services->label($serviceKey),
                ]),
            ]);
        }

        return $state['entitlement'];
    }

    /**
     * Return all entitlement states for display on dashboards.
     *
     * @param  Subscription|null  $subscription  The subscription value.
     * @return Collection<int, array{service_key: string, label: string, remaining_credit: int, used_credit: int, total_credit: int, status: string}>
     *
     * @since 1.0.0
     */
    public function summaryForSubscription(?Subscription $subscription): Collection
    {
        $scope = 'billing.entitlement.summary_for_subscription';
        $step = 0;

        $step++;
        if (! $subscription) {
            return collect();
        }

        $remainingCredit = $this->remainingCreditForSubscription($subscription);
        $totalCredit = $this->totalCreditForSubscription($subscription);

        $summary = $subscription->serviceEntitlements()
            ->orderBy('service_key')
            ->get()
            ->map(fn (SubscriptionServiceEntitlement $entitlement): array => [
                'service_key' => $entitlement->service_key,
                'label' => $this->services->label($entitlement->service_key),
                'remaining_credit' => $remainingCredit,
                'used_credit' => $entitlement->used_credit,
                'total_credit' => $totalCredit,
                'status' => $entitlement->is_active && $remainingCredit > 0 ? 'available' : 'credit_finished',
            ]);

        return $summary;
    }

    /**
     * Execute the total credit for subscription operation.
     *
     * @param  Subscription|null  $subscription  The subscription value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function totalCreditForSubscription(?Subscription $subscription): int
    {
        if (! $subscription) {
            return 0;
        }

        if (! CreditTransaction::query()->where('subscription_id', $subscription->id)->exists()) {
            return (int) $subscription->serviceEntitlements()->sum(DB::raw('included_credit + purchased_credit'));
        }

        return (int) CreditTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->whereIn('type', [
                CreditTransactionType::Grant->value,
                CreditTransactionType::TopUp->value,
                CreditTransactionType::Adjustment->value,
                CreditTransactionType::Refund->value,
            ])
            ->where('amount', '>', 0)
            ->sum('amount');
    }

    /**
     * Execute the remaining credit for subscription operation.
     *
     * @param  Subscription|null  $subscription  The subscription value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    public function remainingCreditForSubscription(?Subscription $subscription): int
    {
        if (! $subscription) {
            return 0;
        }

        $legacyRemaining = max(0, (int) $subscription->serviceEntitlements()->sum(DB::raw('included_credit + purchased_credit - used_credit')));

        if (! CreditTransaction::query()->where('subscription_id', $subscription->id)->exists()) {
            return $legacyRemaining;
        }

        $ledgerRemaining = max(0, (int) CreditTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->sum('amount'));

        return $legacyRemaining > 0 ? min($ledgerRemaining, $legacyRemaining) : $ledgerRemaining;
    }

    /**
     * Build a normalized entitlement state.
     *
     * @param  bool  $available  The available value.
     * @param  string  $status  Target lifecycle status.
     * @param  string  $messageKey  The message key value.
     * @param  Subscription|null  $subscription  The subscription value.
     * @param  SubscriptionServiceEntitlement|null  $entitlement  The entitlement value.
     * @param  int  $remainingCredit  The remaining credit value.
     * @return array{available: bool, status: string, message_key: string, subscription: ?Subscription, entitlement: ?SubscriptionServiceEntitlement, remaining_credit: int}
     *
     * @since 1.0.0
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
}
