<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanAccessStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Admin\LeadDashboardService;
use App\Services\Admin\SystemOverviewService;
use App\Services\Billing\ServiceEntitlementService;
use App\Support\DebugStepLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Renders admin dashboard pages.
 *
 * @since 1.1.0
 */
class DashboardController extends Controller
{
    /**
     * Show the main dashboard for system or tenant users.
     */
    public function index(
        Request $request,
        LeadDashboardService $leads,
        SystemOverviewService $overview,
        ServiceEntitlementService $entitlements
    ): View {
        $scope = 'admin.dashboard.index';

        $tenant = $request->attributes->get('adminTenant');
        $availableTenants = $request->attributes->get('adminTenants');
        $planAccess = $request->attributes->get('planAccess');
        $activeSubscription = $request->attributes->get('activeSubscription');
        $user = $request->user();

        DebugStepLogger::step($request, $scope, 'Request received', [
            'user_id' => $user?->id,
            'tenant_id' => $this->tenantId($tenant),
            'available_tenants_count' => $this->countValue($availableTenants),
            'plan_status' => $this->planStatusValue($planAccess),
        ]);

        if ($user->hasPermission('system.dashboard', $tenant?->id)) {
            DebugStepLogger::step($request, $scope, 'System dashboard permission granted', [
                'user_id' => $user->id,
                'tenant_id' => $this->tenantId($tenant),
            ]);

            $counts = $overview->dashboardCounts();

            DebugStepLogger::step($request, $scope, 'System dashboard counts prepared', [
                'counts_keys' => array_keys($counts),
            ]);

            return view('admin.system.dashboard', [
                'tenant' => $tenant,
                'availableTenants' => $availableTenants,
                'counts' => $counts,
            ]);
        }

        DebugStepLogger::step($request, $scope, 'Tenant dashboard path selected', [
            'user_id' => $user->id,
            'tenant_id' => $this->tenantId($tenant),
        ]);

        $planStatus = is_array($planAccess) ? ($planAccess['status'] ?? null) : null;

        $canSeeOperationalData = $user->isSuperAdmin()
            || $planStatus === PlanAccessStatus::Active
            || $planStatus === PlanAccessStatus::Exempt;

        DebugStepLogger::step($request, $scope, 'Operational data access evaluated', [
            'user_id' => $user->id,
            'tenant_id' => $this->tenantId($tenant),
            'is_super_admin' => $user->isSuperAdmin(),
            'plan_status' => $this->planStatusValue($planAccess),
            'can_see_operational_data' => $canSeeOperationalData,
        ]);

        $query = $canSeeOperationalData ? $leads->baseQuery($user, $tenant) : null;

        DebugStepLogger::step($request, $scope, 'Lead query prepared', [
            'has_query' => $query !== null,
        ]);

        $leadCount = $query ? (clone $query)->count() : 0;
        $newLeadCount = $query ? (clone $query)->where('crm_stage', 'new_lead')->count() : 0;
        $followUpCount = $query ? (clone $query)->where('crm_stage', 'needs_follow_up')->count() : 0;
        $convertedCount = $query ? (clone $query)->where('crm_stage', 'converted')->count() : 0;
        $recentLeads = $query ? (clone $query)->with('tenant')->latest()->limit(6)->get() : new Collection();
        $serviceSummaries = $entitlements->summaryForSubscription($activeSubscription);

        DebugStepLogger::step($request, $scope, 'Tenant dashboard metrics prepared', [
            'lead_count' => $leadCount,
            'new_lead_count' => $newLeadCount,
            'follow_up_count' => $followUpCount,
            'converted_count' => $convertedCount,
            'recent_leads_count' => $recentLeads->count(),
            'service_summaries_count' => $this->countValue($serviceSummaries),
        ]);

        DebugStepLogger::step($request, $scope, 'Dashboard view rendered', [
            'view' => 'admin.dashboard',
        ]);

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'planAccess' => $planAccess,
            'activeSubscription' => $activeSubscription,
            'serviceSummaries' => $serviceSummaries,
            'leadCount' => $leadCount,
            'newLeadCount' => $newLeadCount,
            'followUpCount' => $followUpCount,
            'convertedCount' => $convertedCount,
            'recentLeads' => $recentLeads,
        ]);
    }

    /**
     * Show lead and tenant reports.
     */
    public function reports(Request $request, LeadDashboardService $leads): View
    {
        $scope = 'admin.dashboard.reports';

        $tenant = $request->attributes->get('adminTenant');
        $availableTenants = $request->attributes->get('adminTenants');
        $user = $request->user();

        DebugStepLogger::step($request, $scope, 'Request received', [
            'user_id' => $user?->id,
            'tenant_id' => $this->tenantId($tenant),
            'available_tenants_count' => $this->countValue($availableTenants),
            'is_super_admin' => $user?->isSuperAdmin(),
        ]);

        $query = $leads->baseQuery($user, $tenant);

        DebugStepLogger::step($request, $scope, 'Lead report query prepared', [
            'tenant_id' => $this->tenantId($tenant),
        ]);

        $stageCounts = (clone $query)
            ->selectRaw('crm_stage, count(*) as aggregate')
            ->groupBy('crm_stage')
            ->pluck('aggregate', 'crm_stage');

        DebugStepLogger::step($request, $scope, 'Stage counts prepared', [
            'stage_count_keys' => $stageCounts->keys()->values()->all(),
        ]);

        $tenantCounts = $user->isSuperAdmin()
            ? Tenant::query()->withCount('leads')->orderBy('name')->get()
            : collect();

        DebugStepLogger::step($request, $scope, 'Tenant counts prepared', [
            'tenant_counts_enabled' => $user->isSuperAdmin(),
            'tenant_counts_count' => $tenantCounts->count(),
        ]);

        DebugStepLogger::step($request, $scope, 'Reports view rendered', [
            'view' => 'admin.reports',
        ]);

        return view('admin.reports', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'stageCounts' => $stageCounts,
            'tenantCounts' => $tenantCounts,
        ]);
    }

    /**
     * Show dashboard settings.
     */
    public function settings(Request $request): View
    {
        $scope = 'admin.dashboard.settings';

        $tenant = $request->attributes->get('adminTenant');
        $availableTenants = $request->attributes->get('adminTenants');

        DebugStepLogger::step($request, $scope, 'Request received', [
            'user_id' => $request->user()?->id,
            'tenant_id' => $this->tenantId($tenant),
            'available_tenants_count' => $this->countValue($availableTenants),
        ]);

        DebugStepLogger::step($request, $scope, 'Settings view rendered', [
            'view' => 'admin.settings',
        ]);

        return view('admin.settings', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
        ]);
    }

    /**
     * Return a safe tenant ID for logs.
     */
    private function tenantId(mixed $tenant): ?int
    {
        return $tenant instanceof Tenant ? $tenant->id : null;
    }

    /**
     * Count arrays and collections safely for logs.
     */
    private function countValue(mixed $value): ?int
    {
        if ($value instanceof Collection) {
            return $value->count();
        }

        return is_countable($value) ? count($value) : null;
    }

    /**
     * Return a safe plan status value for logs.
     */
    private function planStatusValue(mixed $planAccess): ?string
    {
        if (! is_array($planAccess)) {
            return null;
        }

        $status = $planAccess['status'] ?? null;

        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if ($status instanceof \UnitEnum) {
            return $status->name;
        }

        return is_scalar($status) ? (string) $status : null;
    }
}