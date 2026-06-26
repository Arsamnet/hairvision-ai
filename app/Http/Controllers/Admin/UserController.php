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

        abort_unless($user->hasPermission('users.list', $tenant?->id), 403);

        $users = $tenant
            ? $tenant->users()->orderBy('name')->paginate(15)
            : User::query()
                ->with('tenants')
                ->when(! $user->hasPermission('users.manage'), function ($query) use ($user): void {
                    $tenantIds = $user->tenants()->wherePivot('status', 'active')->pluck('tenants.id');
                    $query->whereHas('tenants', fn ($tenantQuery) => $tenantQuery->whereIn('tenants.id', $tenantIds));
                })
                ->orderBy('name')
                ->paginate(15);

        return view('admin.users.index', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'users' => $users,
        ]);
    }
}
