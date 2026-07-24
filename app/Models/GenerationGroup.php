<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groups the three generation period jobs created for one upload.
 *
 * @since 1.0.0
 */
class GenerationGroup extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'upload_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns this generation group.
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
     * Verified lead that owns this generation group.
     *
     * @return BelongsTo<Lead, $this>
     *
     * @since 1.0.0
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Upload used as the reference input.
     *
     * @return BelongsTo<Upload, $this>
     *
     * @since 1.0.0
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    /**
     * Period jobs in this group.
     *
     * @return HasMany<GenerationJob, $this>
     *
     * @since 1.0.0
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(GenerationJob::class);
    }

    /**
     * Generated assets produced for this group.
     *
     * @return HasMany<GeneratedAsset, $this>
     *
     * @since 1.0.0
     */
    public function assets(): HasMany
    {
        return $this->hasMany(GeneratedAsset::class);
    }
}
