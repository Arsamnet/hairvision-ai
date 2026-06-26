<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Purchasable subscription plan definition managed by system administrators.
 *
 * @since 1.0.0
 */
class Plan extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'duration_days',
        'usage_limit',
        'features_json',
        'status',
    ];

    protected $casts = [
        'features_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Invoices created for purchases of this plan.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
