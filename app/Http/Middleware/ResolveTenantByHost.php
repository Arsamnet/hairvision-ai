<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use App\Support\Tenancy\HostClassifier;
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

        $host = HostClassifier::normalize($request->getHost());

        $domain = TenantDomain::query()
            ->with('tenant')
            ->where('domain', '=', $host)
            ->where('status', '=', 'active')
            ->first();

        if (! $domain && app()->environment('local') && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $domain = TenantDomain::query()
                ->with('tenant')
                ->whereIn('type', HostClassifier::visitorDomainTypes())
                ->where('status', '=', 'active')
                ->first();

        }

        if (
            HostClassifier::isPlatformHost($host, $domain)
            || ! $domain
            || ! in_array($domain->type, HostClassifier::visitorDomainTypes(), true)
            || ! $domain->tenant
            || $domain->tenant->status !== 'active'
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }

        app()->instance('currentTenant', $domain->tenant);
        $request->attributes->set('currentTenant', $domain->tenant);

        return $next($request);
    }
}
