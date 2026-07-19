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
 * System-created message delivered to one or more clinic tenants.
 *
 * @since 1.0.0
 */
class ClinicNotification extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'title',
        'body',
        'body_html',
        'type',
        'importance',
        'status',
        'target_scope',
        'targeting_rules',
        'visible_from',
        'visible_until',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'targeting_rules' => 'array',
        'visible_from' => 'datetime',
        'visible_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Clinic delivery rows for this message.
     *
     * @return HasMany<ClinicNotificationRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(ClinicNotificationRecipient::class);
    }

    /**
     * Dashboard user who created this message.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Dashboard user who last updated this message.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @param  Builder<ClinicNotification>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * @param  Builder<ClinicNotification>  $query
     */
    public function scopeVisibleNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where(fn (Builder $query) => $query->whereNull('visible_from')->orWhere('visible_from', '<=', $now))
            ->where(fn (Builder $query) => $query->whereNull('visible_until')->orWhere('visible_until', '>=', $now));
    }

    public function importanceTone(): string
    {
        return match ($this->importance) {
            'urgent' => 'rose',
            'important' => 'amber',
            'info' => 'teal',
            default => 'zinc',
        };
    }
}
