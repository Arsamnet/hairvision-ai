<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantAiSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider_id',
        'model_name',
        'prompt_template',
        'settings_json',
        'encrypted_api_key',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (TenantAiSetting $setting) {
            if (empty($setting->uuid)) {
                $setting->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function provider()
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
