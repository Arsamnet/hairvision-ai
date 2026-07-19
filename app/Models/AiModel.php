<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Executable AI model instance for one category and one configured type.
 *
 * @since 2.0.0
 */
class AiModel extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'ai_model_category_id',
        'type',
        'name',
        'status',
        'priority',
        'concurrency_limit',
        'settings',
        'credentials',
        'health_status',
        'consecutive_failures',
        'last_health_check_at',
        'last_assigned_at',
        'paused_at',
        'archived_at',
        'legacy_ai_model_access_id',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $casts = [
        'priority' => 'integer',
        'concurrency_limit' => 'integer',
        'settings' => 'array',
        'credentials' => 'encrypted:array',
        'consecutive_failures' => 'integer',
        'last_health_check_at' => 'datetime',
        'last_assigned_at' => 'datetime',
        'paused_at' => 'datetime',
        'archived_at' => 'datetime',
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
     * @return BelongsTo<AiModelAccess, $this>
     */
    public function legacyAiModelAccess(): BelongsTo
    {
        return $this->belongsTo(AiModelAccess::class, 'legacy_ai_model_access_id');
    }

    /**
     * @return HasMany<AiModelOutputConfig, $this>
     */
    public function outputConfigs(): HasMany
    {
        return $this->hasMany(AiModelOutputConfig::class);
    }

    /**
     * @return HasMany<GenerationAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(GenerationAttempt::class);
    }

    /**
     * @param  Builder<AiModel>  $query
     */
    public function scopeAssignable(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereIn('health_status', ['healthy', 'unknown'])
            ->whereNull('archived_at')
            ->whereNull('paused_at');
    }

    /**
     * @return array<string, string>
     */
    public function maskedCredentials(): array
    {
        return collect($this->credentials ?? [])
            ->mapWithKeys(fn (mixed $value, string $key): array => [$key => $this->maskSecret((string) $value)])
            ->all();
    }

    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return __('messages.none');
        }

        $length = Str::length($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return Str::substr($value, 0, 4).str_repeat('*', $length - 8).Str::substr($value, -4);
    }
}
