<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-output configuration for an executable AI model instance.
 *
 * @since 2.0.0
 */
class AiModelOutputConfig extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'ai_model_id',
        'ai_model_category_output_id',
        'is_active',
        'configuration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'configuration' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Define the model Eloquent relationship.
     *
     * @return BelongsTo<AiModel, $this>
     *
     * @since 2.0.0
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    /**
     * Define the output Eloquent relationship.
     *
     * @return BelongsTo<AiModelCategoryOutput, $this>
     *
     * @since 2.0.0
     */
    public function output(): BelongsTo
    {
        return $this->belongsTo(AiModelCategoryOutput::class, 'ai_model_category_output_id');
    }
}
