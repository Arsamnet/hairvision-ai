<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * HairVision clinic tenant resolved from request hosts.
 *
 * @since 1.0.0
 */
class Tenant extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = ['name', 'slug', 'status'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Active and historical domains mapped to this tenant.
     *
     * @return HasMany<TenantDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /**
     * Dashboard users that may access this tenant.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Verified leads owned by this tenant.
     *
     * @return HasMany<Lead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Public landing-page theme settings for this tenant.
     *
     * @return HasOne<TenantThemeSetting, $this>
     */
    public function theme(): HasOne
    {
        return $this->hasOne(TenantThemeSetting::class);
    }

    /**
     * AI provider settings available to this tenant.
     *
     * @return HasMany<TenantAiSetting, $this>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(TenantAiSetting::class);
    }

    /**
     * OTP records requested by this tenant's visitors.
     *
     * @return HasMany<OtpCode, $this>
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    /**
     * Anonymous landing-page visits recorded for this tenant.
     *
     * @return HasMany<TenantLandingPageVisit, $this>
     */
    public function landingPageVisits(): HasMany
    {
        return $this->hasMany(TenantLandingPageVisit::class);
    }
}
