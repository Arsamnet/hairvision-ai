<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Service credit definition granted by a purchasable plan.
 *
 * @since 1.0.0
 */
class PlanServiceEntitlement extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'plan_id',
        'service_key',
        'credit_amount',
        'is_active',
    ];

    protected $casts = [
        'credit_amount' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Plan that grants this service credit definition.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
