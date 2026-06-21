<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GenerationGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'upload_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GenerationGroup $group) {
            if (empty($group->uuid)) {
                $group->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function jobs()
    {
        return $this->hasMany(GenerationJob::class);
    }

    public function assets()
    {
        return $this->hasMany(GeneratedAsset::class);
    }
}
