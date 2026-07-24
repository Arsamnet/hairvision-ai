<?php

namespace App\Policies;

use App\Models\AiModelAccess;
use App\Models\User;

/**
 * Authorizes global AI model access management.
 *
 * @since 1.0.0
 */
class AiModelAccessPolicy
{
    /**
     * Execute the before operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  string  $ability  The ability value.
     * @return bool|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function before(User $user, string $ability): ?bool
    {
        if (in_array($ability, ['delete', 'update'], true)) {
            return null;
        }

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
        return $user->hasPermission('ai_models.list');
    }

    /**
     * Execute the view operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  AiModelAccess  $access  The access value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function view(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.view');
    }

    /**
     * Display the form for creating a new ai model access policy.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('ai_models.create');
    }

    /**
     * Update the requested ai model access policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  AiModelAccess  $access  The access value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function update(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.edit');
    }

    /**
     * Delete the requested ai model access policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  AiModelAccess  $access  The access value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function delete(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.delete');
    }

    /**
     * Determine whether the user may toggle model access.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  AiModelAccess  $access  The access value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function toggle(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.toggle');
    }

    /**
     * Assign to clinic.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function assignToClinic(User $user): bool
    {
        return $user->hasPermission('ai_models.assign_to_clinic');
    }
}
