<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorizes dashboard user management actions.
 *
 * @since 1.0.0
 */
class UserPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.list')
            && $user->tenants()->wherePivot('status', 'active')->exists();
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view')
            && $this->sharesActiveTenant($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.edit')
            && $this->sharesActiveTenant($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission('users.delete')
            && ! $user->is($target)
            && $this->sharesActiveTenant($user, $target);
    }

    private function sharesActiveTenant(User $user, User $target): bool
    {
        $tenantIds = $user->tenants()
            ->wherePivot('status', 'active')
            ->pluck('tenants.id')
            ->all();

        if ($tenantIds === []) {
            return false;
        }

        return $target->tenants()
            ->wherePivot('status', 'active')
            ->whereIn('tenants.id', $tenantIds)
            ->exists();
    }
}
