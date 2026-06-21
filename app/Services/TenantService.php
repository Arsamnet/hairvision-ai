<?php

namespace App\Services;

use App\Models\Tenant;

class TenantService
{
    public function current(): ?Tenant
    {
        return app('currentTenant');
    }
}
