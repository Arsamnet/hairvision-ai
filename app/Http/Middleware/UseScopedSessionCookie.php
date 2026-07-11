<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\HostClassifier;
use Closure;
use Illuminate\Http\Request;

/**
 * Selects a separate session cookie for each authentication surface.
 *
 * Public lead sessions, clinic dashboard sessions, and platform dashboard
 * sessions must not overwrite or invalidate one another on shared hosts.
 *
 * @since 1.0.0
 */
class UseScopedSessionCookie
{
    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $cookieName = $this->cookieName($request);
        $session = app('session');
        $currentCookieName = $session->driver()->getName();

        if (config('session.cookie') !== $cookieName || $currentCookieName !== $cookieName) {
            $testSessionData = app()->runningUnitTests() && count($request->cookies->all()) === 0
                ? $session->driver()->all()
                : [];

            config(['session.cookie' => $cookieName]);
            app()->forgetInstance('session.store');
            $session->forgetDrivers();

            if ($testSessionData !== []) {
                $session->driver()->replace($testSessionData);
            }

            app()->instance('session.store', $session->driver());
        }

        return $next($request);
    }

    private function cookieName(Request $request): string
    {
        if ($request->is('api/v1/*')) {
            return (string) config('hairvision.sessions.lead_cookie', 'hairvision_lead_session');
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            return $this->isPlatformHost($request)
                ? (string) config('hairvision.sessions.platform_admin_cookie', 'hairvision_platform_admin_session')
                : (string) config('hairvision.sessions.clinic_admin_cookie', 'hairvision_clinic_admin_session');
        }

        return (string) config('session.cookie', 'hairvision_session');
    }

    private function isPlatformHost(Request $request): bool
    {
        return HostClassifier::isPrimaryHost($request->getHost());
    }
}
