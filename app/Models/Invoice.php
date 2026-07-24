<?php

namespace App\Models;

use App\Casts\JalaliDateTimeCast;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * User-visible billing document connected to plan purchases and payments.
 *
 * @since 1.0.0
 */
class Invoice extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'plan_id',
        'number',
        'status',
        'amount',
        'currency',
        'due_at',
        'paid_at',
        'expires_at',
        'activated_at',
        'metadata_json',
    ];

    protected $casts = [
        'amount' => 'integer',
        'due_at' => JalaliDateTimeCast::class,
        'paid_at' => JalaliDateTimeCast::class,
        'expires_at' => JalaliDateTimeCast::class,
        'activated_at' => JalaliDateTimeCast::class,
        'metadata_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Clinic tenant connected to this billing document.
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
     * User that owns and may pay this invoice.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Plan purchased by this invoice, if any.
     *
     * @return BelongsTo<Plan, $this>
     *
     * @since 1.0.0
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Payments attempted for this invoice.
     *
     * @return HasMany<Payment, $this>
     *
     * @since 1.0.0
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Return the purchased plan-model snapshot, or null for legacy invoices.
     *
     * @return array<int, int>|null
     *
     * @since 1.0.0
     */
    public function aiModelAccessIdsSnapshot(): ?array
    {
        $metadata = $this->metadata_json ?? [];

        if (! array_key_exists('ai_model_access_ids', $metadata)) {
            return null;
        }

        return collect($metadata['ai_model_access_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
