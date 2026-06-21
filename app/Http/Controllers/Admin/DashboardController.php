<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\Admin\LeadDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders dashboard summary pages.
 *
 * @since 1.1.0
 */
class DashboardController extends Controller
{
    public function index(Request $request, LeadDashboardService $leads): View
    {
        $tenant = $request->attributes->get('adminTenant');
        $query = $leads->baseQuery($request->user(), $tenant);

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'leadCount' => (clone $query)->count(),
            'newLeadCount' => (clone $query)->where('crm_stage', 'new_lead')->count(),
            'followUpCount' => (clone $query)->where('crm_stage', 'needs_follow_up')->count(),
            'convertedCount' => (clone $query)->where('crm_stage', 'converted')->count(),
            'recentLeads' => (clone $query)->with('tenant')->latest()->limit(6)->get(),
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
