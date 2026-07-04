<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use App\Support\DebugStepLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Resolves the current tenant from the request host.
 *
 * @since 1.0.0
 */
class ResolveTenantByHost
{
    /**
     * Resolve an active tenant domain and bind it to the request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $scope = 'tenant.resolve_host';

        DebugStepLogger::step($request, $scope, 'Request received', [
            'host' => strtolower($request->getHost()),
            'environment' => app()->environment(),
        ]);

        $host = strtolower($request->getHost());

        DebugStepLogger::step($request, $scope, 'Host normalized', [
            'host' => $host,
        ]);

        $domain = TenantDomain::query()
            ->with('tenant')
            ->where('domain', '=', $host)
            ->where('status', '=', 'active')
            ->first();

        DebugStepLogger::step($request, $scope, 'Tenant domain lookup completed', [
            'host' => $host,
            'domain_found' => $domain !== null,
            'tenant_domain_id' => $domain?->id,
            'tenant_id' => $domain?->tenant?->id,
        ]);

        if (! $domain && app()->environment('local') && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            DebugStepLogger::step($request, $scope, 'Local fallback lookup started', [
                'host' => $host,
            ]);

            $domain = TenantDomain::query()
                ->with('tenant')
                ->where('status', '=', 'active')
                ->first();

            DebugStepLogger::step($request, $scope, 'Local fallback lookup completed', [
                'domain_found' => $domain !== null,
                'tenant_domain_id' => $domain?->id,
                'tenant_id' => $domain?->tenant?->id,
            ]);
        }

        if (! $domain || ! $domain->tenant || $domain->tenant->status !== 'active') {
            DebugStepLogger::step($request, $scope, 'Tenant resolution failed', [
                'host' => $host,
                'domain_found' => $domain !== null,
                'tenant_domain_id' => $domain?->id,
                'tenant_id' => $domain?->tenant?->id,
                'tenant_status' => $domain?->tenant?->status,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        app()->instance('currentTenant', $domain->tenant);
        $request->attributes->set('currentTenant', $domain->tenant);

        DebugStepLogger::step($request, $scope, 'Tenant bound to request and container', [
            'tenant_id' => $domain->tenant->id,
            'tenant_domain_id' => $domain->id,
            'tenant_status' => $domain->tenant->status,
        ]);

        DebugStepLogger::step($request, $scope, 'Passing request to next middleware');

        return $next($request);
    }
}