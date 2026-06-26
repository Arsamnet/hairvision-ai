<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lists canonical backend permissions for system managers.
 *
 * @since 1.0.0
 */
class PermissionController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.system.permissions.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'permissions' => Permission::query()->orderBy('group')->orderBy('code')->get()->groupBy('group'),
        ]);
    }
}
