<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanAccessStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Admin\LeadDashboardService;
use App\Services\Admin\SystemOverviewService;
use App\Services\Billing\BillingService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\ServiceEntitlementService;
use App\Services\Visitor\PublicApplicationAvailabilityService;
use App\Support\Tenancy\HostClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Renders admin dashboard pages.
 *
 * @since 1.0.0
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
        ServiceEntitlementService $entitlements,
        PublicApplicationAvailabilityService $availability
    ): View {
        $scope = 'admin.dashboard.index';

        $tenant = $request->attributes->get('adminTenant');
        $availableTenants = $request->attributes->get('adminTenants');
        $planAccess = $request->attributes->get('planAccess');
        $activeSubscription = $request->attributes->get('activeSubscription');
        $user = $request->user();

        if ($user->hasPermission('system.dashboard', $tenant?->id)) {
            $counts = $overview->dashboardCounts();

            return view('admin.system.dashboard', [
                'tenant' => $tenant,
                'availableTenants' => $availableTenants,
                'counts' => $counts,
            ]);
        }

        $planStatus = is_array($planAccess) ? ($planAccess['status'] ?? null) : null;

        $canSeeOperationalData = $user->isSuperAdmin()
            || $planStatus === PlanAccessStatus::Active
            || $planStatus === PlanAccessStatus::Exempt;

        $query = $canSeeOperationalData ? $leads->baseQuery($user, $tenant) : null;

        $leadCount = $query ? (clone $query)->count() : 0;
        $newLeadCount = $query ? (clone $query)->where('crm_stage', 'new_lead')->count() : 0;
        $followUpCount = $query ? (clone $query)->where('crm_stage', 'needs_follow_up')->count() : 0;
        $convertedCount = $query ? (clone $query)->where('crm_stage', 'converted')->count() : 0;
        $recentLeads = $query ? (clone $query)->with('tenant')->latest()->limit(6)->get() : new Collection;
        $serviceSummaries = $entitlements->summaryForSubscription($activeSubscription);
        $planSubscription = is_array($planAccess) ? ($planAccess['subscription'] ?? null) : null;
        $dashboardSubscription = $activeSubscription ?: $planSubscription;
        $applicationSummaries = $tenant instanceof Tenant
            ? $availability->summariesForSubscription($tenant, $dashboardSubscription)
            : collect();

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'planAccess' => $planAccess,
            'activeSubscription' => $activeSubscription,
            'planSubscription' => $planSubscription,
            'serviceSummaries' => $serviceSummaries,
            'applicationSummaries' => $applicationSummaries,
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

        $query = $leads->baseQuery($user, $tenant);

        $stageCounts = (clone $query)
            ->selectRaw('crm_stage, count(*) as aggregate')
            ->groupBy('crm_stage')
            ->pluck('aggregate', 'crm_stage');

        $tenantCounts = $user->isSuperAdmin()
            ? Tenant::query()->withCount('leads')->orderBy('name')->get()
            : collect();

        return view('admin.reports', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'stageCounts' => $stageCounts,
            'tenantCounts' => $tenantCounts,
        ]);
    }

    /**
     * Show the clinic credit usage report.
     */
    public function creditUsageReport(
        Request $request,
        BillingService $billing,
        ServiceEntitlementService $entitlements,
        CreditLedgerService $ledger
    ): View {
        $tenant = $request->attributes->get('adminTenant');
        $availableTenants = $request->attributes->get('adminTenants');
        $activeSubscription = HostClassifier::isPrimaryHost($request->getHost())
            ? null
            : ($request->attributes->get('activeSubscription') ?: $billing->activeSubscription($request->user(), $tenant));

        return view('admin.reports.credit-usage', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'activeSubscription' => $activeSubscription,
            'serviceSummaries' => $entitlements->summaryForSubscription($activeSubscription),
            'remainingCredit' => $entitlements->remainingCreditForSubscription($activeSubscription),
            'totalCredit' => $entitlements->totalCreditForSubscription($activeSubscription),
            'creditTimeline' => $ledger->timelineForSubscription($activeSubscription),
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
