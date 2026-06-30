<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Assigns users to global and tenant access levels.
 *
 * @since 1.0.0
 */
class AccessAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['name', 'email', 'phone_normalized', 'role', 'status', 'created_at'],
            'name',
            'asc'
        );
        $query = User::query()
            ->with('tenants')
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));
        SearchFilter::apply($query, $pagination->search, ['name', 'email', 'phone_normalized']);
        DateRangeFilter::apply($query, $request);

        return view('admin.system.access.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'users' => $query
                ->orderBy($pagination->sort, $pagination->direction)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
                ->withQueryString(),
            'roles' => Role::query()->where('status', 'active')->orderBy('name')->get(),
            'tenants' => Tenant::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $roleCodes = Role::query()->pluck('code')->all();
        $tenantIds = Tenant::query()->pluck('id')->all();

        $validated = $request->validate([
            'role' => ['required', Rule::in($roleCodes)],
            'status' => ['required', 'in:active,suspended,disabled'],
            'tenant_id' => ['nullable', Rule::in($tenantIds)],
            'tenant_role' => ['nullable', Rule::in($roleCodes)],
            'tenant_status' => ['nullable', 'in:active,inactive'],
        ]);

        $user->update([
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        if (! empty($validated['tenant_id']) && ! empty($validated['tenant_role'])) {
            $user->tenants()->syncWithoutDetaching([
                (int) $validated['tenant_id'] => [
                    'role' => $validated['tenant_role'],
                    'status' => $validated['tenant_status'] ?? 'active',
                ],
            ]);
            $user->tenants()->updateExistingPivot((int) $validated['tenant_id'], [
                'role' => $validated['tenant_role'],
                'status' => $validated['tenant_status'] ?? 'active',
            ]);
        }

        return back()->with('status', __('permissions.access_updated'));
    }
}
