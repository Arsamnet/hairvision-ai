<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Metadata for a private generated result asset.
 *
 * @since 1.0.0
 */
class GeneratedAsset extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'generation_group_id',
        'generation_job_id',
        'tenant_id',
        'lead_id',
        'lead_source_image_id',
        'ai_model_category_id',
        'ai_model_category_output_id',
        'generation_request_id',
        'generation_attempt_id',
        'period',
        'version',
        'is_current',
        'disk',
        'path',
        'mime',
        'size',
        'checksum',
        'archived_at',
        'provider_metadata_json',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_current' => 'boolean',
        'provider_metadata_json' => 'array',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns this generated asset.
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
     * Generation job that produced this asset.
     *
     * @return BelongsTo<GenerationJob, $this>
     *
     * @since 1.0.0
     */
    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(GenerationJob::class, 'generation_job_id');
    }

    /**
     * V2 generation request that produced this asset, when applicable.
     *
     * @return BelongsTo<GenerationRequest, $this>
     *
     * @since 1.0.0
     */
    public function generationRequest(): BelongsTo
    {
        return $this->belongsTo(GenerationRequest::class);
    }

    /**
     * V2 execution attempt that produced this asset, when applicable.
     *
     * @return BelongsTo<GenerationAttempt, $this>
     *
     * @since 2.0.0
     */
    public function generationAttempt(): BelongsTo
    {
        return $this->belongsTo(GenerationAttempt::class);
    }
}
