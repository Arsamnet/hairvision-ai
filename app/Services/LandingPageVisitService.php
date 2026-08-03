<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantLandingPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Records anonymous tenant landing visits.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function record(Request $request, Tenant $tenant): TenantLandingPageVisit
    {
        $scope = 'public.landing.visit';

        $host = strtolower($request->getHost());
        $ipHash = $request->ip() ? hash('sha256', $request->ip()) : null;
        $userAgentHash = $request->userAgent() ? hash('sha256', $request->userAgent()) : null;
        $analyticsToken = Str::random(64);

        try {
            $visit = TenantLandingPageVisit::create([
                'tenant_id' => $tenant->id,
                'host' => $host,
                'ip_hash' => $ipHash,
                'user_agent_hash' => $userAgentHash,
                'analytics_token_hash' => hash('sha256', $analyticsToken),
                'referer' => $this->refererOrigin($request),
                'visited_at' => now(),
            ]);
        } catch (Throwable $exception) {
            throw $exception;
        }

        return $visit->setAttribute('event_token', $analyticsToken);
    }

    /**
     * Reduce the optional referrer to a source origin without query-string PII.
     *
     * @param  Request  $request  Incoming public landing request.
     * @return string|null Safe HTTP(S) referrer origin.
     *
     * @since 2.1.0
     */
    private function refererOrigin(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);

        if (
            ! is_array($parts)
            || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        ) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.strtolower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.(int) $parts['port'];
        }

        return mb_substr($origin, 0, 255);
    }
}
