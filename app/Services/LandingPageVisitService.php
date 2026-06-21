<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantLandingPageVisit;
use Illuminate\Http\Request;

/**
 * Records anonymous tenant landing page visits for MVP analytics.
 *
 * @since 1.0.0
 */
class LandingPageVisitService
{
    /**
     * Create a tenant-scoped visit row with hashed visitor identifiers.
     *
     * Side effects: stores a visit record but does not create a lead.
     */
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
