<?php

namespace App\Models;

use App\Enums\SmsProviderScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stores an encrypted system or user-owned SMS provider connection.
 *
 * @since 1.2.0
 */
#[Fillable([
    'sms_provider_type_id',
    'owner_user_id',
    'scope',
    'name',
    'credentials',
    'settings',
    'sender_number',
    'priority',
    'is_active',
    'is_default',
    'last_used_at',
])]
class SmsProviderAccount extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SmsProviderScope::class,
            'credentials' => 'encrypted:array',
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Vendor type for this configured provider account.
     *
     * @return BelongsTo<SmsProviderType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(SmsProviderType::class, 'sms_provider_type_id');
    }

    /**
     * User that owns a private provider account.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Delivery logs sent through this account.
     *
     * @return HasMany<SmsDeliveryLog, $this>
     */
    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(SmsDeliveryLog::class);
    }

    /**
     * Determine whether this account is owned by the platform.
     */
    public function isSystemScoped(): bool
    {
        return $this->scope === SmsProviderScope::System;
    }

    /**
     * Determine whether a user can send through this account.
     */
    public function isAccessibleBy(User $user): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->scope === SmsProviderScope::System) {
            return true;
        }

        return $this->owner_user_id === $user->id;
    }

    /**
     * Return masked credential names for safe dashboard display.
     *
     * @return array<string, string>
     */
    public function maskedCredentials(): array
    {
        return collect($this->credentials ?? [])
            ->keys()
            ->mapWithKeys(fn (string $key): array => [$key => '••••••••'])
            ->all();
    }
}
