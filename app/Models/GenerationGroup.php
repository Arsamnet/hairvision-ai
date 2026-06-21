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
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Period jobs in this group.
     *
     * @return HasMany<GenerationJob, $this>
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(GenerationJob::class);
    }

    /**
     * Generated assets produced for this group.
     *
     * @return HasMany<GeneratedAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(GeneratedAsset::class);
    }
}
