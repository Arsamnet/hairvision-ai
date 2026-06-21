<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasFactory;

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

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            if (empty($log->uuid)) {
                $log->uuid = Str::uuid()->toString();
            }
        });
    }
}
