<?php

namespace App\Services\Admin;

use App\Models\Tenant;
use App\Services\Billing\ServiceEntitlementService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\SearchFilter;
use App\Support\StateCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Handles platform clinic management workflows.
 *
 * @since 1.0.0
 */
class ClinicManagementService
{
    public function __construct(private readonly ServiceEntitlementService $entitlements) {}

    /**
     * Paginate dashboard clinic records.
     */
    public function paginate(PaginationParameters $pagination, ?string $status = null): LengthAwarePaginator
    {
        $this->entitlements->expireEndedSubscriptions();

        return $this->query($pagination, $status)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
    }

    /**
     * Build a searchable clinic query.
     */
    public function query(PaginationParameters $pagination, ?string $status = null): Builder
    {
        $query = Tenant::query()
            ->with(['domains' => fn ($query) => $query->orderByDesc('status')->orderBy('id')])
            ->with([
                'subscriptions' => fn ($query) => $query
                    ->with(['plan.serviceEntitlements', 'user', 'serviceEntitlements'])
                    ->whereHas('plan', fn (Builder $query) => $query->where('plan_type', 'base_subscription'))
                    ->latest('starts_at')
                    ->latest('id'),
            ])
            ->withCount(['domains', 'users', 'leads', 'aiModelAccesses']);

        SearchFilter::apply($query, $pagination->search, ['name', 'slug'], ['domains' => ['domain']]);

        if (in_array($status, StateCatalog::values('tenants.statuses'), true)) {
            $query->where('status', $status);
        }

        return $query->orderBy($pagination->sort, $pagination->direction);
    }

    /**
     * Create a clinic and its primary domain.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => $data['status'],
            ]);

            $tenant->domains()->create([
                'domain' => $data['domain'],
                'type' => $data['domain_type'],
                'status' => $data['domain_status'],
                'ssl_status' => 'unknown',
                'verified_at' => $data['domain_status'] === 'active' ? now() : null,
            ]);

            return $tenant;
        });
    }

    /**
     * Update a clinic and its primary domain row.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        return DB::transaction(function () use ($tenant, $data): Tenant {
            $tenant->update([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => $data['status'],
            ]);

            $domain = $tenant->domains()->orderByDesc('status')->orderBy('id')->first();

            $attributes = [
                'domain' => $data['domain'],
                'type' => $data['domain_type'],
                'status' => $data['domain_status'],
                'ssl_status' => $domain?->ssl_status ?? 'unknown',
                'verified_at' => $data['domain_status'] === 'active'
                    ? ($domain?->verified_at ?? now())
                    : null,
            ];

            $domain
                ? $domain->update($attributes)
                : $tenant->domains()->create($attributes);

            return $tenant->refresh();
        });
    }

    /**
     * Delete a clinic and cascade tenant-owned data through database constraints.
     */
    public function delete(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
