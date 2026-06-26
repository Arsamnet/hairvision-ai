<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Resolves the current tenant exclusively from the request host.
 *
 * Side effects: binds `currentTenant` into the service container and request
 * attributes for downstream controllers and services.
 *
 * @since 1.0.0
 */
class ResolveTenantByHost
{
    public function __construct(private readonly TenantService $tenants) {}

    /**
     * Resolve an active tenant domain or abort with 404.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $host = strtolower($request->getHost());
        $domain = $this->tenants->resolveDomainForHost($host);

        if (! $domain || ! $domain->tenant || $domain->tenant->status !== 'active') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $this->tenants->bindCurrent($request, $domain->tenant);

        return $next($request);
    }
}
