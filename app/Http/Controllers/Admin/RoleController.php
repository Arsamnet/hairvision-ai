<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Auth\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages dashboard access levels and their permission assignments.
 *
 * @since 1.0.0
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.system.roles.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'roles' => Role::query()->withCount('permissions')->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('roles', 'code')],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        Role::create([
            'name' => $validated['name'],
            'code' => $validated['code'] ?? Str::slug($validated['name'], '_'),
            'description' => $validated['description'] ?? null,
            'is_system' => false,
            'status' => 'active',
        ]);

        return back()->with('status', __('permissions.role_created'));
    }

    public function edit(Request $request, Role $role): View
    {
        return view('admin.system.roles.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'role' => $role->load('permissions'),
            'permissions' => Permission::query()->orderBy('group')->orderBy('code')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role, PermissionService $permissions): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:active,inactive'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ]);
        $role->permissions()->sync($validated['permissions'] ?? []);
        $permissions->forgetRole($role->code);

        return redirect()->route('admin.system.roles.edit', $role)->with('status', __('permissions.role_updated'));
    }
}
