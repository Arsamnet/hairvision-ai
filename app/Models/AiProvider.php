<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'base_url',
        'auth_type',
        'is_active',
        'supports_async',
        'default_model',
        'settings_schema_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supports_async' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiProvider $provider) {
            if (empty($provider->uuid)) {
                $provider->uuid = Str::uuid()->toString();
            }
        });
    }
}
