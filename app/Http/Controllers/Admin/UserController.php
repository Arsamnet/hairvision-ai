<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lists dashboard users available in the active clinic context.
 *
 * @since 1.1.0
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tenant = $request->attributes->get('adminTenant');

        abort_unless($user->isSuperAdmin() || in_array($user->roleForTenant($tenant?->id), ['clinic_admin'], true), 403);

        $users = $tenant
            ? $tenant->users()->orderBy('name')->paginate(15)
            : User::query()->with('tenants')->orderBy('name')->paginate(15);

        return view('admin.users.index', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'users' => $users,
        ]);
    }
}
