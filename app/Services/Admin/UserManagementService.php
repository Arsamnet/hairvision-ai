<?php

namespace App\Services\Admin;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Coordinates dashboard user queries and tenant access assignment.
 *
 * @since 1.0.0
 */
class UserManagementService
{
    public function __construct(private readonly ClinicFilterResolver $clinicFilterResolver) {}

    /**
     * Paginate dashboard users visible to the actor.
     */
    public function paginate(Request $request, User $actor, ?Tenant $clinicFilter = null): LengthAwarePaginator
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'email', 'phone_normalized', 'role', 'status'],
            'created_at'
        );

        if ($pagination->search && ! $actor->hasPermission('users.search', $clinicFilter?->id)) {
            abort(403, __('permissions.denied'));
        }

        return $this->filteredQuery($request, $actor, $clinicFilter, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
    }

    /**
     * Build the filtered user query for Blade or JSON responses.
     */
    public function filteredQuery(Request $request, User $actor, ?Tenant $clinicFilter, PaginationParameters $pagination): Builder
    {
        return $this->baseQuery($actor, $clinicFilter)
            ->with('tenants')
            ->when($pagination->search, function (Builder $query) use ($pagination): void {
                $search = '%'.$pagination->search.'%';
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('phone_normalized', 'like', $search);
                });
            })
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role', $request->string('role')->toString()))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * Create a dashboard user and attach the requested allowed tenant access.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?Tenant $currentTenant = null): User
    {
        return DB::transaction(function () use ($data, $actor, $currentTenant): User {
            $role = $this->authorizedGlobalRole((string) $data['role'], $actor);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_normalized' => $data['phone_normalized'] ?? null,
                'password' => Hash::make(Str::random(40)),
                'role' => $role,
                'status' => $data['status'],
            ]);

            $this->syncTenantAccess($user, $data, $actor, $currentTenant);

            return $user->refresh();
        });
    }

    /**
     * Update a dashboard user and their allowed tenant access.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, User $actor, ?Tenant $currentTenant = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor, $currentTenant): User {
            $role = $this->authorizedGlobalRole((string) $data['role'], $actor);

            $this->guardLastActiveSuperAdmin($user, $role, (string) $data['status']);

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone_normalized' => $data['phone_normalized'] ?? null,
                'role' => $role,
                'status' => $data['status'],
            ]);

            $this->syncTenantAccess($user, $data, $actor, $currentTenant);

            return $user->refresh();
        });
    }

    /**
     * Delete a dashboard user after safety checks.
     */
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages([
                'user' => __('system.users.cannot_delete_self'),
            ]);
        }

        $this->guardLastActiveSuperAdmin($user, 'deleted', 'deleted');

        $user->delete();
    }

    /**
     * Resolve an optional clinic filter without trusting arbitrary tenant input.
     */
    public function resolveClinicFilter(Request $request, User $actor): ?Tenant
    {
        return $this->clinicFilterResolver->resolve($request, $actor);
    }

    /**
     * Query users visible to the actor in an optional clinic filter.
     */
    private function baseQuery(User $actor, ?Tenant $clinicFilter): Builder
    {
        $query = User::query();

        if ($clinicFilter) {
            return $query->whereHas('tenants', fn (Builder $tenantQuery) => $tenantQuery->whereKey($clinicFilter->id));
        }

        if (! $actor->isSuperAdmin()) {
            $tenantIds = $this->allowedTenantIds($actor);

            $query->whereHas('tenants', fn (Builder $tenantQuery) => $tenantQuery->whereIn('tenants.id', $tenantIds));
        }

        return $query;
    }

    /**
     * @return array<int, int>
     */
    private function allowedTenantIds(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return Tenant::query()->where('status', 'active')->pluck('id')->all();
        }

        return $actor->tenants()
            ->wherePivot('status', 'active')
            ->where('tenants.status', 'active')
            ->pluck('tenants.id')
            ->all();
    }

    private function authorizedGlobalRole(string $role, User $actor): string
    {
        if ($role === 'super_admin' && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'role' => __('permissions.denied'),
            ]);
        }

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTenantAccess(User $user, array $data, User $actor, ?Tenant $currentTenant): void
    {
        $tenantIds = collect($data['tenant_ids'] ?? [])
            ->filter(fn (mixed $tenantId): bool => filled($tenantId))
            ->map(fn (mixed $tenantId): int => (int) $tenantId)
            ->unique()
            ->values();

        if ($tenantIds->isEmpty() && $currentTenant && $user->role !== 'super_admin') {
            $tenantIds = collect([$currentTenant->id]);
        }

        $allowedTenantIds = $this->allowedTenantIds($actor);
        $sync = [];

        foreach ($tenantIds as $tenantId) {
            if (! in_array($tenantId, $allowedTenantIds, true)) {
                throw ValidationException::withMessages([
                    'tenant_ids' => __('permissions.tenant_denied'),
                ]);
            }

            $tenantRole = (string) data_get($data, "tenant_roles.$tenantId", $user->role);

            if ($tenantRole === 'super_admin') {
                $tenantRole = 'clinic_admin';
            }

            $sync[$tenantId] = [
                'role' => $tenantRole,
                'status' => (string) data_get($data, "tenant_statuses.$tenantId", 'active'),
            ];
        }

        if ($actor->isSuperAdmin()) {
            $user->tenants()->sync($sync);

            return;
        }

        $allowedExisting = $user->tenants()->whereIn('tenants.id', $allowedTenantIds)->pluck('tenants.id')->all();
        $user->tenants()->detach($allowedExisting);

        if ($sync !== []) {
            $user->tenants()->syncWithoutDetaching($sync);
        }
    }

    private function guardLastActiveSuperAdmin(User $user, string $newRole, string $newStatus): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        if ($newRole === 'super_admin' && $newStatus === 'active') {
            return;
        }

        $activeSuperAdmins = User::query()
            ->where('role', 'super_admin')
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->count();

        if ($activeSuperAdmins === 0) {
            throw ValidationException::withMessages([
                'user' => __('system.users.cannot_remove_last_super_admin'),
            ]);
        }
    }
}
