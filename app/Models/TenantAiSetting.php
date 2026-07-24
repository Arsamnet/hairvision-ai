<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Legacy tenant provider configuration retained for historical data only.
 *
 * Runtime model selection uses active clinic AI model assignments instead.
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
     *
     * @since 1.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Legacy provider catalog row referenced by this setting.
     *
     * @return BelongsTo<AiProvider, $this>
     *
     * @since 1.0.0
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
