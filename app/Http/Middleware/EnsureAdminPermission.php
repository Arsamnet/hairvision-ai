<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Enforces a backend dashboard permission before a route is executed.
 *
 * @since 1.0.0
 */
class EnsureAdminPermission
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next, string $permission, string ...$permissions): mixed
    {
        $tenant = $request->attributes->get('adminTenant');
        $requiredPermissions = [$permission, ...$permissions];

        abort_unless(
            $request->user()?->hasAnyPermission($requiredPermissions, $tenant?->id),
            403,
            __('permissions.denied')
        );

        return $next($request);
    }
}
