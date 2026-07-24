<?php

namespace App\Http\Middleware;

use App\Enums\PlanAccessStatus;
use App\Services\Billing\PlanAccessService;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks plan-restricted dashboard routes when a normal user has no active plan.
 *
 * @since 1.0.0
 */
class EnsureActivePlanAccess
{
    /**
     * Create a new ensure active plan access instance.
     *
     * @param  PlanAccessService  $plans  The plans value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly PlanAccessService $plans) {}

    /**
     * Execute the ensure active plan access operation.
     *
     * @param  Closure(Request): mixed  $next
     * @param  Request  $request  Incoming HTTP request.
     * @return mixed Resolved operation result.
     *
     * @since 1.0.0
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        $state = $this->plans->stateFor($user, $request->attributes->get('adminTenant'));
        $request->attributes->set('planAccess', $state);
        $request->attributes->set('planSubscription', $state['subscription']);
        $request->attributes->set(
            'activeSubscription',
            $state['status'] === PlanAccessStatus::Active ? $state['subscription'] : null
        );

        if ($state['allowed'] || $this->routeIsAllowedWithoutPlan($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __($state['message_key']));
        }

        return redirect()
            ->route('admin.plans.index')
            ->withErrors(['plan' => __($state['message_key'])]);
    }

    /**
     * Execute the route is allowed without plan operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function routeIsAllowedWithoutPlan(Request $request): bool
    {
        return $request->routeIs(
            'admin.dashboard',
            'admin.notifications.*',
            'admin.plans.*',
            'admin.billing.*',
            'admin.tenant.switch',
            'admin.filter-presets.store',
            'admin.logout',
            'admin.system.*',
        );
    }
}
