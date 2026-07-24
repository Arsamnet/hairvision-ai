<?php

namespace App\Services;

use App\Models\Tenant;
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
     *
     * @return Tenant|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function current(): ?Tenant
    {
        $request = app(Request::class);

        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            return null;
        }

        return $tenant;
    }
}
