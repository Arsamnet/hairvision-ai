<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\Auth\PermissionService;
use Database\Factories\PlatformUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Platform-level dashboard user with system-wide permissions.
 *
 * @since 1.0.0
 */
#[Fillable(['name', 'email', 'phone_normalized', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class PlatformUser extends Authenticatable
{
    /** @use HasFactory<PlatformUserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected $table = 'platform_users';

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
     * Determine whether super admin.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
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
        return $this->isSuperAdmin() && $tenantId !== null;
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
        return $this->role;
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
        return app(PermissionService::class)->can($this, $permission, null);
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
