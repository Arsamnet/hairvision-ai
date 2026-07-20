<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Database-backed API access record for a configured AI model type.
 *
 * Credentials are encrypted at rest and hidden from serialization. Dashboard
 * code should use masked credential helpers instead of reading secrets.
 *
 * @since 1.0.0
 */
class AiModelAccess extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'model_key',
        'usage_category_key',
        'name',
        'description',
        'credentials',
        'settings',
        'prompt',
        'credit_limit',
        'credit_used',
        'usage_count',
        'is_active',
        'is_demo',
        'is_protected',
        'priority',
        'last_used_at',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'settings' => 'array',
        'credit_limit' => 'integer',
        'credit_used' => 'integer',
        'usage_count' => 'integer',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'is_protected' => 'boolean',
        'priority' => 'integer',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Clinics that may use this model access record.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'clinic_ai_model_access', 'ai_model_access_id', 'clinic_id')
            ->withPivot(['is_active', 'assigned_by'])
            ->withTimestamps();
    }

    /**
     * Purchasable plans that provision this model access.
     *
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_ai_model_access', 'ai_model_access_id', 'plan_id')
            ->withTimestamps();
    }

    /**
     * Executable v2 model synchronized from this access record.
     *
     * @return HasOne<AiModel, $this>
     */
    public function executableModel(): HasOne
    {
        return $this->hasOne(AiModel::class, 'legacy_ai_model_access_id');
    }

    /**
     * Usage log rows for executions through this access record.
     *
     * @return HasMany<AiModelUsageLog, $this>
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiModelUsageLog::class);
    }

    /**
     * User that created the access record, when available.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User that last updated the access record, when available.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope active access records.
     *
     * @param  Builder<AiModelAccess>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Determine whether this record has unlimited credit.
     */
    public function hasUnlimitedCredit(): bool
    {
        return $this->credit_limit === null;
    }

    /**
     * Determine whether at least one execution credit is available.
     */
    public function hasAvailableCredit(int $amount = 1): bool
    {
        return $this->hasUnlimitedCredit()
            || $this->credit_used + $amount <= $this->credit_limit;
    }

    /**
     * Return remaining credit when limited, otherwise null for unlimited.
     */
    public function remainingCredit(): ?int
    {
        if ($this->hasUnlimitedCredit()) {
            return null;
        }

        return max(0, $this->credit_limit - $this->credit_used);
    }

    /**
     * Return masked credential summaries safe for admin UI display.
     *
     * @return array<string, string>
     */
    public function maskedCredentials(): array
    {
        return collect($this->credentials ?? [])
            ->mapWithKeys(function (mixed $value, string $key): array {
                if (! is_string($value) || $value === '') {
                    return [$key => __('messages.none')];
                }

                return [$key => $this->maskCredentialValue($value)];
            })
            ->all();
    }

    /**
     * Mask stored secrets without revealing short API keys.
     */
    private function maskCredentialValue(string $value): string
    {
        $length = Str::length($value);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return Str::substr($value, 0, 4)
            .str_repeat('*', $length - 8)
            .Str::substr($value, -4);
    }
}
