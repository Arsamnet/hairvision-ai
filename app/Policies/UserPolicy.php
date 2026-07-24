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
        if (! $user->hasPermission('users.list')) {
            return false;
        }

        return $user->isSystemScoped()
            || $user->tenants()->wherePivot('status', 'active')->exists();
    }

    /**
     * Execute the view operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  User  $target  The target value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view')
            && $this->sharesActiveTenant($user, $target);
    }

    /**
     * Display the form for creating a new user policy.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    /**
     * Update the requested user policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  User  $target  The target value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.edit')
            && $this->sharesActiveTenant($user, $target);
    }

    /**
     * Delete the requested user policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  User  $target  The target value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission('users.delete')
            && ! $user->is($target)
            && $this->sharesActiveTenant($user, $target);
    }

    /**
     * Execute the shares active tenant operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  User  $target  The target value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function sharesActiveTenant(User $user, User $target): bool
    {
        if ($user->isSystemScoped()) {
            return true;
        }

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
