<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Private visitor upload metadata scoped by tenant and lead.
 *
 * @since 1.0.0
 */
class Upload extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'original_disk',
        'original_path',
        'mime',
        'size',
        'width',
        'height',
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Tenant that owns this upload.
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
     * Verified lead that submitted this upload.
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
     * Generation group created from this upload.
     *
     * @return HasOne<GenerationGroup, $this>
     *
     * @since 1.0.0
     */
    public function generationGroup(): HasOne
    {
        return $this->hasOne(GenerationGroup::class);
    }
}
