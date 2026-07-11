<?php

namespace App\Models;

use App\Enums\PlanType;
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

    protected $attributes = [
        'plan_type' => 'base_subscription',
        'service_key' => 'hair_transplant',
        'usage_limit' => 0,
        'is_public' => true,
        'status' => 'active',
    ];

    protected $fillable = [
        'name',
        'description',
        'plan_type',
        'service_key',
        'price',
        'currency',
        'duration_days',
        'usage_limit',
        'features_json',
        'is_public',
        'status',
    ];

    protected $casts = [
        'plan_type' => PlanType::class,
        'features_json' => 'array',
        'duration_days' => 'integer',
        'usage_limit' => 'integer',
        'is_public' => 'boolean',
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

    /**
     * Service credits granted by this plan.
     *
     * @return HasMany<PlanServiceEntitlement, $this>
     */
    public function serviceEntitlements(): HasMany
    {
        return $this->hasMany(PlanServiceEntitlement::class);
    }

    public function isBaseSubscription(): bool
    {
        return $this->plan_type === PlanType::BaseSubscription;
    }

    public function isServiceAddOn(): bool
    {
        return $this->plan_type === PlanType::ServiceAddOn;
    }

    public function isCreditTopUp(): bool
    {
        return $this->plan_type === PlanType::CreditTopUp;
    }

    /**
     * Return service credit definitions, falling back to legacy usage_limit.
     *
     * @return array<string, int>
     */
    public function serviceCredits(): array
    {
        $credits = $this->serviceEntitlements
            ->where('is_active', true)
            ->mapWithKeys(fn (PlanServiceEntitlement $entitlement): array => [
                $entitlement->service_key => $entitlement->credit_amount,
            ])
            ->all();

        if ($credits !== []) {
            return $credits;
        }

        $serviceKey = $this->service_key ?: 'hair_transplant';

        return [(string) $serviceKey => (int) $this->usage_limit];
    }
}
