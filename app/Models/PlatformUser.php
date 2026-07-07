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

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAccessTenant(?int $tenantId): bool
    {
        return $this->isSuperAdmin() && $tenantId !== null;
    }

    public function roleForTenant(?int $tenantId): string
    {
        return $this->role;
    }

    public function hasPermission(string $permission, ?int $tenantId = null): bool
    {
        return app(PermissionService::class)->can($this, $permission, null);
    }

    /**
     * @param  iterable<string>  $permissions
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
