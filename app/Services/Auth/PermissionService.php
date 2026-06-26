<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Checks database-backed dashboard permissions for authenticated users.
 *
 * @since 1.0.0
 */
class PermissionService
{
    public function __construct(private readonly PermissionCatalog $catalog) {}

    /**
     * Determine whether a user has a permission in the active tenant context.
     */
    public function can(User $user, string $permission, ?int $tenantId = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $roleCode = $user->roleForTenant($tenantId);

        return in_array($permission, $this->permissionsForRole($roleCode), true);
    }

    /**
     * Clear cached permission lookups after role permission changes.
     */
    public function forgetRole(string $roleCode): void
    {
        Cache::forget($this->cacheKey($roleCode));
    }

    /**
     * Return permission codes assigned to a role code.
     *
     * @return array<int, string>
     */
    private function permissionsForRole(string $roleCode): array
    {
        return Cache::remember($this->cacheKey($roleCode), 300, function () use ($roleCode): array {
            return Role::query()
                ->where('code', $roleCode)
                ->where('status', 'active')
                ->first()
                ?->permissions()
                ->pluck('code')
                ->all() ?? $this->catalog->roles()[$roleCode]['permissions'] ?? [];
        });
    }

    private function cacheKey(string $roleCode): string
    {
        return 'hairvision:role-permissions:'.$roleCode;
    }
}
