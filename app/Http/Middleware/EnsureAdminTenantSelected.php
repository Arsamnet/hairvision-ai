<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminTenantContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the active admin tenant for authenticated dashboard routes.
 *
 * @since 1.1.0
 */
class EnsureAdminTenantSelected
{
    public function __construct(private readonly AdminTenantContext $tenantContext) {}

    /**
     * Attach the active tenant and available tenant list to the request.
     *
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        $request->attributes->set('adminTenant', $this->tenantContext->current($request, $user));
        $request->attributes->set('adminTenants', $this->tenantContext->availableTenants($user));

        return $next($request);
    }
}
