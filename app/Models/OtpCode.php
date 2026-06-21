<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OtpCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'phone_normalized',
        'code_hash',
        'purpose',
        'expires_at',
        'verified_at',
        'attempts',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OtpCode $otp) {
            if (empty($otp->uuid)) {
                $otp->uuid = Str::uuid()->toString();
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
