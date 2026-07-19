<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Requestable output inside an AI model category.
 *
 * @since 2.0.0
 */
class AiModelCategoryOutput extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'ai_model_category_id',
        'code',
        'name',
        'description',
        'prompt',
        'sort_order',
        'credit_cost',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'credit_cost' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<AiModelCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AiModelCategory::class, 'ai_model_category_id');
    }

    /**
     * @return HasMany<AiModelOutputConfig, $this>
     */
    public function modelOutputConfigs(): HasMany
    {
        return $this->hasMany(AiModelOutputConfig::class);
    }

    /**
     * @return HasMany<GenerationRequest, $this>
     */
    public function generationRequests(): HasMany
    {
        return $this->hasMany(GenerationRequest::class, 'ai_model_category_output_id');
    }

    /**
     * @param  Builder<AiModelCategoryOutput>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
