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
        'resume_token_hash',
        'resumable_until',
        'requested_at',
        'completed_at',
        'failed_at',
        'safe_error_code',
        'safe_error_message',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resumable_until' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Define the tenant Eloquent relationship.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 2.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Define the lead Eloquent relationship.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 2.0.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Define the source image Eloquent relationship.
     *
     * @return BelongsTo<LeadSourceImage, $this>
     *
     * @since 2.0.0
     */
    public function sourceImage(): BelongsTo
    {
        return $this->belongsTo(LeadSourceImage::class, 'lead_source_image_id');
    }

    /**
     * Define the category Eloquent relationship.
     *
     * @return BelongsTo<AiModelCategory, $this>
     *
     * @since 2.0.0
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AiModelCategory::class, 'ai_model_category_id');
    }

    /**
     * Define the output Eloquent relationship.
     *
     * @return BelongsTo<AiModelCategoryOutput, $this>
     *
     * @since 2.0.0
     */
    public function output(): BelongsTo
    {
        return $this->belongsTo(AiModelCategoryOutput::class, 'ai_model_category_output_id');
    }

    /**
     * Define the attempts Eloquent relationship.
     *
     * @return HasMany<GenerationAttempt, $this>
     *
     * @since 2.0.0
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(GenerationAttempt::class);
    }

    /**
     * Every generated result version produced for this request.
     *
     * @return HasMany<GeneratedAsset, $this>
     *
     * @since 2.0.0
     */
    public function assets(): HasMany
    {
        return $this->hasMany(GeneratedAsset::class);
    }

    /**
     * Define the current asset Eloquent relationship.
     *
     * @return BelongsTo<GeneratedAsset, $this>
     *
     * @since 2.0.0
     */
    public function currentAsset(): BelongsTo
    {
        return $this->belongsTo(GeneratedAsset::class, 'current_generated_asset_id');
    }
}
