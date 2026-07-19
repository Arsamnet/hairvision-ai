<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\AdminIcon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * System-owned product category for AI image generation services.
 *
 * @since 2.0.0
 */
class AiModelCategory extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'sort_order',
        'icon',
        'card_image_path',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return HasMany<AiModelCategoryOutput, $this>
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(AiModelCategoryOutput::class);
    }

    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * @param  Builder<AiModelCategory>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function setIconAttribute(mixed $value): void
    {
        $this->attributes['icon'] = AdminIcon::normalizeForStorage($value);
    }

    public function resolvedIcon(): string
    {
        return AdminIcon::normalize($this->icon, AdminIcon::DEFAULT_CATEGORY_ICON);
    }

    public function cardImageUrl(): ?string
    {
        $path = trim((string) $this->card_image_path);

        if ($path === '' || str_starts_with($path, '//')) {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return $path;
        }

        return null;
    }
}
