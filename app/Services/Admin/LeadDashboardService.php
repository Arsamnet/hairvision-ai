<?php

namespace App\Services\Admin;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\NumberNormalizer;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Builds tenant-scoped lead queries and dashboard mutations.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class LeadDashboardService
{
    /**
     * Create a new lead dashboard service instance.
     *
     * @param  ClinicFilterResolver  $clinicFilterResolver  The clinic filter resolver value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly ClinicFilterResolver $clinicFilterResolver) {}

    /**
     * Paginate leads visible to the user in the active tenant context.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     * @return LengthAwarePaginator Paginated result set.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function paginate(Request $request, User $user, ?Tenant $tenant): LengthAwarePaginator
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'full_name', 'phone_normalized', 'age', 'status'],
            'created_at'
        );

        if ($pagination->search && ! $user->hasPermission('leads.search', $tenant?->id)) {
            abort(403, __('permissions.denied'));
        }

        return $this->filteredQuery($request, $user, $tenant, $pagination)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
    }

    /**
     * Build filtered query for paginated Blade or JSON responses.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     * @param  PaginationParameters  $pagination  The pagination value.
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     * @since 2.1.1 Eager-loads the latest CRM note for tenant-safe list tooltips.
     */
    public function filteredQuery(Request $request, User $user, ?Tenant $tenant, PaginationParameters $pagination): Builder
    {
        $query = $this->baseQuery($user, $tenant)->with(['assignedUser', 'latestNote']);
        SearchFilter::apply($query, $pagination->search, ['full_name', 'phone_normalized', 'city', 'source']);
        $ageFrom = $this->ageBound($request, 'age_from');
        $ageUntil = $this->ageBound($request, 'age_until');

        $query
            ->when(
                $request->filled('status'),
                fn (Builder $query) => $query->where('status', $request->string('status')->toString())
            )
            ->when($ageFrom !== null, fn (Builder $query) => $query->where('age', '>=', $ageFrom))
            ->when($ageUntil !== null, fn (Builder $query) => $query->where('age', '<=', $ageUntil));

        DateRangeFilter::apply($query, $request);

        return $query
            ->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * Resolve an optional clinic filter without trusting arbitrary tenant input.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  User  $user  Authenticated user performing the operation.
     * @return Tenant|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function resolveClinicFilter(Request $request, User $user): ?Tenant
    {
        return $this->clinicFilterResolver->resolve($request, $user);
    }

    /**
     * Load the legacy and version 2 records shown on a lead detail page.
     *
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return Lead Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function loadDetails(Lead $lead): Lead
    {
        return $lead->load([
            'tenant',
            'assignedUser',
            'uploads' => fn ($query) => $query
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->latest('created_at'),
            'generationGroups' => fn ($query) => $query
                ->where('tenant_id', $lead->tenant_id)
                ->with([
                    'upload' => fn ($uploadQuery) => $uploadQuery
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('lead_id', $lead->id),
                    'jobs' => fn ($jobQuery) => $jobQuery
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('lead_id', $lead->id)
                        ->oldest('created_at'),
                    'assets' => fn ($assetQuery) => $assetQuery
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('lead_id', $lead->id)
                        ->oldest('created_at'),
                ])
                ->latest('created_at'),
            'notes.user',
            'sourceImages' => fn ($query) => $query
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->with([
                    'generationRequests' => fn ($requestQuery) => $requestQuery
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('lead_id', $lead->id)
                        ->with([
                            'category',
                            'output',
                            'assets' => fn ($assetQuery) => $assetQuery
                                ->where('tenant_id', $lead->tenant_id)
                                ->where('lead_id', $lead->id)
                                ->latest('version')
                                ->latest('id'),
                        ])
                        ->latest('requested_at')
                        ->latest('id'),
                ])
                ->latest('version'),
        ]);
    }

    /**
     * Create a tenant-owned lead from dashboard input.
     *
     * @param  array<string, mixed>  $data
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  User  $user  Authenticated user performing the operation.
     * @return Lead Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function create(array $data, Tenant $tenant, User $user): Lead
    {
        $lead = Lead::query()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'phone_normalized' => $data['phone_normalized'],
            ],
            [
                'full_name' => $data['full_name'] ?? null,
                'age' => $data['age'] ?? null,
                'city' => $data['city'] ?? null,
                'phone' => $data['phone_normalized'],
                'source' => $data['source'] ?? 'dashboard',
                'phone_verified_at' => now(),
                'status' => $data['status'] ?? 'new',
                'assigned_user_id' => $this->assignableUserId($data['assigned_user_id'] ?? null, $tenant),
            ],
        );

        if (! $lead->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'phone_normalized' => __('validation.custom.phone_normalized.unique'),
            ]);
        }

        if (! empty($data['note'])) {
            $this->createNote($lead, $user, (string) $data['note']);
        }

        return $lead;
    }

    /**
     * Update lead fields while respecting limited editor access.
     *
     * @param  array<string, mixed>  $data
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return Lead Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function update(Lead $lead, array $data, User $user, Tenant $tenant): Lead
    {
        $role = $user->roleForTenant($tenant->id);
        $allowed = $role === 'editor'
            ? ['status', 'source']
            : ['full_name', 'age', 'city', 'phone_normalized', 'source', 'status', 'assigned_user_id'];

        $updates = array_intersect_key($data, array_flip($allowed));

        if (array_key_exists('phone_normalized', $updates)) {
            $updates['phone'] = $updates['phone_normalized'];
        }

        if (array_key_exists('assigned_user_id', $updates)) {
            $updates['assigned_user_id'] = $this->assignableUserId($updates['assigned_user_id'], $tenant);
        }

        $lead->fill($updates)->save();

        if (! empty($data['note'])) {
            $this->createNote($lead, $user, (string) $data['note']);
        }

        return $lead->refresh();
    }

    /**
     * Query leads visible in the active tenant context.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  Tenant|null  $tenant  Tenant in the current operation scope.
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function baseQuery(User $user, ?Tenant $tenant): Builder
    {
        $query = Lead::query();

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        } elseif (! $user->isSystemScoped()) {
            $tenantIds = $user->tenants()
                ->wherePivot('status', 'active')
                ->pluck('tenants.id');

            $query->whereIn('tenant_id', $tenantIds);
        }

        return $query;
    }

    /**
     * Store a tenant-owned admin note for a lead.
     *
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  User  $user  Authenticated user performing the operation.
     * @param  string  $note  The note value.
     * @return LeadNote Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    private function createNote(Lead $lead, User $user, string $note): LeadNote
    {
        $lead->forceFill(['crm_last_note_at' => now()])->save();

        return LeadNote::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'type' => 'admin_note',
            'status' => $lead->status,
            'note' => $note,
        ]);
    }

    /**
     * Confirm an assigned user belongs to the current tenant.
     *
     * @param  mixed  $userId  The user id value.
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @return int|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function assignableUserId(mixed $userId, Tenant $tenant): ?int
    {
        if (! $userId) {
            return null;
        }

        return $tenant->users()
            ->where('users.id', (int) $userId)
            ->wherePivot('status', 'active')
            ->value('users.id');
    }

    /**
     * Normalize a supported-digit age filter within the stored lead age range.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $key  Filter query key.
     * @return int|null Normalized age boundary or null when absent/invalid.
     *
     * @since 2.1.0
     */
    private function ageBound(Request $request, string $key): ?int
    {
        if (! $request->filled($key)) {
            return null;
        }

        $value = NumberNormalizer::normalizeNumericString((string) $request->input($key));

        if (! ctype_digit($value)) {
            return null;
        }

        $age = (int) $value;

        return $age <= 150 ? $age : null;
    }
}
