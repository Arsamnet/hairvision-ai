<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Authorizes platform-level clinic management.
 *
 * @since 1.0.0
 */
class TenantPolicy
{
    /**
     * Execute the before operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /**
     * Execute the view any operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tenants.list');
    }

    /**
     * Execute the view operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('tenants.view') && $user->canAccessTenant($tenant->id);
    }

    /**
     * Display the form for creating a new tenant policy.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('tenants.create');
    }

    /**
     * Update the requested tenant policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('tenants.edit');
    }

    /**
     * Delete the requested tenant policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('tenants.delete');
    }
}
