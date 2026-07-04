<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DebugStepLogger;
use Illuminate\Database\Eloquent\Model;
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
    private int $debugStepCounter = 0;

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
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Request received', [
            'tenant_id' => $entitlement->tenant_id,
            'subscription_id' => $entitlement->subscription_id,
            'entitlement_id' => $entitlement->id,
            'service_key' => $entitlement->service_key,
            'amount' => $amount,
            'type' => $type->value,
            'actor_id' => $actor?->id,
            'has_related' => $related !== null,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'has_description' => is_string($description) && $description !== '',
            'description_length' => is_string($description) ? mb_strlen($description) : 0,
        ]);

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
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Request received', [
            'tenant_id' => $entitlement->tenant_id,
            'subscription_id' => $entitlement->subscription_id,
            'entitlement_id' => $entitlement->id,
            'service_key' => $entitlement->service_key,
            'amount' => $amount,
            'actor_id' => $actor?->id,
            'has_related' => $related !== null,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'has_description' => is_string($description) && $description !== '',
            'description_length' => is_string($description) ? mb_strlen($description) : 0,
        ]);

        try {
            return DB::transaction(function () use ($entitlement, $amount, $related, $actor, $description, $scope): CreditTransaction {
                $locked = SubscriptionServiceEntitlement::query()
                    ->lockForUpdate()
                    ->findOrFail($entitlement->id);

                $this->debugStep($scope, 'Entitlement locked', [
                    'tenant_id' => $locked->tenant_id,
                    'subscription_id' => $locked->subscription_id,
                    'entitlement_id' => $locked->id,
                    'service_key' => $locked->service_key,
                    'amount' => $amount,
                    'remaining_credit_before' => $locked->remainingCredit(),
                    'used_credit_before' => $locked->used_credit,
                    'purchased_credit_before' => $locked->purchased_credit,
                    'total_credit_before' => $locked->totalCredit(),
                ]);

                $locked->increment('purchased_credit', $amount);
                $locked->refresh();

                $this->debugStep($scope, 'Purchased credit incremented', [
                    'tenant_id' => $locked->tenant_id,
                    'subscription_id' => $locked->subscription_id,
                    'entitlement_id' => $locked->id,
                    'service_key' => $locked->service_key,
                    'amount' => $amount,
                    'remaining_credit_after' => $locked->remainingCredit(),
                    'used_credit_after' => $locked->used_credit,
                    'purchased_credit_after' => $locked->purchased_credit,
                    'total_credit_after' => $locked->totalCredit(),
                ]);

                return $this->createTransaction(
                    $locked,
                    CreditTransactionType::TopUp,
                    $amount,
                    $locked->remainingCredit(),
                    $related,
                    $actor,
                    $description,
                    $scope,
                );
            });
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Top-up transaction failed', [
                'tenant_id' => $entitlement->tenant_id,
                'subscription_id' => $entitlement->subscription_id,
                'entitlement_id' => $entitlement->id,
                'service_key' => $entitlement->service_key,
                'amount' => $amount,
                'exception' => $exception::class,
            ]);

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
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Request received', [
            'tenant_id' => $tenant->id,
            'service_key' => $serviceKey,
            'amount' => $amount,
            'actor_id' => $actor?->id,
            'has_related' => $related !== null,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'has_description' => is_string($description) && $description !== '',
            'description_length' => is_string($description) ? mb_strlen($description) : 0,
        ]);

        try {
            return DB::transaction(function () use ($tenant, $serviceKey, $amount, $related, $actor, $description, $scope): CreditTransaction {
                $subscription = $this->entitlements->activeBaseSubscriptionForTenant($tenant, lock: true);

                if (! $subscription) {
                    $this->debugStep($scope, 'Consumption blocked because subscription is missing', [
                        'tenant_id' => $tenant->id,
                        'service_key' => $serviceKey,
                        'amount' => $amount,
                        'status' => 'subscription_required',
                    ]);

                    throw ValidationException::withMessages([
                        'service_access' => __('subscriptions.errors.required'),
                    ]);
                }

                $this->debugStep($scope, 'Subscription locked', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'plan_id' => $subscription->plan_id,
                    'service_key' => $serviceKey,
                    'amount' => $amount,
                ]);

                $entitlement = $this->entitlements->activeEntitlement($tenant, $serviceKey, lock: true);

                if (! $entitlement) {
                    $this->debugStep($scope, 'Consumption blocked because entitlement is missing', [
                        'tenant_id' => $tenant->id,
                        'subscription_id' => $subscription->id,
                        'service_key' => $serviceKey,
                        'amount' => $amount,
                        'status' => 'service_unavailable',
                    ]);

                    throw ValidationException::withMessages([
                        'service_access' => __('subscriptions.errors.service_unavailable', [
                            'service' => $this->services->label($serviceKey),
                        ]),
                    ]);
                }

                $remainingBefore = $entitlement->remainingCredit();

                $this->debugStep($scope, 'Entitlement locked', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'entitlement_id' => $entitlement->id,
                    'service_key' => $serviceKey,
                    'amount' => $amount,
                    'remaining_credit_before' => $remainingBefore,
                    'used_credit_before' => $entitlement->used_credit,
                    'total_credit_before' => $entitlement->totalCredit(),
                ]);

                if ($remainingBefore < $amount) {
                    $this->debugStep($scope, 'Consumption blocked because credit is finished', [
                        'tenant_id' => $tenant->id,
                        'subscription_id' => $subscription->id,
                        'entitlement_id' => $entitlement->id,
                        'service_key' => $serviceKey,
                        'amount' => $amount,
                        'remaining_credit_before' => $remainingBefore,
                        'status' => 'credit_finished',
                    ]);

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

                $this->debugStep($scope, 'Used credit incremented', [
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'entitlement_id' => $entitlement->id,
                    'service_key' => $serviceKey,
                    'amount' => $amount,
                    'remaining_credit_after' => $entitlement->remainingCredit(),
                    'used_credit_after' => $entitlement->used_credit,
                    'total_credit_after' => $entitlement->totalCredit(),
                ]);

                return $this->createTransaction(
                    $entitlement,
                    CreditTransactionType::Use,
                    -$amount,
                    $entitlement->remainingCredit(),
                    $related,
                    $actor,
                    $description,
                    $scope,
                );
            });
        } catch (ValidationException $exception) {
            $this->debugStep($scope, 'Consumption validation failed', [
                'tenant_id' => $tenant->id,
                'service_key' => $serviceKey,
                'amount' => $amount,
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Consumption transaction failed', [
                'tenant_id' => $tenant->id,
                'service_key' => $serviceKey,
                'amount' => $amount,
                'exception' => $exception::class,
            ]);

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

                $this->debugStep($scope, 'Entitlement locked', [
                    'tenant_id' => $locked->tenant_id,
                    'subscription_id' => $locked->subscription_id,
                    'entitlement_id' => $locked->id,
                    'service_key' => $locked->service_key,
                    'type' => $type->value,
                    'amount' => $amount,
                    'remaining_credit' => $locked->remainingCredit(),
                    'used_credit' => $locked->used_credit,
                    'total_credit' => $locked->totalCredit(),
                ]);

                return $this->createTransaction(
                    $locked,
                    $type,
                    $amount,
                    $locked->remainingCredit(),
                    $related,
                    $actor,
                    $description,
                    $scope,
                );
            });
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Positive credit transaction failed', [
                'tenant_id' => $entitlement->tenant_id,
                'subscription_id' => $entitlement->subscription_id,
                'entitlement_id' => $entitlement->id,
                'service_key' => $entitlement->service_key,
                'type' => $type->value,
                'amount' => $amount,
                'exception' => $exception::class,
            ]);

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
        $this->debugStep($scope, 'Creating credit transaction', [
            'tenant_id' => $entitlement->tenant_id,
            'subscription_id' => $entitlement->subscription_id,
            'entitlement_id' => $entitlement->id,
            'service_key' => $entitlement->service_key,
            'type' => $type->value,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'actor_id' => $actor?->id,
            'has_related' => $related !== null,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'has_description' => is_string($description) && $description !== '',
            'description_length' => is_string($description) ? mb_strlen($description) : 0,
        ]);

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

        $this->debugStep($scope, 'Credit transaction created', [
            'credit_transaction_id' => $transaction->id,
            'tenant_id' => $transaction->tenant_id,
            'subscription_id' => $transaction->subscription_id,
            'entitlement_id' => $transaction->subscription_service_entitlement_id,
            'service_key' => $transaction->service_key,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
        ]);

        return $transaction;
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}