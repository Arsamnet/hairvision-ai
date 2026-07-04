<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\DebugStepLogger;
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
        $request = app(Request::class);

        DebugStepLogger::step($request, 'tenant.current', 'Current tenant lookup started');

        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            DebugStepLogger::step($request, 'tenant.current', 'Current tenant missing from request attributes');

            return null;
        }

        DebugStepLogger::step($request, 'tenant.current', 'Current tenant resolved from request attributes', [
            'tenant_id' => $tenant->getKey(),
            'tenant_status' => $tenant->status ?? null,
        ]);

        return $tenant;
    }
}