<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantLandingPageVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'host',
        'ip_hash',
        'user_agent_hash',
        'referer',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantLandingPageVisit $visit) {
            if (empty($visit->uuid)) {
                $visit->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
