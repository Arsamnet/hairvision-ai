<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Persistent public request for one category output from one source image.
 *
 * @since 2.0.0
 */
class GenerationRequest extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'lead_source_image_id',
        'ai_model_category_id',
        'ai_model_category_output_id',
        'current_generated_asset_id',
        'credit_transaction_id',
        'status',
        'idempotency_key',
        'requested_at',
        'completed_at',
        'failed_at',
        'safe_error_code',
        'safe_error_message',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<LeadSourceImage, $this>
     */
    public function sourceImage(): BelongsTo
    {
        return $this->belongsTo(LeadSourceImage::class, 'lead_source_image_id');
    }

    /**
     * @return BelongsTo<AiModelCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AiModelCategory::class, 'ai_model_category_id');
    }

    /**
     * @return BelongsTo<AiModelCategoryOutput, $this>
     */
    public function output(): BelongsTo
    {
        return $this->belongsTo(AiModelCategoryOutput::class, 'ai_model_category_output_id');
    }

    /**
     * @return HasMany<GenerationAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(GenerationAttempt::class);
    }

    /**
     * @return BelongsTo<GeneratedAsset, $this>
     */
    public function currentAsset(): BelongsTo
    {
        return $this->belongsTo(GeneratedAsset::class, 'current_generated_asset_id');
    }
}
