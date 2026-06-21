<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog entry for an image-generation provider.
 *
 * @since 1.0.0
 */
class AiProvider extends Model
{
    use HasFactory, HasUuid;

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
}
