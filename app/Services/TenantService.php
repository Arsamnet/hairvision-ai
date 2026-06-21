<?php

namespace App\Services;

use App\Models\Tenant;

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
}
