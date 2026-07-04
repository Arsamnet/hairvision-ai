<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantLandingPageVisit;
use App\Support\DebugStepLogger;
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
     */
    public function record(Request $request, Tenant $tenant): TenantLandingPageVisit
    {
        $scope = 'public.landing.visit';

        DebugStepLogger::step($request, $scope, 'Visit recording requested', [
            'tenant_id' => $tenant->id,
            'host' => strtolower($request->getHost()),
            'has_ip' => $request->ip() !== null,
            'has_user_agent' => $request->userAgent() !== null,
            'has_referer' => $request->headers->has('referer'),
        ]);

        $host = strtolower($request->getHost());
        $ipHash = $request->ip() ? hash('sha256', $request->ip()) : null;
        $userAgentHash = $request->userAgent() ? hash('sha256', $request->userAgent()) : null;

        DebugStepLogger::step($request, $scope, 'Anonymous visitor identifiers prepared', [
            'tenant_id' => $tenant->id,
            'has_ip_hash' => $ipHash !== null,
            'has_user_agent_hash' => $userAgentHash !== null,
        ]);

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
            DebugStepLogger::step($request, $scope, 'Visit recording failed', [
                'tenant_id' => $tenant->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        DebugStepLogger::step($request, $scope, 'Visit recorded', [
            'tenant_id' => $tenant->id,
            'visit_id' => $visit->id,
            'host' => $host,
        ]);

        return $visit;
    }
}