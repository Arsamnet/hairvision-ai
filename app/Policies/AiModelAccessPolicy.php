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
    public function before(User $user, string $ability): ?bool
    {
        if ($ability === 'delete') {
            return null;
        }

        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ai_models.list');
    }

    public function view(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('ai_models.create');
    }

    public function update(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.edit');
    }

    public function delete(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.delete') && ! $access->is_protected;
    }

    public function toggle(User $user, AiModelAccess $access): bool
    {
        return $user->hasPermission('ai_models.toggle');
    }

    public function assignToClinic(User $user): bool
    {
        return $user->hasPermission('ai_models.assign_to_clinic');
    }
}
