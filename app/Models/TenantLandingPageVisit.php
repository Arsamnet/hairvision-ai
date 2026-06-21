<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anonymous tenant landing-page visit for pre-lead analytics.
 *
 * @since 1.0.0
 */
class TenantLandingPageVisit extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'host',
        'ip_hash',
        'user_agent_hash',
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
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
