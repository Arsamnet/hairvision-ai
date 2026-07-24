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
     */
    public function __invoke(Request $request, SystemOverviewService $overview): View
    {
        return view('admin.system.reports.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'metrics' => $overview->managementMetrics(),
        ]);
    }
}
