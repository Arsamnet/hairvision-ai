<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves clinic subscription and service-level credit availability.
 *
 * @since 1.0.0
 */
class ServiceEntitlementService
{
    public function __construct(private readonly ServiceCatalog $services) {}

    /**
     * Return the active base subscription for a clinic, expiring stale rows first.
     */
    public function activeBaseSubscriptionForTenant(?Tenant $tenant, bool $lock = false): ?Subscription
    {
        if (! $tenant) {
            return null;
        }

        $this->expireEndedSubscriptions($tenant);

        $query = Subscription::query()
            ->with(['plan', 'serviceEntitlements'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->whereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'))
            ->latest('starts_at')
            ->latest('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Mark active subscriptions expired when their base duration has ended.
     */
    public function expireEndedSubscriptions(?Tenant $tenant = null): int
    {
        return Subscription::query()
            ->when($tenant, fn (Builder $query) => $query->where('tenant_id', $tenant->id))
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Return active service entitlement for a clinic and service.
     */
    public function activeEntitlement(Tenant $tenant, string $serviceKey, bool $lock = false): ?SubscriptionServiceEntitlement
    {
        $subscription = $this->activeBaseSubscriptionForTenant($tenant, $lock);

        if (! $subscription) {
            return null;
        }

        $query = SubscriptionServiceEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->where('subscription_id', $subscription->id)
            ->where('service_key', $serviceKey)
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Resolve a user-facing service availability state.
     *
     * @return array{available: bool, status: string, message_key: string, subscription: ?Subscription, entitlement: ?SubscriptionServiceEntitlement, remaining_credit: int}
     */
    public function stateFor(Tenant $tenant, string $serviceKey, int $amount = 1): array
    {
        $subscription = $this->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            return $this->state(false, 'subscription_required', 'subscriptions.errors.required');
        }

        $entitlement = $this->activeEntitlement($tenant, $serviceKey);

        if (! $entitlement) {
            return $this->state(false, 'service_unavailable', 'subscriptions.errors.service_unavailable', $subscription);
        }

        $remainingCredit = $entitlement->remainingCredit();

        if ($remainingCredit < $amount) {
            return $this->state(false, 'credit_finished', 'credits.errors.finished', $subscription, $entitlement, $remainingCredit);
        }

        return $this->state(true, 'available', 'subscriptions.statuses.available', $subscription, $entitlement, $remainingCredit);
    }

    /**
     * Require that a service can be used by the clinic.
     *
     * @throws ValidationException
     */
    public function assertCanUse(Tenant $tenant, string $serviceKey, int $amount = 1): SubscriptionServiceEntitlement
    {
        $state = $this->stateFor($tenant, $serviceKey, $amount);

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
     * @return Collection<int, array{service_key: string, label: string, remaining_credit: int, used_credit: int, total_credit: int, status: string}>
     */
    public function summaryForSubscription(?Subscription $subscription): Collection
    {
        if (! $subscription) {
            return collect();
        }

        return $subscription->serviceEntitlements()
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
    }

    /**
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
}
