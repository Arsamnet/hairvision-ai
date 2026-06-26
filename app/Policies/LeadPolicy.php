<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Authorizes dashboard access to tenant-owned leads.
 *
 * @since 1.1.0
 */
class LeadPolicy
{
    /**
     * Determine whether a user may browse lead lists.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive()
            && ($user->isSuperAdmin() || $user->tenants()->wherePivot('status', 'active')->exists());
    }

    /**
     * Determine whether a user may view a specific lead.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $user->isActive() && ($user->isSuperAdmin() || $user->canAccessTenant($lead->tenant_id));
    }

    /**
     * Determine whether a user may create leads in the active tenant.
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (in_array($user->role, ['clinic_admin', 'editor'], true)) {
            return true;
        }

        return $user->tenants()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', ['clinic_admin', 'editor'])
            ->exists();
    }

    /**
     * Determine whether a user may update a tenant-owned lead.
     */
    public function update(User $user, Lead $lead): bool
    {
        if ($user->isSuperAdmin()) {
            return false;
        }

        if (! $user->isActive() || ! $user->canAccessTenant($lead->tenant_id)) {
            return false;
        }

        return in_array($user->roleForTenant($lead->tenant_id), ['clinic_admin', 'editor'], true);
    }

    /**
     * Lead deletion is intentionally disabled in the MVP dashboard.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return false;
    }
}
