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
    public function __construct(private readonly ServiceEntitlementService $entitlements) {}

    /**
     * Return the user's plan access state and relevant subscription record.
     *
     * @return array{status: PlanAccessStatus, allowed: bool, subscription: ?Subscription, message_key: string}
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
     * @return array{status: PlanAccessStatus, allowed: bool, subscription: ?Subscription, message_key: string}
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
                PlanAccessStatus::Inactive => 'permissions.plan_required',
                default => 'dashboard.plan.'.$status->value,
            },
        ];
    }
}
