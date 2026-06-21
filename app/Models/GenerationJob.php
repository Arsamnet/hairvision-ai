<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One provider job for a generation group period.
 *
 * @since 1.0.0
 */
class GenerationJob extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'generation_group_id',
        'tenant_id',
        'lead_id',
        'upload_id',
        'provider_id',
        'period',
        'model_name',
        'prompt',
        'status',
        'provider_job_id',
        'next_poll_at',
        'attempts',
        'max_attempts',
        'last_error',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'next_poll_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GenerationJob $job) {
            $job->max_attempts = $job->max_attempts ?? 3;
        });
    }

    /**
     * Generation group that owns this period job.
     *
     * @return BelongsTo<GenerationGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GenerationGroup::class, 'generation_group_id');
    }

    /**
     * Tenant that owns this job.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verified lead that owns this job.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Upload used as the generation input.
     *
     * @return BelongsTo<Upload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Generated asset produced by this job.
     *
     * @return HasOne<GeneratedAsset, $this>
     */
    public function asset(): HasOne
    {
        return $this->hasOne(GeneratedAsset::class);
    }
}
