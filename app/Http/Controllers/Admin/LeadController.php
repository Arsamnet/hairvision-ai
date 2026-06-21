<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leads\StoreLeadRequest;
use App\Http\Requests\Admin\Leads\UpdateLeadRequest;
use App\Models\Lead;
use App\Services\Admin\LeadDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Handles tenant-scoped lead management for the admin dashboard.
 *
 * @since 1.1.0
 */
class LeadController extends Controller
{
    public function index(Request $request, LeadDashboardService $leadService): View
    {
        Gate::authorize('viewAny', Lead::class);

        return view('admin.leads.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'leads' => $leadService->paginate($request, $request->user(), $request->attributes->get('adminTenant')),
        ]);
    }

    public function show(Request $request, Lead $lead): View
    {
        Gate::authorize('view', $lead);

        abort_unless($this->isLeadVisible($request, $lead), 404);

        return view('admin.leads.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'lead' => $lead->load(['tenant', 'assignedUser', 'uploads.generationGroup.jobs', 'generationGroups.assets', 'notes.user']),
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

        return redirect()->route('admin.leads.show', $lead)->with('status', 'لید جدید ثبت شد.');
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

        return redirect()->route('admin.leads.show', $lead)->with('status', 'لید به‌روزرسانی شد.');
    }

    /**
     * Ensure a route-bound lead is inside the active tenant context.
     */
    private function isLeadVisible(Request $request, Lead $lead): bool
    {
        $tenant = $request->attributes->get('adminTenant');

        if ($tenant) {
            return $lead->tenant_id === $tenant->id;
        }

        return $request->user()->canAccessTenant($lead->tenant_id);
    }
}
