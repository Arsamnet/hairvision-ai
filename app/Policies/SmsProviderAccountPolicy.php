<?php

namespace App\Policies;

use App\Enums\SmsProviderScope;
use App\Models\SmsProviderAccount;
use App\Models\User;

/**
 * Authorizes dashboard management of system and user SMS provider accounts.
 *
 * @since 1.2.0
 */
class SmsProviderAccountPolicy
{
    /**
     * Determine whether a user may browse SMS provider accounts.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether a user may view an account.
     */
    public function view(User $user, SmsProviderAccount $account): bool
    {
        return $user->isSuperAdmin()
            || $account->scope === SmsProviderScope::System
            || $account->owner_user_id === $user->id;
    }

    /**
     * Determine whether a user may create provider accounts.
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether a user may update an account.
     */
    public function update(User $user, SmsProviderAccount $account): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $account->scope === SmsProviderScope::User && $account->owner_user_id === $user->id;
    }

    /**
     * Determine whether a user may delete an account.
     */
    public function delete(User $user, SmsProviderAccount $account): bool
    {
        return $this->update($user, $account);
    }
}
