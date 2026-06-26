<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Database-backed setting editable by authorized system managers.
 *
 * @since 1.0.0
 */
class SystemSetting extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'key',
        'group',
        'label',
        'type',
        'value_json',
        'encrypted_value',
        'description',
        'is_encrypted',
    ];

    protected $hidden = [
        'encrypted_value',
    ];

    protected $casts = [
        'value_json' => 'array',
        'encrypted_value' => 'encrypted',
        'is_encrypted' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
