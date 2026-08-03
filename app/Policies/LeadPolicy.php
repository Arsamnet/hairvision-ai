<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Authorizes dashboard access to tenant-owned leads.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class LeadPolicy
{
    /**
     * Super admins may bypass tenant-scoped checks.
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
     * Determine whether a user may browse lead lists.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leads.list')
            && ($user->isSystemScoped() || $user->tenants()->wherePivot('status', 'active')->exists());
    }

    /**
     * Determine whether a user may view a specific lead.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.view', $lead->tenant_id)
            && ($user->isSystemScoped() || $user->canAccessTenant($lead->tenant_id));
    }

    /**
     * Determine whether a user may create leads in the active tenant.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function create(User $user): bool
    {
        if ($user->hasPermission('leads.create')) {
            return true;
        }

        return $user->tenants()
            ->wherePivot('status', 'active')
            ->wherePivotIn('role', ['clinic_admin', 'editor'])
            ->exists();
    }

    /**
     * Determine whether a user may update a tenant-owned lead.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function update(User $user, Lead $lead): bool
    {
        if (! $user->isActive() || ! $user->canAccessTenant($lead->tenant_id)) {
            return false;
        }

        return $user->hasPermission('leads.edit', $lead->tenant_id);
    }

    /**
     * Determine whether a user may add a CRM follow-up note.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function createNote(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }

    /**
     * Determine whether a user may update a CRM follow-up note for the lead.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  mixed  $note  The note value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function updateNote(User $user, Lead $lead, mixed $note): bool
    {
        return $note?->lead_id === $lead->id
            && $note?->tenant_id === $lead->tenant_id
            && $this->update($user, $lead);
    }

    /**
     * Lead deletion requires explicit permission and tenant access.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.delete', $lead->tenant_id)
            && ($user->isSystemScoped() || $user->canAccessTenant($lead->tenant_id));
    }
}
