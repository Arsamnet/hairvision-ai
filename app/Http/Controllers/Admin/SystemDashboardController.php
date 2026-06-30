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
    public function __invoke(Request $request, SystemOverviewService $overview): View
    {
        return view('admin.system.dashboard', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'counts' => $overview->dashboardCounts(),
        ]);
    }
}
