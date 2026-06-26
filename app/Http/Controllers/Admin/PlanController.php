<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Plans\StorePlanRequest;
use App\Http\Requests\Admin\Plans\UpdatePlanRequest;
use App\Models\Plan;
use App\Services\Billing\BillingService;
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

        return view('admin.plans.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'plans' => Plan::query()
                ->when(! $canManagePlans, fn ($query) => $query->where('status', 'active'))
                ->orderBy('price')
                ->paginate(20),
            'canManagePlans' => $canManagePlans,
            'activeSubscription' => $request->attributes->get('activeSubscription'),
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        Plan::create($request->validated() + ['features_json' => []]);

        return back()->with('status', __('plans.created'));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated() + ['features_json' => $plan->features_json ?? []]);

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
        $invoice = $billing->invoiceForPlan($request->user(), $plan);

        return redirect()
            ->route('admin.billing.index')
            ->with('status', __('plans.invoice_ready', ['number' => $invoice->number]));
    }
}
