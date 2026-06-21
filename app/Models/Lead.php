<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned verified visitor lead.
 *
 * @since 1.0.0
 */
class Lead extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'phone',
        'phone_normalized',
        'phone_verified_at',
        'status',
        'crm_stage',
        'crm_last_note_at',
        'last_seen_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'crm_last_note_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns this lead.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Private uploads submitted by this lead.
     *
     * @return HasMany<Upload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    /**
     * Generation groups created for this lead.
     *
     * @return HasMany<GenerationGroup, $this>
     */
    public function generationGroups(): HasMany
    {
        return $this->hasMany(GenerationGroup::class);
    }

    /**
     * Future admin notes for lead follow-up.
     *
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }
}
