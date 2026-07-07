<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * SMS gateway configuration for platform messages or a user's own customers.
 *
 * @since 1.0.0
 */
class SmsGateway extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'provider_code',
        'owner_scope',
        'status',
        'is_default',
        'encrypted_credentials',
        'settings_json',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'encrypted_credentials' => 'encrypted:array',
        'settings_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User that owns a private gateway, when owner scope is `user`.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clinic that owns a tenant-scoped gateway.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope query to system-level gateways.
     *
     * @param  Builder<SmsGateway>  $query
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('owner_scope', 'system')->whereNull('user_id');
    }

    /**
     * Scope query to enabled gateway rows.
     *
     * @param  Builder<SmsGateway>  $query
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'enabled');
    }

    /**
     * Return a safe masked credential value for dashboard display.
     */
    public function maskedCredential(string $key): ?string
    {
        $value = data_get($this->encrypted_credentials ?? [], $key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (Str::length($value) <= 8) {
            return Str::mask($value, '*', 1);
        }

        return Str::substr($value, 0, 4).'...'.Str::substr($value, -4);
    }
}
