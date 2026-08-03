<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-scoped first-party event in one anonymous landing visit funnel.
 *
 * @since 2.1.0
 */
class TenantLandingEvent extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'tenant_landing_page_visit_id',
        'lead_id',
        'ai_model_category_id',
        'ai_model_category_output_id',
        'event_id',
        'event_type',
        'category_code',
        'category_name',
        'output_code',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns the event.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 2.1.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Anonymous landing visit that contains the event.
     *
     * @return BelongsTo<TenantLandingPageVisit, $this>
     *
     * @since 2.1.0
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(TenantLandingPageVisit::class, 'tenant_landing_page_visit_id');
    }

    /**
     * Verified lead linked to the visit after successful OTP verification.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 2.1.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
