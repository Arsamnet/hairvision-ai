<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles active clinic switching for dashboard users.
 *
 * @since 1.1.0
 */
class TenantSwitchController extends Controller
{
    public function __invoke(Request $request, AdminTenantContext $tenantContext): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => ['required'],
        ]);

        $tenantContext->select($request, $request->user(), $validated['tenant_id']);

        return back()->with('status', __('dashboard.tenant_changed'));
    }
}
