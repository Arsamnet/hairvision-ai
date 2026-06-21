<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-specific AI provider configuration and encrypted secret holder.
 *
 * @since 1.0.0
 */
class TenantAiSetting extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'provider_id',
        'model_name',
        'prompt_template',
        'settings_json',
        'encrypted_api_key',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns this provider setting.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Provider catalog row selected by this setting.
     *
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
