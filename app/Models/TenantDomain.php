<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantDomain extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'domain', 'type', 'status', 'ssl_status', 'verified_at'];

    protected $casts = [
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantDomain $domain) {
            if (empty($domain->uuid)) {
                $domain->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
