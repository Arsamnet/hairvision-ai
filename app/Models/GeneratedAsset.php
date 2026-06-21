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
        'period',
        'disk',
        'path',
        'mime',
        'size',
        'provider_metadata_json',
    ];

    protected $casts = [
        'provider_metadata_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns this generated asset.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Generation job that produced this asset.
     *
     * @return BelongsTo<GenerationJob, $this>
     */
    public function generationJob(): BelongsTo
    {
        return $this->belongsTo(GenerationJob::class, 'generation_job_id');
    }
}
