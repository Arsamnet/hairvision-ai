<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service-level credit balance attached to a clinic base subscription.
 *
 * @since 1.0.0
 */
class SubscriptionServiceEntitlement extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'plan_id',
        'service_key',
        'source_type',
        'source_id',
        'included_credit',
        'purchased_credit',
        'used_credit',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'included_credit' => 'integer',
        'purchased_credit' => 'integer',
        'used_credit' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Clinic tenant that owns the entitlement.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Base subscription this service belongs to.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Plan that originally granted the service.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Ledger entries for this service entitlement.
     *
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function totalCredit(): int
    {
        return $this->included_credit + $this->purchased_credit;
    }

    public function remainingCredit(): int
    {
        return max(0, $this->totalCredit() - $this->used_credit);
    }
}
