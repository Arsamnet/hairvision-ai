<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
     *
     * @since 1.0.0
     */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /**
     * Dashboard users that may access this tenant.
     *
     * @return BelongsToMany<User, $this>
     *
     * @since 1.0.0
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Clinic-auth users owned directly by this tenant.
     *
     * @return HasMany<ClinicUser, $this>
     *
     * @since 1.0.0
     */
    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    /**
     * Verified leads owned by this tenant.
     *
     * @return HasMany<Lead, $this>
     *
     * @since 1.0.0
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Public landing-page theme settings for this tenant.
     *
     * @return HasOne<TenantThemeSetting, $this>
     *
     * @since 1.0.0
     */
    public function theme(): HasOne
    {
        return $this->hasOne(TenantThemeSetting::class);
    }

    /**
     * Legacy AI provider settings retained for this tenant's history.
     *
     * @return HasMany<TenantAiSetting, $this>
     *
     * @since 1.0.0
     */
    public function providers(): HasMany
    {
        return $this->hasMany(TenantAiSetting::class);
    }

    /**
     * AI model access records assigned to this clinic.
     *
     * @return BelongsToMany<AiModelAccess, $this>
     *
     * @since 1.0.0
     */
    public function aiModelAccesses(): BelongsToMany
    {
        return $this->belongsToMany(AiModelAccess::class, 'clinic_ai_model_access', 'clinic_id', 'ai_model_access_id')
            ->withPivot(['is_active', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * Safe usage logs recorded for this clinic.
     *
     * @return HasMany<AiModelUsageLog, $this>
     *
     * @since 1.0.0
     */
    public function aiModelUsageLogs(): HasMany
    {
        return $this->hasMany(AiModelUsageLog::class, 'clinic_id');
    }

    /**
     * Active and historical subscriptions owned by this clinic.
     *
     * @return HasMany<Subscription, $this>
     *
     * @since 1.0.0
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Service-level entitlements currently or historically owned by this clinic.
     *
     * @return HasMany<SubscriptionServiceEntitlement, $this>
     *
     * @since 1.0.0
     */
    public function serviceEntitlements(): HasMany
    {
        return $this->hasMany(SubscriptionServiceEntitlement::class);
    }

    /**
     * Credit ledger entries for this clinic.
     *
     * @return HasMany<CreditTransaction, $this>
     *
     * @since 1.0.0
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Billing invoices issued for this clinic.
     *
     * @return HasMany<Invoice, $this>
     *
     * @since 1.0.0
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * SMS gateways configured for this clinic.
     *
     * @return HasMany<SmsGateway, $this>
     *
     * @since 1.0.0
     */
    public function smsGateways(): HasMany
    {
        return $this->hasMany(SmsGateway::class);
    }

    /**
     * OTP records requested by this tenant's visitors.
     *
     * @return HasMany<OtpCode, $this>
     *
     * @since 1.0.0
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    /**
     * Anonymous landing-page visits recorded for this tenant.
     *
     * @return HasMany<TenantLandingPageVisit, $this>
     *
     * @since 1.0.0
     */
    public function landingPageVisits(): HasMany
    {
        return $this->hasMany(TenantLandingPageVisit::class);
    }

    /**
     * Notification delivery rows visible in this clinic's dashboard inbox.
     *
     * @return HasMany<ClinicNotificationRecipient, $this>
     *
     * @since 1.0.0
     */
    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(ClinicNotificationRecipient::class);
    }
}
