<?php

namespace App\Services\Auth;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;

/**
 * Checks database-backed dashboard permissions for authenticated users.
 *
 * @since 1.0.0
 */
class PermissionService
{
    /**
     * Create a new permission service instance.
     *
     * @param  PermissionCatalog  $catalog  The catalog value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly PermissionCatalog $catalog) {}

    /**
     * Determine whether a user has a permission in the active tenant context.
     *
     * @param  mixed  $user  Authenticated user performing the operation.
     * @param  string  $permission  The permission value.
     * @param  int|null  $tenantId  The tenant id value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function can(mixed $user, string $permission, ?int $tenantId = null): bool
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
     *
     * @param  string  $roleCode  The role code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function forgetRole(string $roleCode): void
    {
        Cache::forget($this->cacheKey($roleCode));
    }

    /**
     * Return permission codes assigned to a role code.
     *
     * @param  string  $roleCode  The role code value.
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function permissionsForRole(string $roleCode): array
    {
        return Cache::remember($this->cacheKey($roleCode), 300, function () use ($roleCode): array {
            $role = Role::query()
                ->where('code', $roleCode)
                ->where('status', 'active')
                ->first();

            if ($role instanceof Role && $role->isClinicRole() && array_key_exists($roleCode, $this->catalog->roles())) {
                return $this->catalog->roles()[$roleCode]['permissions'];
            }

            return $role
                ?->permissions()
                ->pluck('code')
                ->all() ?? $this->catalog->roles()[$roleCode]['permissions'] ?? [];
        });
    }

    /**
     * Execute the cache key operation.
     *
     * @param  string  $roleCode  The role code value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function cacheKey(string $roleCode): string
    {
        return 'hairvision:role-permissions:'.$roleCode;
    }
}
