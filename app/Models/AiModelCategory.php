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
     * Define the outputs Eloquent relationship.
     *
     * @return HasMany<AiModelCategoryOutput, $this>
     *
     * @since 2.0.0
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(AiModelCategoryOutput::class);
    }

    /**
     * Define the models Eloquent relationship.
     *
     * @return HasMany<AiModel, $this>
     *
     * @since 2.0.0
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * Constrain the query by active.
     *
     * @param  Builder<AiModelCategory>  $query
     * @return Builder Configured query builder.
     *
     * @since 2.0.0
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Set icon attribute.
     *
     * @param  mixed  $value  Input value supplied to the operation.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function setIconAttribute(mixed $value): void
    {
        $this->attributes['icon'] = AdminIcon::normalizeForStorage($value);
    }

    /**
     * Resolve d icon.
     *
     * @return string Resolved string value.
     *
     * @since 2.0.0
     */
    public function resolvedIcon(): string
    {
        return AdminIcon::normalize($this->icon, AdminIcon::DEFAULT_CATEGORY_ICON);
    }

    /**
     * Execute the card image url operation.
     *
     * @return string|null Resolved operation result.
     *
     * @since 2.0.0
     */
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
