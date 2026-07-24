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
     *
     * @since 1.0.0
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(ClinicNotification::class, 'clinic_notification_id');
    }

    /**
     * Clinic tenant that received the message.
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
     * Dashboard user who first opened the message.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function readByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by_user_id');
    }

    /**
     * Constrain the query by for tenant.
     *
     * @param  Builder<ClinicNotificationRecipient>  $query
     * @param  int  $tenantId  The tenant id value.
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Constrain the query by unread.
     *
     * @param  Builder<ClinicNotificationRecipient>  $query
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Constrain the query by currently visible.
     *
     * @param  Builder<ClinicNotificationRecipient>  $query
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     */
    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query->whereHas('notification', fn (Builder $query) => $query->published()->visibleNow());
    }

    /**
     * Execute the mark read by operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
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
