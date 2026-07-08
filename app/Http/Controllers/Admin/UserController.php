<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\UserManagementService;
use App\Support\Pagination\PaginatedResponse;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Manages dashboard users available in the actor's clinic scope.
 *
 * @since 1.0.0
 */
class UserController extends Controller
{
    public function index(Request $request, UserManagementService $users): View|JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $clinicFilter = $users->resolveClinicFilter($request, $request->user());

        if ($request->expectsJson()) {
            $pagination = PaginationParameters::fromRequest(
                $request,
                ['created_at', 'name', 'email', 'phone_normalized', 'access_scope', 'role', 'status'],
                'created_at'
            );
            if ($pagination->search && ! $request->user()->hasPermission('users.search', $clinicFilter?->id)) {
                abort(403, __('permissions.denied'));
            }

            $paginator = $users
                ->filteredQuery($request, $request->user(), $clinicFilter, $pagination)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page);

            return response()->json(PaginatedResponse::make($paginator));
        }

        return view('admin.users.index', array_merge($this->formData($request), [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinicFilter' => $clinicFilter,
            'users' => $users->paginate($request, $request->user(), $clinicFilter),
        ]));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', $this->formData($request));
    }

    public function store(StoreUserRequest $request, UserManagementService $users): RedirectResponse
    {
        $created = $users->create(
            $request->validated(),
            $request->user(),
            $request->attributes->get('adminTenant')
        );

        if ($request->boolean('return_to_index')) {
            return redirect()->route('admin.users.index')->with('status', __('system.users.created'));
        }

        return redirect()->route('admin.users.show', $created)->with('status', __('system.users.created'));
    }

    public function show(Request $request, User $user): View
    {
        Gate::authorize('view', $user);

        return view('admin.users.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'dashboardUser' => $user->load(['tenants', 'smsGateways', 'subscriptions.plan']),
        ]);
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', $this->formData($request) + [
            'dashboardUser' => $user->load('tenants'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserManagementService $users): RedirectResponse
    {
        $users->update(
            $user,
            $request->validated(),
            $request->user(),
            $request->attributes->get('adminTenant')
        );

        return redirect()->route('admin.users.show', $user)->with('status', __('system.users.updated'));
    }

    public function destroy(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $users->delete($user, $request->user());

        return redirect()->route('admin.users.index')->with('status', __('system.users.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Request $request): array
    {
        $actor = $request->user();

        return [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'roles' => Role::query()
                ->where('status', 'active')
                ->when(! $actor->isSuperAdmin(), fn ($query) => $query->whereIn('code', ['clinic_admin', 'editor', 'viewer']))
                ->orderBy('name')
                ->get(),
            'systemRoles' => Role::query()
                ->where('status', 'active')
                ->platform()
                ->when(! $actor->isSuperAdmin(), fn ($query) => $query->whereRaw('1 = 0'))
                ->orderBy('name')
                ->get(),
            'clinicRoles' => Role::query()
                ->where('status', 'active')
                ->clinic()
                ->orderBy('name')
                ->get(),
            'tenants' => $actor->isSuperAdmin()
                ? Tenant::query()->where('status', 'active')->orderBy('name')->get()
                : $actor->tenants()->wherePivot('status', 'active')->where('tenants.status', 'active')->orderBy('tenants.name')->get(),
        ];
    }
}
