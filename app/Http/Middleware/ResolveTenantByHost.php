<?php

namespace App\Http\Middleware;

use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResolveTenantByHost
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());

        $domain = TenantDomain::query()->where('domain', '=', $host)->where('status', '=', 'active')->first();

        // In local development, allow falling back to the first active tenant
        // so `localhost` or `127.0.0.1` can render the landing page without
        // requiring host DNS entries.
        if (! $domain && app()->environment('local') && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            $domain = TenantDomain::query()->where('status', '=', 'active')->first();
        }

        if (! $domain || ! $domain->tenant || $domain->tenant->status !== 'active') {
            abort(Response::HTTP_NOT_FOUND);
        }

        app()->instance('currentTenant', $domain->tenant);
        $request->attributes->set('currentTenant', $domain->tenant);

        return $next($request);
    }
}
