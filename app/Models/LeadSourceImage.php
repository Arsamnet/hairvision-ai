<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned private source image for one tenant lead profile.
 *
 * @since 2.0.0
 */
class LeadSourceImage extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'lead_profile_id',
        'upload_id',
        'version',
        'is_current',
        'replaced_at',
        'disk',
        'path',
        'mime',
        'size',
        'checksum',
        'status',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_current' => 'boolean',
        'replaced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Define the profile Eloquent relationship.
     *
     * @return BelongsTo<LeadProfile, $this>
     *
     * @since 2.0.0
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(LeadProfile::class, 'lead_profile_id');
    }

    /**
     * Define the generation requests Eloquent relationship.
     *
     * @return HasMany<GenerationRequest, $this>
     *
     * @since 2.0.0
     */
    public function generationRequests(): HasMany
    {
        return $this->hasMany(GenerationRequest::class);
    }
}
