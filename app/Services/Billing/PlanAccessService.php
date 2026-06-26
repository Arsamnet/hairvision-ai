<?php

namespace App\Services\Billing;

use App\Enums\PlanAccessStatus;
use App\Models\Subscription;
use App\Models\User;

/**
 * Resolves dashboard feature access from the user's current subscription state.
 *
 * @since 1.0.0
 */
class PlanAccessService
{
    /**
     * Return the user's plan access state and relevant subscription record.
     *
     * @return array{status: PlanAccessStatus, allowed: bool, subscription: ?Subscription, message_key: string}
     */
    public function stateFor(User $user): array
    {
        if ($this->isExempt($user)) {
            return $this->state(PlanAccessStatus::Exempt, true);
        }

        $subscription = $user->subscriptions()
            ->with('plan')
            ->latest('starts_at')
            ->latest('id')
            ->first();

        if (! $subscription) {
            return $this->state(PlanAccessStatus::Inactive, false);
        }

        if ($subscription->status === 'active' && $subscription->usage_used >= $subscription->usage_limit) {
            $subscription->forceFill(['status' => 'expired'])->save();

            return $this->state(PlanAccessStatus::CreditFinished, false, $subscription->refresh());
        }

        if ($subscription->status === 'active' && $subscription->ends_at <= now()) {
            $subscription->forceFill(['status' => 'expired'])->save();

            return $this->state(PlanAccessStatus::Expired, false, $subscription->refresh());
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
