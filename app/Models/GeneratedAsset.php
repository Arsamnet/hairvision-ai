<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GeneratedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'generation_group_id',
        'generation_job_id',
        'tenant_id',
        'lead_id',
        'period',
        'disk',
        'path',
        'mime',
        'size',
        'provider_metadata_json',
    ];

    protected $casts = [
        'provider_metadata_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GeneratedAsset $asset) {
            if (empty($asset->uuid)) {
                $asset->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function generationJob()
    {
        return $this->belongsTo(GenerationJob::class, 'generation_job_id');
    }
}
