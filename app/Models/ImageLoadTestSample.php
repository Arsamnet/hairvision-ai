<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ephemeral metric row produced by the opt-in image queue load test.
 *
 * @since 2.0.0
 */
class ImageLoadTestSample extends Model
{
    protected $fillable = [
        'run_id',
        'batch_size',
        'sequence',
        'status',
        'queued_at',
        'started_at',
        'completed_at',
        'queue_wait_ms',
        'provider_latency_ms',
        'memory_bytes',
        'worker_pid',
    ];

    protected $casts = [
        'batch_size' => 'integer',
        'sequence' => 'integer',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'queue_wait_ms' => 'integer',
        'provider_latency_ms' => 'integer',
        'memory_bytes' => 'integer',
        'worker_pid' => 'integer',
    ];
}
