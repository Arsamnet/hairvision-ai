<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminTenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lists dashboard users available in the active clinic context.
 *
 * @since 1.1.0
 */
class UserController extends Controller
{
    public function index(Request $request, AdminTenantContext $tenantContext): View
    {
        $user = $request->user();
        $tenant = $tenantContext->fromFilter($request, $user);

        abort_unless($user->isSuperAdmin() || in_array($user->roleForTenant($tenant?->id), ['clinic_admin'], true), 403);

        $users = $tenant
            ? $tenant->users()->orderBy('name')->get()
            : User::query()->with('tenants')->orderBy('name')->get();

        return view('admin.users.index', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'users' => $users,
        ]);
    }
}
