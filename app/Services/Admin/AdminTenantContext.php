<?php

namespace App\Services\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and validates the active dashboard tenant stored in session.
 *
 * @since 1.1.0
 */
class AdminTenantContext
{
    /**
     * Return the tenant currently selected for dashboard work.
     */
    public function current(Request $request, User $user): ?Tenant
    {
        if ($user->isSuperAdmin() && $request->session()->get('admin_all_tenants')) {
            return null;
        }

        $tenantId = $request->session()->get('admin_active_tenant_id');

        if ($tenantId && $user->canAccessTenant((int) $tenantId)) {
            return Tenant::query()->whereKey($tenantId)->first();
        }

        if ($user->isSuperAdmin()) {
            return null;
        }

        $tenant = $this->defaultTenant($user);

        if ($tenant) {
            $request->session()->put('admin_active_tenant_id', $tenant->id);
        }

        return $tenant;
    }

    /**
     * Select a tenant after checking that the user is allowed to use it.
     *
     * @throws ValidationException
     */
    public function select(Request $request, User $user, int|string|null $tenantId): ?Tenant
    {
        if ($tenantId === 'all' && $user->isSuperAdmin()) {
            $request->session()->forget('admin_active_tenant_id');
            $request->session()->put('admin_all_tenants', true);

            return null;
        }

        if (! is_numeric($tenantId) || ! $user->canAccessTenant((int) $tenantId)) {
            throw ValidationException::withMessages([
                'tenant_id' => __('permissions.tenant_denied'),
            ]);
        }

        $tenant = Tenant::query()->whereKey((int) $tenantId)->where('status', 'active')->firstOrFail();
        $request->session()->forget('admin_all_tenants');
        $request->session()->put('admin_active_tenant_id', $tenant->id);

        return $tenant;
    }

    /**
     * Tenants available to the authenticated user for the switcher.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    public function availableTenants(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Tenant::query()->where('status', 'active')->orderBy('name')->get();
        }

        return $user->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.name')
            ->get();
    }

    /**
     * Resolve the default active tenant for a user.
     */
    private function defaultTenant(User $user): ?Tenant
    {
        return $user->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->orderBy('tenants.id')
            ->first();
    }
}
