<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Maintains auditable service credit grants and consumption.
 *
 * @since 1.0.0
 */
class CreditLedgerService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly ServiceCatalog $services,
    ) {}

    /**
     * Record initial or add-on service credit.
     */
    public function grant(
        SubscriptionServiceEntitlement $entitlement,
        int $amount,
        CreditTransactionType $type = CreditTransactionType::Grant,
        ?Model $related = null,
        ?User $actor = null,
        ?string $description = null,
    ): CreditTransaction {
        $scope = 'billing.credit_ledger.grant';

        return $this->recordPositive($entitlement, $amount, $type, $related, $actor, $description, $scope);
    }

    /**
     * Add purchased credit to an existing service entitlement.
     */
    public function topUp(
        SubscriptionServiceEntitlement $entitlement,
        int $amount,
        ?Model $related = null,
        ?User $actor = null,
        ?string $description = null,
    ): CreditTransaction {
        $scope = 'billing.credit_ledger.top_up';

        try {
            return DB::transaction(function () use ($entitlement, $amount, $related, $actor, $description, $scope): CreditTransaction {
                $locked = SubscriptionServiceEntitlement::query()
                    ->lockForUpdate()
                    ->findOrFail($entitlement->id);

                $locked->increment('purchased_credit', $amount);
                $locked->refresh();
                $balanceAfter = $this->ledgerBalanceForSubscription($locked->subscription_id, fallbackToEntitlements: false) + $amount;

                return $this->createTransaction(
                    $locked,
                    CreditTransactionType::TopUp,
                    $amount,
                    $balanceAfter,
                    $related,
                    $actor,
                    $description,
                    $scope,
                );
            });
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Consume service credit atomically for a successful AI service request.
     *
     * @throws ValidationException
     */
    public function consume(
        Tenant $tenant,
        string $serviceKey,
        int $amount = 1,
        ?Model $related = null,
        ?User $actor = null,
        ?string $description = null,
    ): CreditTransaction {
        $scope = 'billing.credit_ledger.consume';

        try {
            return DB::transaction(function () use ($tenant, $serviceKey, $amount, $related, $actor, $description, $scope): CreditTransaction {
                $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

                if (! $subscription) {
                    throw ValidationException::withMessages([
                        'service_access' => __('subscriptions.errors.required'),
                    ]);
                }

                $entitlement = $this->entitlements->activeEntitlement($tenant, $serviceKey, lock: true);

                if (! $entitlement) {
                    throw ValidationException::withMessages([
                        'service_access' => __('subscriptions.errors.service_unavailable', [
                            'service' => $this->services->label($serviceKey),
                        ]),
                    ]);
                }

                $remainingBefore = $this->ledgerBalanceForSubscription($subscription->id);

                if ($remainingBefore < $amount) {
                    throw ValidationException::withMessages([
                        'service_access' => __('credits.errors.finished', [
                            'service' => $this->services->label($serviceKey),
                        ]),
                    ]);
                }

                $entitlement->forceFill([
                    'used_credit' => $entitlement->used_credit + $amount,
                ])->save();

                $entitlement->refresh();
                $balanceAfter = $remainingBefore - $amount;

                return $this->createTransaction(
                    $entitlement,
                    CreditTransactionType::Use,
                    -$amount,
                    $balanceAfter,
                    $related,
                    $actor,
                    $description,
                    $scope,
                );
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Record a positive credit transaction without changing entitlement counters.
     */
    private function recordPositive(
        SubscriptionServiceEntitlement $entitlement,
        int $amount,
        CreditTransactionType $type,
        ?Model $related,
        ?User $actor,
        ?string $description,
        string $scope,
    ): CreditTransaction {
        try {
            return DB::transaction(function () use ($entitlement, $amount, $type, $related, $actor, $description, $scope): CreditTransaction {
                $locked = SubscriptionServiceEntitlement::query()
                    ->lockForUpdate()
                    ->findOrFail($entitlement->id);
                $balanceAfter = $this->ledgerBalanceForSubscription($locked->subscription_id, fallbackToEntitlements: false) + $amount;

                return $this->createTransaction(
                    $locked,
                    $type,
                    $amount,
                    $balanceAfter,
                    $related,
                    $actor,
                    $description,
                    $scope,
                );
            });
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * Create the auditable ledger transaction.
     */
    private function createTransaction(
        SubscriptionServiceEntitlement $entitlement,
        CreditTransactionType $type,
        int $amount,
        int $balanceAfter,
        ?Model $related,
        ?User $actor,
        ?string $description,
        string $scope,
    ): CreditTransaction {
        $transaction = CreditTransaction::create([
            'tenant_id' => $entitlement->tenant_id,
            'subscription_id' => $entitlement->subscription_id,
            'subscription_service_entitlement_id' => $entitlement->id,
            'service_key' => $entitlement->service_key,
            'type' => $type->value,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'description' => $description,
            'created_by' => $actor?->id,
        ]);

        return $transaction;
    }

    public function ledgerBalanceForSubscription(int $subscriptionId, bool $fallbackToEntitlements = true): int
    {
        if ($fallbackToEntitlements && ! CreditTransaction::query()->where('subscription_id', $subscriptionId)->exists()) {
            $subscription = Subscription::query()->find($subscriptionId);

            return max(0, (int) ($subscription?->serviceEntitlements()
                ->sum(DB::raw('included_credit + purchased_credit - used_credit')) ?? 0));
        }

        return (int) CreditTransaction::query()
            ->where('subscription_id', $subscriptionId)
            ->sum('amount');
    }

    public function ledgerBalanceForTenant(Tenant $tenant): int
    {
        $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant);

        if (! $subscription) {
            return 0;
        }

        return $this->ledgerBalanceForSubscription($subscription->id);
    }

    /**
     * Return credit movements for clinic transparency.
     *
     * @return Collection<int, array{date: string, type: string, amount: int, balance_after: int|null, description: string|null, created_at: mixed}>
     */
    public function timelineForSubscription(?Subscription $subscription): Collection
    {
        if (! $subscription) {
            return collect();
        }

        $positiveRows = $subscription->creditTransactions()
            ->where('amount', '>', 0)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CreditTransaction $transaction): array => [
                'date' => $transaction->created_at?->toDateString() ?? '',
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'balance_after' => $transaction->balance_after,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
            ]);

        $dailyUseRows = $subscription->creditTransactions()
            ->where('amount', '<', 0)
            ->selectRaw('date(created_at) as movement_date, sum(amount) as amount, min(created_at) as first_created_at, max(created_at) as last_created_at')
            ->groupBy('movement_date')
            ->orderByDesc('movement_date')
            ->get()
            ->map(fn (mixed $row): array => [
                'date' => (string) $row->movement_date,
                'type' => CreditTransactionType::Use->value,
                'amount' => (int) $row->amount,
                'balance_after' => null,
                'description' => __('credits.descriptions.daily_usage'),
                'created_at' => $row->last_created_at,
            ]);

        return $positiveRows
            ->concat($dailyUseRows)
            ->sortByDesc('created_at')
            ->values();
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
