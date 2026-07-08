<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Authorizes dashboard access to tenant-owned leads.
 *
 * @since 1.0.0
 */
class LeadPolicy
{
    /**
     * Super admins may bypass tenant-scoped checks.
     */
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /**
     * Determine whether a user may browse lead lists.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('leads.list')
            && ($user->isSuperAdmin() || $user->tenants()->wherePivot('status', 'active')->exists());
    }

    /**
     * Determine whether a user may view a specific lead.
     */
    public function view(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.view', $lead->tenant_id)
            && $user->canAccessTenant($lead->tenant_id);
    }

    /**
     * Determine whether a user may create leads in the active tenant.
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
     */
    public function createNote(User $user, Lead $lead): bool
    {
        return $this->update($user, $lead);
    }

    /**
     * Determine whether a user may update a CRM follow-up note for the lead.
     */
    public function updateNote(User $user, Lead $lead, mixed $note): bool
    {
        return $note?->lead_id === $lead->id
            && $note?->tenant_id === $lead->tenant_id
            && $this->update($user, $lead);
    }

    /**
     * Lead deletion requires explicit permission and tenant access.
     */
    public function delete(User $user, Lead $lead): bool
    {
        return $user->hasPermission('leads.delete', $lead->tenant_id)
            && $user->canAccessTenant($lead->tenant_id);
    }
}
