<?php

namespace App\Models;

use App\Enums\PlanType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
     * Get and set the plan price without changing its numeric value.
     *
     * Large unsigned BIGINT values are stored as numeric strings so PDO does
     * not bind them as platform-sized integers. Eloquent callers still read
     * the price as a PHP integer.
     *
     * @return Attribute<int, string>
     *
     * @since 2.0.3
     */
    protected function price(): Attribute
    {
        return Attribute::make(
            get: static fn (mixed $value): int => (int) $value,
            set: static fn (mixed $value): string => (string) $value,
        );
    }

    /**
     * Invoices created for purchases of this plan.
     *
     * @return HasMany<Invoice, $this>
     *
     * @since 1.0.0
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Service credits granted by this plan.
     *
     * @return HasMany<PlanServiceEntitlement, $this>
     *
     * @since 1.0.0
     */
    public function serviceEntitlements(): HasMany
    {
        return $this->hasMany(PlanServiceEntitlement::class);
    }

    /**
     * AI model accesses that must be provisioned when this plan is activated.
     *
     * @return BelongsToMany<AiModelAccess, $this>
     *
     * @since 1.0.0
     */
    public function aiModelAccesses(): BelongsToMany
    {
        return $this->belongsToMany(AiModelAccess::class, 'plan_ai_model_access', 'plan_id', 'ai_model_access_id')
            ->withTimestamps();
    }

    /**
     * Determine whether base subscription.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isBaseSubscription(): bool
    {
        return $this->plan_type === PlanType::BaseSubscription;
    }

    /**
     * Determine whether service add on.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isServiceAddOn(): bool
    {
        return $this->plan_type === PlanType::ServiceAddOn;
    }

    /**
     * Determine whether credit top up.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isCreditTopUp(): bool
    {
        return $this->plan_type === PlanType::CreditTopUp;
    }

    /**
     * Return service credit definitions, falling back to legacy usage_limit.
     *
     * @return array<string, int>
     *
     * @since 1.0.0
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

    /**
     * Return usage category keys unlocked by this plan.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    public function serviceKeys(): array
    {
        $keys = $this->serviceEntitlements
            ->where('is_active', true)
            ->pluck('service_key')
            ->map(fn (mixed $key): string => (string) $key)
            ->values()
            ->all();

        if ($keys !== []) {
            return $keys;
        }

        return [(string) ($this->service_key ?: 'hair_transplant')];
    }
}
