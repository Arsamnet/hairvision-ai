<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leads\StoreLeadRequest;
use App\Http\Requests\Admin\Leads\UpdateLeadRequest;
use App\Models\Lead;
use App\Services\Admin\LeadDashboardService;
use App\Support\Pagination\PaginatedResponse;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Handles tenant-scoped lead management for the admin dashboard.
 *
 * @since 1.0.0
 */
class LeadController extends Controller
{
    public function index(Request $request, LeadDashboardService $leadService): View|JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);
        $clinicFilter = $leadService->resolveClinicFilter($request, $request->user());

        if ($request->expectsJson()) {
            $pagination = PaginationParameters::fromRequest(
                $request,
                ['created_at', 'full_name', 'phone_normalized', 'crm_stage', 'status'],
                'created_at'
            );
            if ($pagination->search && ! $request->user()->hasPermission('leads.search', $clinicFilter?->id)) {
                abort(403, __('permissions.denied'));
            }

            $paginator = $leadService
                ->filteredQuery($request, $request->user(), $clinicFilter, $pagination)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page);

            return response()->json(PaginatedResponse::make($paginator));
        }

        return view('admin.leads.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinicFilter' => $clinicFilter,
            'assignableUsers' => $request->attributes->get('adminTenant')
                ? $request->attributes->get('adminTenant')->users()->wherePivot('status', 'active')->orderBy('name')->get()
                : collect(),
            'leads' => $leadService->paginate($request, $request->user(), $clinicFilter),
        ]);
    }

    public function show(Request $request, Lead $lead, LeadDashboardService $leadService): View
    {
        Gate::authorize('view', $lead);

        abort_unless($this->isLeadVisible($request, $lead), 404);

        return view('admin.leads.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'lead' => $leadService->loadDetails($lead),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Lead::class);

        $tenant = $request->attributes->get('adminTenant');
        abort_unless($tenant, 403);

        return view('admin.leads.create', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'assignableUsers' => $tenant->users()->wherePivot('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreLeadRequest $request, LeadDashboardService $leadService): RedirectResponse
    {
        $tenant = $request->attributes->get('adminTenant');
        abort_unless($tenant, 403);

        $lead = $leadService->create($request->validated(), $tenant, $request->user());

        if ($request->boolean('return_to_index')) {
            return redirect()->route('admin.leads.index')->with('status', __('leads.created'));
        }

        return redirect()->route('admin.leads.show', $lead)->with('status', __('leads.created'));
    }

    public function edit(Request $request, Lead $lead): View
    {
        Gate::authorize('update', $lead);
        abort_unless($this->isLeadVisible($request, $lead), 404);

        $tenant = $request->attributes->get('adminTenant') ?? $lead->tenant;

        return view('admin.leads.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'lead' => $lead,
            'assignableUsers' => $tenant->users()->wherePivot('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateLeadRequest $request, Lead $lead, LeadDashboardService $leadService): RedirectResponse
    {
        abort_unless($this->isLeadVisible($request, $lead), 404);

        $tenant = $request->attributes->get('adminTenant') ?? $lead->tenant;
        $leadService->update($lead, $request->validated(), $request->user(), $tenant);

        return redirect()->route('admin.leads.show', $lead)->with('status', __('leads.updated'));
    }

    /**
     * Ensure a route-bound lead is inside the active tenant context.
     */
    private function isLeadVisible(Request $request, Lead $lead): bool
    {
        return $request->user()->canAccessTenant($lead->tenant_id);
    }
}
