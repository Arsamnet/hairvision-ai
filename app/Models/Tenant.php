<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->uuid)) {
                $tenant->uuid = Str::uuid()->toString();
            }
        });
    }

    public function domains()
    {
        return $this->hasMany(TenantDomain::class);
    }

    public function theme()
    {
        return $this->hasOne(TenantThemeSetting::class);
    }

    public function providers()
    {
        return $this->hasMany(TenantAiSetting::class);
    }

    public function otpCodes()
    {
        return $this->hasMany(OtpCode::class);
    }

    public function landingPageVisits()
    {
        return $this->hasMany(TenantLandingPageVisit::class);
    }
}
