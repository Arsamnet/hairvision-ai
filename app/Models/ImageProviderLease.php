<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One expiring slot in the application-wide image-provider semaphore.
 *
 * @since 2.0.0
 */
class ImageProviderLease extends Model
{
    protected $fillable = [
        'lease_id',
        'owner_type',
        'owner_id',
        'worker_id',
        'acquired_at',
        'expires_at',
    ];

    protected $casts = [
        'owner_id' => 'integer',
        'acquired_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
