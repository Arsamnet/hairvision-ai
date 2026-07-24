<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Tenant-scoped public profile for a verified lead.
 *
 * @since 2.0.0
 */
class LeadProfile extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'lead_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Define the tenant Eloquent relationship.
     *
     * @return BelongsTo<Tenant, $this>
     *
     * @since 2.0.0
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Define the lead Eloquent relationship.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 2.0.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Define the source images Eloquent relationship.
     *
     * @return HasMany<LeadSourceImage, $this>
     *
     * @since 2.0.0
     */
    public function sourceImages(): HasMany
    {
        return $this->hasMany(LeadSourceImage::class);
    }

    /**
     * Define the current source image Eloquent relationship.
     *
     * @return HasOne<LeadSourceImage, $this>
     *
     * @since 2.0.0
     */
    public function currentSourceImage(): HasOne
    {
        return $this->hasOne(LeadSourceImage::class)->where('is_current', true)->latestOfMany();
    }
}
