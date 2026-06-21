<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'phone',
        'phone_normalized',
        'phone_verified_at',
        'status',
        'crm_stage',
        'crm_last_note_at',
        'last_seen_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'crm_last_note_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if (empty($lead->uuid)) {
                $lead->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploads()
    {
        return $this->hasMany(Upload::class);
    }

    public function generationGroups()
    {
        return $this->hasMany(GenerationGroup::class);
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class);
    }
}
