<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Plans\StorePlanRequest;
use App\Http\Requests\Admin\Plans\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanCatalogService;
use App\Services\Billing\ServiceCatalog;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lists purchasable plans and lets managers maintain plan definitions.
 *
 * @since 1.0.0
 */
class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $canManagePlans = $request->user()->hasAnyPermission(['plans.create', 'plans.edit', 'plans.delete', 'plans.manage']);
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'plan_type', 'price', 'duration_days', 'usage_limit', 'status'],
            'price',
            'asc'
        );
        $query = Plan::query()
            ->with('serviceEntitlements')
            ->when(! $canManagePlans, fn ($query) => $query->where('status', 'active'))
            ->when(! $canManagePlans, fn ($query) => $query->whereIn('plan_type', ['base_subscription', 'service_add_on', 'credit_top_up']))
            ->when($request->filled('status') && $canManagePlans, fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('plan_type'), fn ($query) => $query->where('plan_type', $request->string('plan_type')->toString()));
        SearchFilter::apply($query, $pagination->search, ['name', 'description', 'currency']);
        DateRangeFilter::apply($query, $request);
        $services = app(ServiceCatalog::class);

        return view('admin.plans.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'plans' => $query
                ->orderBy($pagination->sort, $pagination->direction)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
                ->withQueryString(),
            'canManagePlans' => $canManagePlans,
            'activeSubscription' => $request->attributes->get('activeSubscription'),
            'serviceOptions' => $services->options(),
        ]);
    }

    public function store(StorePlanRequest $request, PlanCatalogService $plans): RedirectResponse
    {
        $plans->create($request->validated());

        return back()->with('status', __('plans.created'));
    }

    public function update(UpdatePlanRequest $request, Plan $plan, PlanCatalogService $plans): RedirectResponse
    {
        $plans->update($plan, $request->validated());

        return back()->with('status', __('plans.updated'));
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(['plans.delete', 'plans.manage']), 403);

        $plan->delete();

        return back()->with('status', __('plans.deleted'));
    }

    public function purchase(Request $request, Plan $plan, BillingService $billing): RedirectResponse
    {
        $invoice = $billing->invoiceForPlan($request->user(), $plan, $request->attributes->get('adminTenant'));

        return redirect()
            ->route('admin.billing.index')
            ->with('status', __('plans.invoice_ready', ['number' => $invoice->number]));
    }
}
