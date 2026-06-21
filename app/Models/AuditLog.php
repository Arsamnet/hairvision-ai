<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped audit record reserved for future dashboard activity tracking.
 *
 * @since 1.0.0
 */
class AuditLog extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'lead_id',
        'action',
        'subject_type',
        'subject_id',
        'ip_address',
        'user_agent',
        'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'created_at' => 'datetime',
    ];
}
