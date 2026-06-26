<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * System payment gateway configuration with encrypted credentials.
 *
 * @since 1.0.0
 */
class PaymentGateway extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'name',
        'provider_code',
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
     * Scope query to enabled payment gateways.
     *
     * @param  Builder<PaymentGateway>  $query
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'enabled');
    }
}
