<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Http\Request;

/**
 * Access point for the request's resolved tenant.
 *
 * @since 1.0.0
 */
class TenantService
{
    /**
     * Return the tenant resolved from the current request host.
     */
    public function current(): ?Tenant
    {
        return app('currentTenant');
    }

    /**
     * Resolve an active tenant domain from a normalized request host.
     */
    public function resolveDomainForHost(string $host, bool $allowLocalFallback = true): ?TenantDomain
    {
        $host = strtolower($host);

        $domain = TenantDomain::query()
            ->with(['tenant.theme'])
            ->where('domain', '=', $host)
            ->where('status', '=', 'active')
            ->first();

        if (! $domain && $allowLocalFallback && app()->environment('local') && $this->isLocalDevelopmentHost($host)) {
            return TenantDomain::query()
                ->with(['tenant.theme'])
                ->where('status', '=', 'active')
                ->first();
        }

        return $domain;
    }

    /**
     * Store the resolved tenant consistently for downstream services.
     */
    public function bindCurrent(Request $request, Tenant $tenant): void
    {
        app()->instance('currentTenant', $tenant);
        $request->attributes->set('currentTenant', $tenant);
    }

    /**
     * Determine whether the host is a local development fallback host.
     */
    public function isLocalDevelopmentHost(string $host): bool
    {
        return in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);
    }
}
