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
     * Clinic-auth users owned directly by this tenant.
     *
     * @return HasMany<ClinicUser, $this>
     */
    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
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
     * Legacy AI provider settings retained for this tenant's history.
     *
     * @return HasMany<TenantAiSetting, $this>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(TenantAiSetting::class);
    }

    /**
     * AI model access records assigned to this clinic.
     *
     * @return BelongsToMany<AiModelAccess, $this>
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
     */
    public function aiModelUsageLogs(): HasMany
    {
        return $this->hasMany(AiModelUsageLog::class, 'clinic_id');
    }

    /**
     * Active and historical subscriptions owned by this clinic.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Service-level entitlements currently or historically owned by this clinic.
     *
     * @return HasMany<SubscriptionServiceEntitlement, $this>
     */
    public function serviceEntitlements(): HasMany
    {
        return $this->hasMany(SubscriptionServiceEntitlement::class);
    }

    /**
     * Credit ledger entries for this clinic.
     *
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Billing invoices issued for this clinic.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * SMS gateways configured for this clinic.
     *
     * @return HasMany<SmsGateway, $this>
     */
    public function smsGateways(): HasMany
    {
        return $this->hasMany(SmsGateway::class);
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

    /**
     * Notification delivery rows visible in this clinic's dashboard inbox.
     *
     * @return HasMany<ClinicNotificationRecipient, $this>
     */
    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(ClinicNotificationRecipient::class);
    }
}
