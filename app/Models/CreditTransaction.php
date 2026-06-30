<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable ledger row for service credit grants, usage, and adjustments.
 *
 * @since 1.0.0
 */
class CreditTransaction extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'subscription_service_entitlement_id',
        'service_key',
        'type',
        'amount',
        'balance_after',
        'related_type',
        'related_id',
        'description',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Clinic tenant that owns the credit movement.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Base subscription affected by the credit movement.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Service entitlement affected by this ledger row.
     *
     * @return BelongsTo<SubscriptionServiceEntitlement, $this>
     */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(SubscriptionServiceEntitlement::class, 'subscription_service_entitlement_id');
    }

    /**
     * Optional domain object that caused the credit movement.
     *
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * User that created the ledger entry, when the action was administrative.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
