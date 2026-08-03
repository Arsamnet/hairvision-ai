<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SystemOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows system-wide reports separated from user-scoped reports.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class ManagementReportController extends Controller
{
    /**
     * Handle the incoming management report request.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SystemOverviewService  $overview  The overview value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function __invoke(Request $request, SystemOverviewService $overview): View
    {
        abort_unless($request->user()?->isSystemScoped(), 403);

        return view('admin.system.reports.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'metrics' => $overview->managementMetrics(),
            'clinicRows' => $overview->clinicPerformanceSummary(),
        ]);
    }
}
