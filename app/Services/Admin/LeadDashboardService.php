<?php

namespace App\Services\Admin;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Builds tenant-scoped lead queries and dashboard mutations.
 *
 * @since 1.0.0
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
     */
    public function paginate(Request $request, User $user, ?Tenant $tenant): LengthAwarePaginator
    {
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'full_name', 'phone_normalized', 'crm_stage', 'status'],
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
     */
    public function filteredQuery(Request $request, User $user, ?Tenant $tenant, PaginationParameters $pagination): Builder
    {
        $query = $this->baseQuery($user, $tenant)->with(['assignedUser']);
        SearchFilter::apply($query, $pagination->search, ['full_name', 'phone_normalized', 'source']);

        $query
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('crm_stage'), fn (Builder $query) => $query->where('crm_stage', $request->string('crm_stage')->toString()));

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
     */
    public function create(array $data, Tenant $tenant, User $user): Lead
    {
        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'full_name' => $data['full_name'] ?? null,
            'phone' => $data['phone_normalized'],
            'phone_normalized' => $data['phone_normalized'],
            'source' => $data['source'] ?? 'dashboard',
            'phone_verified_at' => now(),
            'status' => $data['status'] ?? 'verified',
            'crm_stage' => $data['crm_stage'] ?? 'new_lead',
            'assigned_user_id' => $this->assignableUserId($data['assigned_user_id'] ?? null, $tenant),
        ]);

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
     */
    public function update(Lead $lead, array $data, User $user, Tenant $tenant): Lead
    {
        $role = $user->roleForTenant($tenant->id);
        $allowed = $role === 'editor'
            ? ['crm_stage', 'source']
            : ['full_name', 'phone_normalized', 'source', 'status', 'crm_stage', 'assigned_user_id'];

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
     */
    public function baseQuery(User $user, ?Tenant $tenant): Builder
    {
        $query = Lead::query();

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        } elseif (! $user->isSuperAdmin()) {
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
     */
    private function createNote(Lead $lead, User $user, string $note): LeadNote
    {
        $lead->forceFill(['crm_last_note_at' => now()])->save();

        return LeadNote::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'type' => 'admin_note',
            'status' => $lead->crm_stage,
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
}
