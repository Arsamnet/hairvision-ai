<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\PlanAccessStatus;
use App\Models\Tenant;
use App\Services\Admin\LeadDashboardService;
use App\Services\Admin\SystemOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Renders dashboard summary pages.
 *
 * @since 1.1.0
 */
class DashboardController extends Controller
{
    public function index(Request $request, LeadDashboardService $leads, SystemOverviewService $overview): View
    {
        $tenant = $request->attributes->get('adminTenant');
        $planAccess = $request->attributes->get('planAccess');
        $user = $request->user();

        if ($user->hasPermission('system.dashboard', $tenant?->id)) {
            return view('admin.system.dashboard', [
                'tenant' => $tenant,
                'availableTenants' => $request->attributes->get('adminTenants'),
                'counts' => $overview->dashboardCounts(),
            ]);
        }

        $canSeeOperationalData = $request->user()->isSuperAdmin()
            || ($planAccess['status'] ?? null) === PlanAccessStatus::Active
            || ($planAccess['status'] ?? null) === PlanAccessStatus::Exempt;
        $query = $canSeeOperationalData ? $leads->baseQuery($request->user(), $tenant) : null;

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'planAccess' => $planAccess,
            'activeSubscription' => $request->attributes->get('activeSubscription'),
            'leadCount' => $query ? (clone $query)->count() : 0,
            'newLeadCount' => $query ? (clone $query)->where('crm_stage', 'new_lead')->count() : 0,
            'followUpCount' => $query ? (clone $query)->where('crm_stage', 'needs_follow_up')->count() : 0,
            'convertedCount' => $query ? (clone $query)->where('crm_stage', 'converted')->count() : 0,
            'recentLeads' => $query ? (clone $query)->with('tenant')->latest()->limit(6)->get() : new Collection(),
        ]);
    }

    public function reports(Request $request, LeadDashboardService $leads): View
    {
        $tenant = $request->attributes->get('adminTenant');
        $query = $leads->baseQuery($request->user(), $tenant);

        return view('admin.reports', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'stageCounts' => (clone $query)
                ->selectRaw('crm_stage, count(*) as aggregate')
                ->groupBy('crm_stage')
                ->pluck('aggregate', 'crm_stage'),
            'tenantCounts' => $request->user()->isSuperAdmin()
                ? Tenant::query()->withCount('leads')->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function settings(Request $request): View
    {
        return view('admin.settings', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
        ]);
    }
}
