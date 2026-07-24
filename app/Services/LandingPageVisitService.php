<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantLandingPageVisit;
use Illuminate\Http\Request;
use Throwable;

/**
 * Records anonymous tenant landing visits.
 *
 * @since 1.0.0
 */
class LandingPageVisitService
{
    /**
     * Create a tenant-scoped visit row.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return TenantLandingPageVisit Resolved operation result.
     *
     * @since 1.0.0
     */
    public function record(Request $request, Tenant $tenant): TenantLandingPageVisit
    {
        $scope = 'public.landing.visit';

        $host = strtolower($request->getHost());
        $ipHash = $request->ip() ? hash('sha256', $request->ip()) : null;
        $userAgentHash = $request->userAgent() ? hash('sha256', $request->userAgent()) : null;

        try {
            $visit = TenantLandingPageVisit::create([
                'tenant_id' => $tenant->id,
                'host' => $host,
                'ip_hash' => $ipHash,
                'user_agent_hash' => $userAgentHash,
                'referer' => $request->headers->get('referer'),
                'visited_at' => now(),
            ]);
        } catch (Throwable $exception) {
            throw $exception;
        }

        return $visit;
    }
}
