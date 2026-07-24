<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\GenerationAttempt;
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
    /**
     * Create a new credit ledger service instance.
     *
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     * @param  ServiceCatalog  $services  The services value.
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly ServiceCatalog $services,
    ) {}

    /**
     * Record initial or add-on service credit.
     *
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @param  int  $amount  The amount value.
     * @param  CreditTransactionType  $type  The type value.
     * @param  Model|null  $related  The related value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @param  string|null  $description  The description value.
     * @return CreditTransaction Resolved operation result.
     *
     * @since 1.0.0
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
     *
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @param  int  $amount  The amount value.
     * @param  Model|null  $related  The related value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @param  string|null  $description  The description value.
     * @return CreditTransaction Resolved operation result.
     *
     * @since 1.0.0
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
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $serviceKey  The service key value.
     * @param  int  $amount  The amount value.
     * @param  Model|null  $related  The related value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @param  string|null  $description  The description value.
     * @return CreditTransaction Resolved operation result.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
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

                $this->ensureLedgerBaseline($subscription, $entitlement);
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
     * Reserve clinic credit before an external provider can be called.
     *
     * The negative reservation participates in the ledger balance immediately,
     * preventing concurrent requests from spending the same remaining credit.
     *
     * @param  GenerationAttempt  $attempt  The attempt value.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $serviceKey  The service key value.
     * @param  int  $amount  The amount value.
     * @param  string|null  $description  The description value.
     * @return CreditTransaction Resolved operation result.
     *
     * @throws ValidationException
     *
     * @since 2.0.0
     */
    public function reserveForAttempt(
        GenerationAttempt $attempt,
        Tenant $tenant,
        string $serviceKey,
        int $amount,
        ?string $description = null,
    ): CreditTransaction {
        return DB::transaction(function () use ($attempt, $tenant, $serviceKey, $amount, $description): CreditTransaction {
            $lockedAttempt = GenerationAttempt::query()
                ->with('generationRequest')
                ->lockForUpdate()
                ->findOrFail($attempt->id);

            if ($lockedAttempt->credit_transaction_id) {
                return CreditTransaction::query()->findOrFail($lockedAttempt->credit_transaction_id);
            }

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

            $amount = max(1, $amount);
            $this->ensureLedgerBaseline($subscription, $entitlement);
            $remainingBefore = $this->ledgerBalanceForSubscription($subscription->id);

            if ($remainingBefore < $amount) {
                throw ValidationException::withMessages([
                    'service_access' => __('credits.errors.finished', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }

            $reservation = $this->createTransaction(
                $entitlement,
                CreditTransactionType::Reservation,
                -$amount,
                $remainingBefore - $amount,
                $lockedAttempt,
                null,
                $description,
                'billing.credit_ledger.reserve',
                reservationStatus: 'reserved',
                generationRequestId: $lockedAttempt->generation_request_id,
            );

            $lockedAttempt->forceFill(['credit_transaction_id' => $reservation->id])->save();

            return $reservation;
        });
    }

    /**
     * Convert an existing reservation into final usage without another debit.
     *
     * @param  GenerationAttempt  $attempt  The attempt value.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $serviceKey  The service key value.
     * @param  int  $amount  The amount value.
     * @param  string|null  $description  The description value.
     * @return CreditTransaction Resolved operation result.
     *
     * @since 2.0.0
     */
    public function consumeReservation(
        GenerationAttempt $attempt,
        Tenant $tenant,
        string $serviceKey,
        int $amount,
        ?string $description = null,
    ): CreditTransaction {
        $reservationId = GenerationAttempt::query()->whereKey($attempt->id)->value('credit_transaction_id');

        if (! $reservationId) {
            return $this->consume($tenant, $serviceKey, $amount, $attempt->generationRequest, description: $description);
        }

        return DB::transaction(function () use ($attempt, $reservationId, $description): CreditTransaction {
            $lockedAttempt = GenerationAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $reservation = CreditTransaction::query()->lockForUpdate()->findOrFail($reservationId);

            if ($reservation->reservation_status === 'consumed') {
                return $reservation;
            }

            if ($reservation->reservation_status !== 'reserved') {
                throw ValidationException::withMessages([
                    'service_access' => __('credits.errors.finished', [
                        'service' => $this->services->label($reservation->service_key),
                    ]),
                ]);
            }

            $entitlement = SubscriptionServiceEntitlement::query()
                ->lockForUpdate()
                ->findOrFail($reservation->subscription_service_entitlement_id);
            $entitlement->forceFill([
                'used_credit' => $entitlement->used_credit + abs((int) $reservation->amount),
            ])->save();

            $reservation->forceFill([
                'type' => CreditTransactionType::Use->value,
                'reservation_status' => 'consumed',
                'description' => $description ?: $reservation->description,
            ])->save();

            $lockedAttempt->generationRequest?->forceFill([
                'credit_transaction_id' => $reservation->id,
            ])->save();

            return $reservation->refresh();
        });
    }

    /**
     * Release a definite failed/cancelled attempt reservation exactly once.
     *
     * @param  GenerationAttempt  $attempt  The attempt value.
     * @param  string|null  $description  The description value.
     * @return CreditTransaction|null Resolved operation result.
     *
     * @since 2.0.0
     */
    public function releaseReservation(GenerationAttempt $attempt, ?string $description = null): ?CreditTransaction
    {
        return DB::transaction(function () use ($attempt, $description): ?CreditTransaction {
            $lockedAttempt = GenerationAttempt::query()->lockForUpdate()->find($attempt->id);

            if (! $lockedAttempt?->credit_transaction_id) {
                return null;
            }

            $reservation = CreditTransaction::query()
                ->lockForUpdate()
                ->find($lockedAttempt->credit_transaction_id);

            if (! $reservation || $reservation->reservation_status !== 'reserved') {
                return null;
            }

            $entitlement = SubscriptionServiceEntitlement::query()
                ->lockForUpdate()
                ->findOrFail($reservation->subscription_service_entitlement_id);
            $releasedAmount = abs((int) $reservation->amount);
            $balanceAfter = $this->ledgerBalanceForSubscription($reservation->subscription_id) + $releasedAmount;

            $reservation->forceFill(['reservation_status' => 'released'])->save();

            return $this->createTransaction(
                $entitlement,
                CreditTransactionType::Release,
                $releasedAmount,
                $balanceAfter,
                $lockedAttempt,
                null,
                $description ?: $reservation->description,
                'billing.credit_ledger.release',
                reservationStatus: 'released',
                generationRequestId: $lockedAttempt->generation_request_id,
            );
        });
    }

    /**
     * Record a positive credit transaction without changing entitlement counters.
     *
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @param  int  $amount  The amount value.
     * @param  CreditTransactionType  $type  The type value.
     * @param  Model|null  $related  The related value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @param  string|null  $description  The description value.
     * @param  string  $scope  The scope value.
     * @return CreditTransaction Resolved operation result.
     *
     * @since 1.0.0
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
     *
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @param  CreditTransactionType  $type  The type value.
     * @param  int  $amount  The amount value.
     * @param  int  $balanceAfter  The balance after value.
     * @param  Model|null  $related  The related value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @param  string|null  $description  The description value.
     * @param  string  $scope  The scope value.
     * @param  string|null  $reservationStatus  The reservation status value.
     * @param  int|null  $generationRequestId  The generation request id value.
     * @return CreditTransaction Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
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
        ?string $reservationStatus = null,
        ?int $generationRequestId = null,
    ): CreditTransaction {
        $transaction = CreditTransaction::create([
            'tenant_id' => $entitlement->tenant_id,
            'subscription_id' => $entitlement->subscription_id,
            'subscription_service_entitlement_id' => $entitlement->id,
            'service_key' => $entitlement->service_key,
            'type' => $type->value,
            'reservation_status' => $reservationStatus,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'generation_request_id' => $generationRequestId,
            'description' => $description,
            'created_by' => $actor?->id,
        ]);

        return $transaction;
    }

    /**
     * Ensure ledger baseline.
     *
     * @param  Subscription  $subscription  The subscription value.
     * @param  SubscriptionServiceEntitlement  $entitlement  The entitlement value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function ensureLedgerBaseline(
        Subscription $subscription,
        SubscriptionServiceEntitlement $entitlement,
    ): void {
        if (CreditTransaction::query()->where('subscription_id', $subscription->id)->exists()) {
            return;
        }

        $balance = max(0, (int) $subscription->serviceEntitlements()
            ->sum(DB::raw('included_credit + purchased_credit - used_credit')));

        if ($balance === 0) {
            return;
        }

        $this->createTransaction(
            $entitlement,
            CreditTransactionType::Grant,
            $balance,
            $balance,
            $subscription,
            null,
            __('credits.descriptions.legacy_balance'),
            'billing.credit_ledger.baseline',
        );
    }

    /**
     * Execute the ledger balance for subscription operation.
     *
     * @param  int  $subscriptionId  The subscription id value.
     * @param  bool  $fallbackToEntitlements  The fallback to entitlements value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
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

    /**
     * Execute the ledger balance for tenant operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
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
     * @param  Subscription|null  $subscription  The subscription value.
     * @return Collection<int, array{date: string, type: string, amount: int, balance_after: int|null, description: string|null, created_at: mixed}>
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function timelineForSubscription(?Subscription $subscription): Collection
    {
        if (! $subscription) {
            return collect();
        }

        $positiveRows = $subscription->creditTransactions()
            ->where('amount', '>', 0)
            ->whereIn('type', [
                CreditTransactionType::Grant->value,
                CreditTransactionType::TopUp->value,
                CreditTransactionType::Adjustment->value,
                CreditTransactionType::Refund->value,
            ])
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
            ->where('type', CreditTransactionType::Use->value)
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
