<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Plans\StorePlanRequest;
use App\Http\Requests\Admin\Plans\UpdatePlanRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AI\ClinicAiModelProvisioningService;
use App\Services\Billing\BillingService;
use App\Services\Billing\PlanCatalogService;
use App\Services\Billing\ServiceCatalog;
use App\Services\Billing\ServiceEntitlementService;
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
    public function index(Request $request, ClinicAiModelProvisioningService $modelProvisioning): View
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
            ->when($canManagePlans, fn ($query) => $query->with('aiModelAccesses'))
            ->when(! $canManagePlans, fn ($query) => $query->where('status', 'active'))
            ->when(! $canManagePlans, fn ($query) => $query->where('is_public', true))
            ->when(! $canManagePlans, fn ($query) => $query->whereIn('plan_type', ['base_subscription', 'service_add_on', 'credit_top_up']))
            ->when($request->filled('status') && $canManagePlans, fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('plan_type'), fn ($query) => $query->where('plan_type', $request->string('plan_type')->toString()));
        SearchFilter::apply($query, $pagination->search, ['name', 'description', 'currency']);
        DateRangeFilter::apply($query, $request);
        $services = app(ServiceCatalog::class);
        $plans = $query
            ->orderBy($pagination->sort, $pagination->direction)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();
        $tenant = $request->attributes->get('adminTenant');
        $modelAccessOptions = $canManagePlans
            ? $modelProvisioning->assignableAccesses()->groupBy('usage_category_key')
            : collect();

        return view('admin.plans.index', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'plans' => $plans,
            'canManagePlans' => $canManagePlans,
            'activeSubscription' => $request->attributes->get('activeSubscription'),
            'serviceOptions' => $services->options(),
            'serviceIcons' => $services->icons(),
            'modelAccessOptions' => $modelAccessOptions,
            'planPurchaseStates' => $this->purchaseStates(
                $plans->getCollection(),
                $tenant instanceof Tenant ? $tenant : null,
                app(ServiceEntitlementService::class),
            ),
        ]);
    }

    public function store(StorePlanRequest $request, PlanCatalogService $plans): RedirectResponse
    {
        $plans->create($request->validated());

        return back()->with('status', __('plans.created'));
    }

    public function show(Request $request, Plan $plan): View
    {
        $canManagePlans = $request->user()->hasAnyPermission(['plans.create', 'plans.edit', 'plans.delete', 'plans.manage']);
        abort_unless($canManagePlans || ($plan->status === 'active' && $plan->is_public), 404);

        $plan->load('serviceEntitlements');

        if ($canManagePlans) {
            $plan->load('aiModelAccesses');
        }

        return view('admin.plans.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'plan' => $plan,
            'serviceOptions' => app(ServiceCatalog::class)->options(),
            'serviceIcons' => app(ServiceCatalog::class)->icons(),
            'canManagePlans' => $canManagePlans,
        ]);
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
        $canManagePlans = $request->user()->hasAnyPermission(['plans.create', 'plans.edit', 'plans.delete', 'plans.manage']);
        abort_unless($canManagePlans || $plan->is_public, 404);

        $invoice = $billing->invoiceForPlan($request->user(), $plan, $request->attributes->get('adminTenant'));

        return redirect()
            ->route('admin.billing.index')
            ->with('status', __('plans.invoice_ready', ['number' => $invoice->number]));
    }

    /**
     * @param  iterable<int, Plan>  $plans
     * @return array<int, array{can_purchase: bool, reason: string|null}>
     */
    private function purchaseStates(iterable $plans, ?Tenant $tenant, ServiceEntitlementService $entitlements): array
    {
        $activeSubscription = $tenant ? $entitlements->activeBaseSubscriptionForTenant($tenant) : null;
        $states = [];

        foreach ($plans as $plan) {
            $serviceKeys = $plan->serviceKeys();
            $availableServices = collect($serviceKeys)
                ->filter(function (string $serviceKey) use ($tenant, $entitlements): bool {
                    if (! $tenant) {
                        return false;
                    }

                    return (bool) $entitlements->activeEntitlement($tenant, $serviceKey);
                })
                ->values()
                ->all();

            $hasAllServices = $serviceKeys !== [] && count($availableServices) === count($serviceKeys);
            $missingAnyService = count($availableServices) < count($serviceKeys);
            $canPurchase = $plan->status === 'active';
            $reason = null;

            if (! $canPurchase) {
                $reason = __('plans.purchase_disabled.inactive');
            } elseif ($plan->isCreditTopUp()) {
                if (! $activeSubscription || $missingAnyService) {
                    $canPurchase = false;
                    $reason = __('plans.purchase_disabled.top_up_requires_service');
                }
            } elseif (! $activeSubscription && $plan->isServiceAddOn()) {
                $canPurchase = false;
                $reason = __('plans.purchase_disabled.add_on_requires_base');
            } elseif ($activeSubscription?->plan_id === $plan->id) {
                $canPurchase = false;
                $reason = __('plans.same_active_plan_blocked');
            } elseif ($plan->isBaseSubscription() && $hasAllServices && $activeSubscription && $entitlements->totalCreditForSubscription($activeSubscription) >= (int) $plan->usage_limit) {
                $canPurchase = false;
                $reason = __('plans.purchase_disabled.already_available');
            } elseif (! $plan->isBaseSubscription() && $hasAllServices) {
                $canPurchase = false;
                $reason = __('plans.purchase_disabled.already_available');
            }

            $states[$plan->id] = [
                'can_purchase' => $canPurchase,
                'reason' => $reason,
            ];
        }

        return $states;
    }
}
