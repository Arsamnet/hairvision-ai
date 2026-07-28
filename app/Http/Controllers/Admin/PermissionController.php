<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\Auth\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lists canonical backend permissions for system managers.
 *
 * @since 1.0.0
 */
class PermissionController extends Controller
{
    /**
     * Handle the incoming permission request.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  PermissionCatalog  $catalog  Canonical permission definitions.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function __invoke(Request $request, PermissionCatalog $catalog): View
    {
        return view('admin.system.permissions.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'permissionGroupLabels' => $catalog->navigationGroups(),
            'permissions' => $catalog->groupForDisplay(
                Permission::query()->orderBy('group')->orderBy('code')->get()
            ),
        ]);
    }
}
