<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Durable registry row for one configured image-worker slot.
 *
 * @since 2.0.0
 */
class ImageWorkerProcess extends Model
{
    protected $fillable = [
        'slot',
        'worker_id',
        'mode',
        'pid',
        'host',
        'status',
        'started_at',
        'heartbeat_at',
        'restart_requested_at',
        'stopped_at',
        'metadata',
    ];

    protected $casts = [
        'slot' => 'integer',
        'pid' => 'integer',
        'started_at' => 'datetime',
        'heartbeat_at' => 'datetime',
        'restart_requested_at' => 'datetime',
        'stopped_at' => 'datetime',
        'metadata' => 'array',
    ];
}
