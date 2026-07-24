<?php

namespace App\Services\Billing;

use App\Enums\PlanAccessStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\JalaliDate;

/**
 * Resolves dashboard feature access from the user's current subscription state.
 *
 * @since 1.0.0
 */
class PlanAccessService
{
    /**
     * Create a new plan access service instance.
     *
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly ServiceEntitlementService $entitlements) {}

    /**
     * Return the user's plan access state and relevant subscription record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     * @return array{status: PlanAccessStatus, allowed: bool, subscription: ?Subscription, message_key: string}
     *
     * @since 1.0.0
     */
    public function stateFor(User $user, ?Tenant $tenant = null): array
    {
        $scope = 'billing.plan_access';

        $isExempt = $this->isExempt($user);

        if ($isExempt) {
            return $this->state(PlanAccessStatus::Exempt, true);
        }

        if ($tenant instanceof Tenant) {
            $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

            if (! $subscription instanceof Subscription) {
                $subscription = Subscription::query()
                    ->with('plan')
                    ->where('tenant_id', $tenant->id)
                    ->whereHas('plan', fn ($query) => $query->where('plan_type', 'base_subscription'))
                    ->latest('starts_at')
                    ->latest('id')
                    ->first();
            }

        } else {
            $subscription = null;

        }

        if (! $subscription instanceof Subscription) {
            $subscription = $user->subscriptions()
                ->with('plan')
                ->whereHas('plan', fn ($query) => $query->where('plan_type', 'base_subscription'))
                ->latest('starts_at')
                ->latest('id')
                ->first();

        } else {
        }

        if (! $subscription instanceof Subscription) {
            return $this->state(PlanAccessStatus::Inactive, false);
        }

        if ($subscription->status === 'active' && JalaliDate::compareDateTime($subscription->ends_at, now()) <= 0) {
            $subscription->forceFill(['status' => 'expired'])->save();

            $subscription->refresh();

            return $this->state(PlanAccessStatus::Expired, false, $subscription);
        }

        if ($subscription->status === 'active' && $subscription->plan?->status !== 'active') {
            return $this->state(PlanAccessStatus::PlanDisabled, false, $subscription);
        }

        if ($subscription->status === 'active') {
            return $this->state(PlanAccessStatus::Active, true, $subscription);
        }

        if ($subscription->status === 'expired') {
            return $this->state(PlanAccessStatus::Expired, false, $subscription);
        }

        return $this->state(PlanAccessStatus::Inactive, false, $subscription);
    }

    /**
     * Determine whether subscription gating should be skipped for this user.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isExempt(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasAnyPermission([
                'system.dashboard',
                'plans.manage',
                'billing.manage',
            ]);
    }

    /**
     * Execute the state operation.
     *
     * @param  PlanAccessStatus  $status  Target lifecycle status.
     * @param  bool  $allowed  The allowed value.
     * @param  Subscription|null  $subscription  The subscription value.
     * @return array{status: PlanAccessStatus, allowed: bool, subscription: ?Subscription, message_key: string}
     *
     * @since 1.0.0
     */
    private function state(PlanAccessStatus $status, bool $allowed, ?Subscription $subscription = null): array
    {
        return [
            'status' => $status,
            'allowed' => $allowed,
            'subscription' => $subscription,
            'message_key' => match ($status) {
                PlanAccessStatus::Expired => 'permissions.plan_expired',
                PlanAccessStatus::CreditFinished => 'permissions.plan_credit_finished',
                PlanAccessStatus::PlanDisabled => 'permissions.plan_disabled_by_system',
                PlanAccessStatus::Inactive => 'permissions.plan_required',
                default => 'dashboard.plan.'.$status->value,
            },
        ];
    }
}
