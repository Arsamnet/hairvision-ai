<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Upload extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'original_disk',
        'original_path',
        'mime',
        'size',
        'width',
        'height',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Upload $upload) {
            if (empty($upload->uuid)) {
                $upload->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function generationGroup()
    {
        return $this->hasOne(GenerationGroup::class);
    }
}
