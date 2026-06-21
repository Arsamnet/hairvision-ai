<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GenerationJob extends Model
{
    use HasFactory;

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
            if (empty($job->uuid)) {
                $job->uuid = Str::uuid()->toString();
            }

            $job->max_attempts = $job->max_attempts ?? 3;
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GenerationGroup::class, 'generation_group_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function asset()
    {
        return $this->hasOne(GeneratedAsset::class);
    }
}
