<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-scoped delivery and read state for a clinic notification.
 *
 * @since 1.0.0
 */
class ClinicNotificationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_notification_id',
        'tenant_id',
        'read_by_user_id',
        'read_by_user_name',
        'delivered_at',
        'read_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Message delivered to the clinic.
     *
     * @return BelongsTo<ClinicNotification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(ClinicNotification::class, 'clinic_notification_id');
    }

    /**
     * Clinic tenant that received the message.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Dashboard user who first opened the message.
     *
     * @return BelongsTo<User, $this>
     */
    public function readByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by_user_id');
    }

    /**
     * @param  Builder<ClinicNotificationRecipient>  $query
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * @param  Builder<ClinicNotificationRecipient>  $query
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * @param  Builder<ClinicNotificationRecipient>  $query
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query->whereHas('notification', fn (Builder $query) => $query->published()->visibleNow());
    }

    public function markReadBy(User $user): void
    {
        if ($this->read_at !== null) {
            return;
        }

        $this->forceFill([
            'read_by_user_id' => $user->id,
            'read_by_user_name' => $user->name,
            'read_at' => now(),
        ])->save();
    }
}
