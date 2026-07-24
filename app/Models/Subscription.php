<?php

namespace App\Models;

use App\Casts\JalaliDateTimeCast;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Active or historical user plan entitlement.
 *
 * @since 1.0.0
 */
class Subscription extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'plan_id',
        'invoice_id',
        'status',
        'starts_at',
        'ends_at',
        'usage_limit',
        'usage_used',
    ];

    protected $casts = [
        'starts_at' => JalaliDateTimeCast::class,
        'ends_at' => JalaliDateTimeCast::class,
        'usage_limit' => 'integer',
        'usage_used' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Clinic tenant that owns this entitlement.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 1.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * User that owns this entitlement.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Plan definition that created this entitlement.
     *
     * @return BelongsTo<Plan, $this>
     *
     * @since 1.0.0
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Paid invoice that activated this entitlement.
     *
     * @return BelongsTo<Invoice, $this>
     *
     * @since 1.0.0
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Service entitlements attached to this base subscription.
     *
     * @return HasMany<SubscriptionServiceEntitlement, $this>
     *
     * @since 1.0.0
     */
    public function serviceEntitlements(): HasMany
    {
        return $this->hasMany(SubscriptionServiceEntitlement::class);
    }

    /**
     * Credit ledger entries recorded for this subscription.
     *
     * @return HasMany<CreditTransaction, $this>
     *
     * @since 1.0.0
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
