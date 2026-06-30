<?php

namespace App\Services\Billing;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains auditable service credit grants and atomic consumption.
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
        return $this->recordPositive($entitlement, $amount, $type, $related, $actor, $description);
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
        return DB::transaction(function () use ($entitlement, $amount, $related, $actor, $description): CreditTransaction {
            $locked = SubscriptionServiceEntitlement::query()->lockForUpdate()->findOrFail($entitlement->id);
            $locked->increment('purchased_credit', $amount);
            $locked->refresh();

            return $this->createTransaction(
                $locked,
                CreditTransactionType::TopUp,
                $amount,
                $locked->remainingCredit(),
                $related,
                $actor,
                $description,
            );
        });
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
        return DB::transaction(function () use ($tenant, $serviceKey, $amount, $related, $actor, $description): CreditTransaction {
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

            if ($entitlement->remainingCredit() < $amount) {
                throw ValidationException::withMessages([
                    'service_access' => __('credits.errors.finished', [
                        'service' => $this->services->label($serviceKey),
                    ]),
                ]);
            }

            $entitlement->increment('used_credit', $amount);
            $entitlement->refresh();

            return $this->createTransaction(
                $entitlement,
                CreditTransactionType::Use,
                -$amount,
                $entitlement->remainingCredit(),
                $related,
                $actor,
                $description,
            );
        });
    }

    private function recordPositive(
        SubscriptionServiceEntitlement $entitlement,
        int $amount,
        CreditTransactionType $type,
        ?Model $related,
        ?User $actor,
        ?string $description,
    ): CreditTransaction {
        return DB::transaction(function () use ($entitlement, $amount, $type, $related, $actor, $description): CreditTransaction {
            $locked = SubscriptionServiceEntitlement::query()->lockForUpdate()->findOrFail($entitlement->id);

            return $this->createTransaction(
                $locked,
                $type,
                $amount,
                $locked->remainingCredit(),
                $related,
                $actor,
                $description,
            );
        });
    }

    private function createTransaction(
        SubscriptionServiceEntitlement $entitlement,
        CreditTransactionType $type,
        int $amount,
        int $balanceAfter,
        ?Model $related,
        ?User $actor,
        ?string $description,
    ): CreditTransaction {
        return CreditTransaction::create([
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
    }
}
