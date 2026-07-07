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
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tenants.list');
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('tenants.view') && $user->canAccessTenant($tenant->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tenants.create');
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('tenants.edit');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasPermission('tenants.delete');
    }
}
