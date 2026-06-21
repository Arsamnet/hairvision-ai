<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantLandingPageVisit;
use Illuminate\Http\Request;

class LandingPageVisitService
{
    public function record(Request $request, Tenant $tenant): TenantLandingPageVisit
    {
        return TenantLandingPageVisit::create([
            'tenant_id' => $tenant->id,
            'host' => strtolower($request->getHost()),
            'ip_hash' => $request->ip() ? hash('sha256', $request->ip()) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
            'referer' => $request->headers->get('referer'),
            'visited_at' => now(),
        ]);
    }
}
