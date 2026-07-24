<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SystemOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders platform-level management overview for authorized users.
 *
 * @since 1.0.0
 */
class SystemDashboardController extends Controller
{
    /**
     * Handle the incoming system dashboard request.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  SystemOverviewService  $overview  The overview value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function __invoke(Request $request, SystemOverviewService $overview): View
    {
        return view('admin.system.dashboard', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'counts' => $overview->dashboardCounts(),
        ]);
    }
}
