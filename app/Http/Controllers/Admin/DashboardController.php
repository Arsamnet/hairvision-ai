<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanAccessStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\PerformanceReportRequest;
use App\Models\Tenant;
use App\Services\Admin\LeadDashboardService;
use App\Services\Admin\SystemOverviewService;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiModelUsageReportService;
use App\Services\Analytics\ClinicPerformanceReportService;
use App\Services\Billing\BillingService;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\ServiceEntitlementService;
use App\Services\Visitor\PublicApplicationAvailabilityService;
use App\Support\StateCatalog;
use App\Support\Tenancy\HostClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Renders admin dashboard pages.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class DashboardController extends Controller
{
    /**
     * Show the main dashboard for system or tenant users.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  LeadDashboardService  $leads  The leads value.
     * @param  SystemOverviewService  $overview  The overview value.
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     * @param  PublicApplicationAvailabilityService  $availability  The availability value.
     * @param  ClinicPerformanceReportService  $performanceReports  First-party clinic performance builder.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function index(
        Request $request,
        LeadDashboardService $leads,
        SystemOverviewService $overview,
        ServiceEntitlementService $entitlements,
        PublicApplicationAvailabilityService $availability,
        ClinicPerformanceReportService $performanceReports,
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
        $newLeadCount = $query ? (clone $query)->where('status', 'new')->count() : 0;
        $followUpCount = $query ? (clone $query)->where('status', 'needs_follow_up')->count() : 0;
        $successfulLeadCount = $query ? (clone $query)->where('status', 'successful')->count() : 0;
        $recentLeads = $query ? (clone $query)->with('tenant')->latest()->limit(6)->get() : new Collection;
        $serviceSummaries = $entitlements->summaryForSubscription($activeSubscription);
        $planSubscription = is_array($planAccess) ? ($planAccess['subscription'] ?? null) : null;
        $dashboardSubscription = $activeSubscription ?: $planSubscription;
        $applicationSummaries = $tenant instanceof Tenant
            ? $availability->summariesForSubscription($tenant, $dashboardSubscription)
            : collect();
        $recentPerformance = $canSeeOperationalData && $tenant instanceof Tenant
            ? $performanceReports->recentDailySummary($tenant)
            : null;

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'planAccess' => $planAccess,
            'activeSubscription' => $activeSubscription,
            'planSubscription' => $planSubscription,
            'serviceSummaries' => $serviceSummaries,
            'applicationSummaries' => $applicationSummaries,
            'recentPerformance' => $recentPerformance,
            'leadCount' => $leadCount,
            'newLeadCount' => $newLeadCount,
            'followUpCount' => $followUpCount,
            'successfulLeadCount' => $successfulLeadCount,
            'recentLeads' => $recentLeads,
        ]);
    }

    /**
     * Show lead and tenant reports.
     *
     * @param  PerformanceReportRequest  $request  Validated report request.
     * @param  ClinicPerformanceReportService  $reports  First-party clinic funnel report builder.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function reports(
        PerformanceReportRequest $request,
        ClinicPerformanceReportService $reports,
    ): View {
        $tenant = $request->attributes->get('adminTenant');
        $availableTenants = $request->attributes->get('adminTenants');
        $filters = $request->validated();
        $isSystemReport = (bool) $request->user()?->isSystemScoped();
        $reportTenant = $this->performanceReportTenant($request, $filters);
        $range = $reports->range($filters);

        return view('admin.reports', [
            'tenant' => $tenant,
            'availableTenants' => $availableTenants,
            'reportTenants' => $isSystemReport ? Tenant::query()->orderBy('name')->get() : collect(),
            'range' => $range,
            'quickRanges' => $reports->quickRanges(),
            'reportTenant' => $reportTenant,
            'isSystemReport' => $isSystemReport,
            'report' => $reportTenant instanceof Tenant
                ? $reports->report($reportTenant, $range['from'], $range['until'])
                : $reports->systemReport($range['from'], $range['until']),
        ]);
    }

    /**
     * Download the selected clinic performance range as a UTF-8 CSV report.
     *
     * @param  PerformanceReportRequest  $request  Validated report request.
     * @param  ClinicPerformanceReportService  $reports  First-party clinic funnel report builder.
     * @return StreamedResponse Streamed CSV download.
     *
     * @since 2.1.0
     */
    public function exportPerformance(
        PerformanceReportRequest $request,
        ClinicPerformanceReportService $reports,
    ): StreamedResponse {
        $filters = $request->validated();
        $isSystemReport = (bool) $request->user()?->isSystemScoped();
        $reportTenant = $this->performanceReportTenant($request, $filters);

        $range = $reports->range($filters);
        $report = $reportTenant instanceof Tenant
            ? $reports->report($reportTenant, $range['from'], $range['until'])
            : $reports->systemReport($range['from'], $range['until']);
        $scopeLabel = $reportTenant?->name ?? __('reports.all_clinics');
        $filename = $reportTenant instanceof Tenant
            ? 'hairvision-clinic-performance.csv'
            : 'hairvision-system-performance.csv';

        return response()->streamDownload(function () use ($report, $range, $scopeLabel): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [__('reports.summary')]);
            fputcsv($stream, [__('reports.report_scope'), $scopeLabel]);
            fputcsv($stream, [
                __('reports.range'),
                __('reports.funnel.landing_view'),
                __('reports.total_application_clicks'),
                __('reports.application_visitors'),
                __('reports.funnel.photo_selected'),
                __('reports.funnel.phone_submitted'),
                __('reports.funnel.otp_verified'),
                __('reports.funnel.photo_uploaded'),
                __('reports.funnel.generation_requested'),
                __('reports.funnel.result_viewed'),
                __('reports.overall_conversion_rate'),
            ]);
            fputcsv($stream, [
                $range['date_from'].' - '.$range['date_until'],
                $report['totals']['landing_views'],
                $report['totals']['application_clicks'],
                $report['totals']['application_visitors'],
                $report['totals']['photo_selected'],
                $report['totals']['phone_submitted'],
                $report['totals']['otp_verified'],
                $report['totals']['photo_uploaded'],
                $report['totals']['generation_requested'],
                $report['totals']['result_viewed'],
                $report['totals']['overall_conversion_rate'].'%',
            ]);

            fputcsv($stream, []);
            fputcsv($stream, [__('reports.monthly_table_title')]);
            fputcsv($stream, [
                __('reports.month'),
                __('reports.funnel.landing_view'),
                __('reports.total_application_clicks'),
                __('reports.application_visitors'),
                __('reports.funnel.photo_selected'),
                __('reports.funnel.phone_submitted'),
                __('reports.funnel.otp_verified'),
                __('reports.funnel.photo_uploaded'),
                __('reports.funnel.generation_requested'),
                __('reports.funnel.result_viewed'),
                __('reports.overall_conversion_rate'),
            ]);

            foreach ($report['monthly'] as $row) {
                fputcsv($stream, [
                    $row['month'],
                    $row['landing_views'],
                    $row['application_clicks'],
                    $row['application_clicked'],
                    $row['photo_selected'],
                    $row['phone_submitted'],
                    $row['otp_verified'],
                    $row['photo_uploaded'],
                    $row['generation_requested'],
                    $row['result_viewed'],
                    $row['conversion_rate'].'%',
                ]);
            }

            fputcsv($stream, []);
            fputcsv($stream, [__('reports.application_clicks_title')]);
            fputcsv($stream, [
                __('reports.application'),
                __('reports.application_code'),
                __('reports.total_application_clicks'),
                __('reports.unique_visitors'),
                __('reports.generation_visitors'),
                __('reports.conversion'),
            ]);

            foreach ($report['applications'] as $application) {
                fputcsv($stream, [
                    $application['name'],
                    $application['code'],
                    $application['clicks'],
                    $application['unique_visitors'],
                    $application['generation_visitors'],
                    $application['conversion_rate'].'%',
                ]);
            }

            fputcsv($stream, []);
            fputcsv($stream, [__('reports.leads_by_status')]);
            fputcsv($stream, [__('reports.lead_status'), __('reports.lead_count')]);

            foreach ($report['statuses'] as $status => $count) {
                fputcsv($stream, [StateCatalog::label('leads.statuses', (string) $status), $count]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Show the clinic credit usage report.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  BillingService  $billing  The billing value.
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     * @param  CreditLedgerService  $ledger  The ledger value.
     * @param  AiModelResolver  $resolver  The resolver value.
     * @param  AiModelUsageReportService  $usageReports  The usage reports value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function creditUsageReport(
        Request $request,
        BillingService $billing,
        ServiceEntitlementService $entitlements,
        CreditLedgerService $ledger,
        AiModelResolver $resolver,
        AiModelUsageReportService $usageReports,
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
            'resolver' => $resolver,
            'usageLogs' => $tenant instanceof Tenant
                ? $usageReports->forClinic($tenant, 'model_usage_page')
                : null,
        ]);
    }

    /**
     * Resolve the validated tenant filter or the clinic user's enforced tenant.
     *
     * @param  PerformanceReportRequest  $request  Authorized report request.
     * @param  array<string, mixed>  $filters  Validated report filters.
     * @return Tenant|null Selected tenant, or null for an authorized system aggregate.
     *
     * @since 2.1.0
     */
    private function performanceReportTenant(PerformanceReportRequest $request, array $filters): ?Tenant
    {
        if ($request->user()?->isSystemScoped()) {
            return filled($filters['clinic_id'] ?? null)
                ? Tenant::query()->findOrFail((int) $filters['clinic_id'])
                : null;
        }

        $tenant = $request->attributes->get('adminTenant');
        abort_unless($tenant instanceof Tenant, 404);

        return $tenant;
    }

    /**
     * Return a safe tenant ID for logs.
     *
     * @param  mixed  $tenant  Tenant in the current operation scope.
     * @return int|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function tenantId(mixed $tenant): ?int
    {
        return $tenant instanceof Tenant ? $tenant->id : null;
    }

    /**
     * Count arrays and collections safely for logs.
     *
     * @param  mixed  $value  Input value supplied to the operation.
     * @return int|null Resolved operation result.
     *
     * @since 1.0.0
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
     *
     * @param  mixed  $planAccess  The plan access value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
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
