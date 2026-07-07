<?php

namespace App\Services\Billing;

use App\Enums\PlanAccessStatus;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DebugStepLogger;
use App\Support\JalaliDate;

/**
 * Resolves dashboard feature access from the user's current subscription state.
 *
 * @since 1.0.0
 */
class PlanAccessService
{
    private int $debugStepCounter = 0;

    public function __construct(private readonly ServiceEntitlementService $entitlements) {}

    /**
     * Return the user's plan access state and relevant subscription record.
     *
     * @return array{status: PlanAccessStatus, allowed: bool, subscription: ?Subscription, message_key: string}
     */
    public function stateFor(User $user, ?Tenant $tenant = null): array
    {
        $scope = 'billing.plan_access';
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Plan access resolution started', [
            'user_id' => $user->getKey(),
            'tenant_id' => $tenant?->getKey(),
            'tenant_present' => $tenant instanceof Tenant,
        ]);

        $isExempt = $this->isExempt($user);

        $this->debugStep($scope, 'Subscription exemption evaluated', [
            'user_id' => $user->getKey(),
            'is_exempt' => $isExempt,
        ]);

        if ($isExempt) {
            $this->debugStep($scope, 'Plan access allowed by exemption', [
                'user_id' => $user->getKey(),
                'status' => PlanAccessStatus::Exempt->value,
                'allowed' => true,
            ]);

            return $this->state(PlanAccessStatus::Exempt, true);
        }

        if ($tenant instanceof Tenant) {
            $this->debugStep($scope, 'Tenant base subscription lookup started', [
                'tenant_id' => $tenant->getKey(),
            ]);

            $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

            $this->debugStep($scope, 'Tenant base subscription lookup finished', [
                'tenant_id' => $tenant->getKey(),
                'subscription_found' => $subscription instanceof Subscription,
                'subscription_id' => $subscription?->getKey(),
                'subscription_status' => $subscription?->status,
            ]);
        } else {
            $subscription = null;

            $this->debugStep($scope, 'Tenant not provided; skipping tenant subscription lookup', [
                'user_id' => $user->getKey(),
            ]);
        }

        if (! $subscription instanceof Subscription) {
            $this->debugStep($scope, 'User base subscription fallback lookup started', [
                'user_id' => $user->getKey(),
            ]);

            $subscription = $user->subscriptions()
                ->with('plan')
                ->whereHas('plan', fn ($query) => $query->where('plan_type', 'base_subscription'))
                ->latest('starts_at')
                ->latest('id')
                ->first();

            $this->debugStep($scope, 'User base subscription fallback lookup finished', [
                'user_id' => $user->getKey(),
                'subscription_found' => $subscription instanceof Subscription,
                'subscription_id' => $subscription?->getKey(),
                'subscription_status' => $subscription?->status,
                'plan_id' => $subscription?->plan?->getKey(),
            ]);
        } else {
            $this->debugStep($scope, 'Tenant subscription selected; skipping user fallback lookup', [
                'tenant_id' => $tenant?->getKey(),
                'subscription_id' => $subscription->getKey(),
                'subscription_status' => $subscription->status,
                'plan_id' => $subscription->plan?->getKey(),
            ]);
        }

        if (! $subscription instanceof Subscription) {
            $this->debugStep($scope, 'No base subscription found', [
                'user_id' => $user->getKey(),
                'tenant_id' => $tenant?->getKey(),
                'status' => PlanAccessStatus::Inactive->value,
                'allowed' => false,
            ]);

            return $this->state(PlanAccessStatus::Inactive, false);
        }

        $this->debugStep($scope, 'Subscription selected for access evaluation', [
            'subscription_id' => $subscription->getKey(),
            'subscription_status' => $subscription->status,
            'plan_id' => $subscription->plan?->getKey(),
            'ends_at_present' => $subscription->ends_at !== null,
            'ends_at_past' => $subscription->ends_at ? JalaliDate::compareDateTime($subscription->ends_at, now()) <= 0 : null,
        ]);

        if ($subscription->status === 'active' && JalaliDate::compareDateTime($subscription->ends_at, now()) <= 0) {
            $this->debugStep($scope, 'Active subscription is past its end time; marking expired', [
                'subscription_id' => $subscription->getKey(),
                'previous_status' => $subscription->status,
                'next_status' => 'expired',
            ]);

            $subscription->forceFill(['status' => 'expired'])->save();

            $subscription->refresh();

            $this->debugStep($scope, 'Subscription marked expired', [
                'subscription_id' => $subscription->getKey(),
                'subscription_status' => $subscription->status,
                'status' => PlanAccessStatus::Expired->value,
                'allowed' => false,
            ]);

            return $this->state(PlanAccessStatus::Expired, false, $subscription);
        }

        if ($subscription->status === 'active') {
            $this->debugStep($scope, 'Plan access allowed by active subscription', [
                'subscription_id' => $subscription->getKey(),
                'subscription_status' => $subscription->status,
                'status' => PlanAccessStatus::Active->value,
                'allowed' => true,
            ]);

            return $this->state(PlanAccessStatus::Active, true, $subscription);
        }

        if ($subscription->status === 'expired') {
            $this->debugStep($scope, 'Plan access denied by expired subscription', [
                'subscription_id' => $subscription->getKey(),
                'subscription_status' => $subscription->status,
                'status' => PlanAccessStatus::Expired->value,
                'allowed' => false,
            ]);

            return $this->state(PlanAccessStatus::Expired, false, $subscription);
        }

        $this->debugStep($scope, 'Plan access denied by inactive subscription state', [
            'subscription_id' => $subscription->getKey(),
            'subscription_status' => $subscription->status,
            'status' => PlanAccessStatus::Inactive->value,
            'allowed' => false,
        ]);

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

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}
