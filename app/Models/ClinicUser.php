<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\Auth\PermissionService;
use Database\Factories\ClinicUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Clinic-level dashboard user scoped to exactly one tenant.
 *
 * @since 1.0.0
 */
#[Fillable(['tenant_id', 'name', 'email', 'phone_normalized', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class ClinicUser extends Authenticatable
{
    /** @use HasFactory<ClinicUserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected $table = 'clinic_users';

    /**
     * Execute the casts operation.
     *
     * @return array Resolved data array.
     *
     * @since 1.0.0
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Clinic that owns this user.
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
     * Determine whether super admin.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isSuperAdmin(): bool
    {
        return false;
    }

    /**
     * Determine whether active.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Execute the can access tenant operation.
     *
     * @param  int|null  $tenantId  The tenant id value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function canAccessTenant(?int $tenantId): bool
    {
        return $tenantId !== null && (int) $this->tenant_id === (int) $tenantId;
    }

    /**
     * Execute the role for tenant operation.
     *
     * @param  int|null  $tenantId  The tenant id value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function roleForTenant(?int $tenantId): string
    {
        return $this->canAccessTenant($tenantId) ? $this->role : 'viewer';
    }

    /**
     * Determine whether permission.
     *
     * @param  string  $permission  The permission value.
     * @param  int|null  $tenantId  The tenant id value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function hasPermission(string $permission, ?int $tenantId = null): bool
    {
        if (! $this->canAccessTenant($tenantId ?? $this->tenant_id)) {
            return false;
        }

        return app(PermissionService::class)->can($this, $permission, $this->tenant_id);
    }

    /**
     * Determine whether any permission.
     *
     * @param  iterable<string>  $permissions
     * @param  int|null  $tenantId  The tenant id value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function hasAnyPermission(iterable $permissions, ?int $tenantId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $tenantId)) {
                return true;
            }
        }

        return false;
    }
}
