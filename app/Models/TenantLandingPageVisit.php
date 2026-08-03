<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Anonymous tenant landing-page visit for pre-lead analytics.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class TenantLandingPageVisit extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'host',
        'ip_hash',
        'user_agent_hash',
        'analytics_token_hash',
        'referer',
        'visited_at',
    ];

    protected $casts = [
        'visited_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that received the visit.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 1.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Verified lead eventually associated with this anonymous visit.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 2.1.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * First-party funnel events recorded during this landing visit.
     *
     * @return HasMany<TenantLandingEvent, $this>
     *
     * @since 2.1.0
     */
    public function events(): HasMany
    {
        return $this->hasMany(TenantLandingEvent::class);
    }
}
