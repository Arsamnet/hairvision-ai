<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenant-owned verified visitor lead.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class Lead extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'full_name',
        'age',
        'city',
        'phone',
        'phone_normalized',
        'source',
        'phone_verified_at',
        'status',
        'crm_last_note_at',
        'assigned_user_id',
        'last_seen_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'age' => 'integer',
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
     *
     * @since 1.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Private uploads submitted by this lead.
     *
     * @return HasMany<Upload, $this>
     *
     * @since 1.0.0
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    /**
     * Generation groups created for this lead.
     *
     * @return HasMany<GenerationGroup, $this>
     *
     * @since 1.0.0
     */
    public function generationGroups(): HasMany
    {
        return $this->hasMany(GenerationGroup::class);
    }

    /**
     * Version 2 visitor profile owned by this lead.
     *
     * @return HasOne<LeadProfile, $this>
     *
     * @since 1.0.0
     */
    public function profile(): HasOne
    {
        return $this->hasOne(LeadProfile::class);
    }

    /**
     * Versioned private source images uploaded for this lead.
     *
     * @return HasMany<LeadSourceImage, $this>
     *
     * @since 1.0.0
     */
    public function sourceImages(): HasMany
    {
        return $this->hasMany(LeadSourceImage::class);
    }

    /**
     * Independent version 2 generation requests created by this lead.
     *
     * @return HasMany<GenerationRequest, $this>
     *
     * @since 1.0.0
     */
    public function generationRequests(): HasMany
    {
        return $this->hasMany(GenerationRequest::class);
    }

    /**
     * Future admin notes for lead follow-up.
     *
     * @return HasMany<LeadNote, $this>
     *
     * @since 1.0.0
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    /**
     * Most recently recorded CRM note shown in lead-list previews.
     *
     * @return HasOne<LeadNote, $this>
     *
     * @since 2.1.1
     */
    public function latestNote(): HasOne
    {
        return $this->hasOne(LeadNote::class)->latestOfMany();
    }

    /**
     * Dashboard user assigned to follow up with this lead.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
