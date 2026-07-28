<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Auth\PermissionCatalog;
use App\Services\Auth\PermissionService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use App\Support\StateCatalog;
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
    /**
     * Display the role listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function index(Request $request): View
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['name', 'code', 'scope', 'status', 'created_at'],
            'name',
            'asc'
        );
        $query = Role::query()
            ->platform()
            ->withCount('permissions')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));
        SearchFilter::apply($query, $pagination->search, ['name', 'code']);
        DateRangeFilter::apply($query, $request);

        return view('admin.system.roles.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'roles' => $query
                ->orderBy($pagination->sort, $pagination->direction)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
                ->withQueryString(),
        ]);
    }

    /**
     * Persist a new role record.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
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
            'scope' => 'platform',
            'is_system' => false,
            'status' => 'active',
        ]);

        return back()->with('status', __('permissions.role_created'));
    }

    /**
     * Display the form for editing the requested role.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Role  $role  The role value.
     * @param  PermissionCatalog  $catalog  Canonical permission definitions.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function edit(Request $request, Role $role, PermissionCatalog $catalog): View
    {
        abort_if($role->isClinicRole(), 404);

        return view('admin.system.roles.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'role' => $role->load('permissions'),
            'permissionGroupLabels' => $catalog->navigationGroups(),
            'permissions' => $catalog->groupForDisplay(
                Permission::query()->orderBy('group')->orderBy('code')->get()
            ),
        ]);
    }

    /**
     * Update the requested role record.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Role  $role  The role value.
     * @param  PermissionService  $permissions  The permissions value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function update(Request $request, Role $role, PermissionService $permissions): RedirectResponse
    {
        if ($role->isClinicRole()) {
            return back()->withErrors([
                'role' => __('permissions.clinic_role_permissions_locked'),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(StateCatalog::values('roles.statuses'))],
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

    /**
     * Delete the requested role record.
     *
     * @param  Role  $role  The role value.
     * @param  PermissionService  $permissions  The permissions value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function destroy(Role $role, PermissionService $permissions): RedirectResponse
    {
        if ($role->isClinicRole() || $role->is_system) {
            return back()->withErrors([
                'role' => __('permissions.protected_role_delete_denied'),
            ]);
        }

        $roleCode = $role->code;
        $role->delete();
        $permissions->forgetRole($roleCode);

        return redirect()->route('admin.system.roles.index')->with('status', __('permissions.role_deleted'));
    }
}
