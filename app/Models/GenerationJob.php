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

    public const STATUSES = ['pending', 'queued', 'processing', 'completed', 'retrying', 'failed', 'cancelled'];

    public const TERMINAL_STATUSES = ['completed', 'failed', 'cancelled'];

    protected $fillable = [
        'generation_group_id',
        'tenant_id',
        'lead_id',
        'upload_id',
        'provider_id',
        'ai_model_access_id',
        'period',
        'model_name',
        'prompt',
        'status',
        'queued_at',
        'dispatched_at',
        'provider_job_id',
        'next_poll_at',
        'attempts',
        'max_attempts',
        'last_error',
        'error_code',
        'started_at',
        'provider_call_started_at',
        'provider_call_finished_at',
        'queue_wait_ms',
        'provider_latency_ms',
        'worker_id',
        'completed_at',
    ];

    protected $casts = [
        'next_poll_at' => 'datetime',
        'queued_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'provider_call_started_at' => 'datetime',
        'provider_call_finished_at' => 'datetime',
        'queue_wait_ms' => 'integer',
        'provider_latency_ms' => 'integer',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Register model lifecycle behavior.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
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
     *
     * @since 1.0.0
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GenerationGroup::class, 'generation_group_id');
    }

    /**
     * Tenant that owns this job.
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
     * Verified lead that owns this job.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 1.0.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Upload used as the generation input.
     *
     * @return BelongsTo<Upload, $this>
     *
     * @since 1.0.0
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Generated asset produced by this job.
     *
     * @return HasOne<GeneratedAsset, $this>
     *
     * @since 1.0.0
     */
    public function asset(): HasOne
    {
        return $this->hasOne(GeneratedAsset::class);
    }

    /**
     * AI model access record selected for this execution.
     *
     * @return BelongsTo<AiModelAccess, $this>
     *
     * @since 1.0.0
     */
    public function aiModelAccess(): BelongsTo
    {
        return $this->belongsTo(AiModelAccess::class);
    }
}
