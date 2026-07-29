<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Clinics\AssignClinicPlanRequest;
use App\Http\Requests\Admin\Clinics\StoreClinicInvoiceRequest;
use App\Http\Requests\Admin\Clinics\StoreClinicRequest;
use App\Http\Requests\Admin\Clinics\StoreClinicSmsGatewayRequest;
use App\Http\Requests\Admin\Clinics\UpdateClinicInvoiceRequest;
use App\Http\Requests\Admin\Clinics\UpdateClinicRequest;
use App\Models\AiModelAccess;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Admin\ClinicManagementService;
use App\Services\Admin\SmsGatewayManagementService;
use App\Services\AI\AiModelResolver;
use App\Services\AI\AiModelUsageReportService;
use App\Services\Billing\BillingService;
use App\Services\Billing\ServiceCatalog;
use App\Services\Sms\SmsGatewayService;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Manages clinic tenants from the platform dashboard.
 *
 * @since 1.0.0
 */
class ClinicController extends Controller
{
    /**
     * Display the clinic listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  ClinicManagementService  $clinics  The clinics value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function index(Request $request, ClinicManagementService $clinics): View
    {
        Gate::authorize('viewAny', Tenant::class);

        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'slug', 'status'],
            'created_at'
        );

        if ($pagination->search && ! $request->user()->hasPermission('tenants.search')) {
            abort(403, __('permissions.denied'));
        }

        return view('admin.clinics.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinics' => $clinics->paginate($pagination, $request->string('status')->toString()),
        ]);
    }

    /**
     * Persist a new clinic record.
     *
     * @param  StoreClinicRequest  $request  Incoming HTTP request.
     * @param  ClinicManagementService  $clinics  The clinics value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function store(StoreClinicRequest $request, ClinicManagementService $clinics): RedirectResponse
    {
        $clinic = $clinics->create($request->validated());

        return redirect()
            ->route('admin.clinics.edit', $clinic)
            ->with('status', __('clinics.messages.created'));
    }

    /**
     * Display the requested clinic resource.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  ServiceCatalog  $services  The services value.
     * @param  AiModelResolver  $resolver  The resolver value.
     * @param  SmsGatewayManagementService  $smsGateways  The sms gateways value.
     * @param  AiModelUsageReportService  $usageReports  The usage reports value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function show(
        Request $request,
        Tenant $clinic,
        ServiceCatalog $services,
        AiModelResolver $resolver,
        SmsGatewayManagementService $smsGateways,
        AiModelUsageReportService $usageReports,
    ): View {
        Gate::authorize('view', $clinic);

        return view('admin.clinics.show', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinic' => $clinic->load([
                'domains' => fn ($query) => $query->orderByDesc('status')->orderBy('id'),
                'invoices' => fn ($query) => $query
                    ->with(['user', 'plan', 'payments.gateway'])
                    ->latest('created_at')
                    ->latest('id'),
                'subscriptions' => fn ($query) => $query
                    ->with(['plan.serviceEntitlements', 'user', 'serviceEntitlements'])
                    ->whereHas('plan', fn ($query) => $query->where('plan_type', 'base_subscription'))
                    ->latest('starts_at')
                    ->latest('id'),
                'users' => fn ($query) => $query
                    ->wherePivot('status', 'active')
                    ->where('users.status', 'active')
                    ->orderBy('users.name'),
            ])->loadCount(['domains', 'users', 'leads', 'aiModelAccesses']),
            'primaryDomain' => $clinic->domains()->orderByDesc('status')->orderBy('id')->first(),
            'plans' => Plan::query()
                ->with('serviceEntitlements')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'serviceOptions' => $services->options(),
            'resolver' => $resolver,
            'assignedAccesses' => $clinic->aiModelAccesses()
                ->withPivot(['is_active', 'assigned_by'])
                ->orderBy('usage_category_key')
                ->orderBy('name')
                ->get(),
            'availableAccesses' => AiModelAccess::query()
                ->active()
                ->orderBy('usage_category_key')
                ->orderBy('name')
                ->get(),
            'clinicSmsGateway' => $smsGateways->clinicSettings($clinic),
            'smsProviderOptions' => $smsGateways->providerOptionsForPrivate($smsGateways->clinicSettings($clinic)),
            'usageLogs' => $usageReports->forClinic($clinic, 'clinic_usage_page'),
        ]);
    }

    /**
     * Display the form for editing the requested clinic.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function edit(Request $request, Tenant $clinic): View
    {
        Gate::authorize('update', $clinic);

        return view('admin.clinics.edit', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'clinic' => $clinic->load('domains'),
            'primaryDomain' => $clinic->domains()->orderByDesc('status')->orderBy('id')->first(),
        ]);
    }

    /**
     * Update the requested clinic record.
     *
     * @param  UpdateClinicRequest  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  ClinicManagementService  $clinics  The clinics value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function update(UpdateClinicRequest $request, Tenant $clinic, ClinicManagementService $clinics): RedirectResponse
    {
        $clinics->update($clinic, $request->validated());

        return redirect()
            ->route('admin.clinics.edit', $clinic)
            ->with('status', __('clinics.messages.updated'));
    }

    /**
     * Delete the requested clinic record.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  ClinicManagementService  $clinics  The clinics value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function destroy(Tenant $clinic, ClinicManagementService $clinics): RedirectResponse
    {
        Gate::authorize('delete', $clinic);

        $clinics->delete($clinic);

        return redirect()
            ->route('admin.clinics.index')
            ->with('status', __('clinics.messages.deleted'));
    }

    /**
     * Assign plan.
     *
     * @param  AssignClinicPlanRequest  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  BillingService  $billing  The billing value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function assignPlan(AssignClinicPlanRequest $request, Tenant $clinic, BillingService $billing): RedirectResponse
    {
        $plan = Plan::query()->with('serviceEntitlements')->findOrFail((int) $request->input('plan_id'));

        try {
            $billing->invoiceForClinicPlan($plan, $clinic, $request->user(), (int) $request->input('discount_amount', 0));

            return redirect()
                ->route('admin.clinics.show', $clinic)
                ->with('status', __('clinics.messages.plan_invoice_created', [
                    'plan' => $plan->name,
                    'clinic' => $clinic->name,
                ]));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Clinic plan assignment failed.', [
                'clinic_id' => $clinic->id,
                'plan_id' => $plan->id,
                'actor_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return back()
                ->withInput()
                ->withErrors(['plan' => __('clinics.messages.plan_assignment_failed')]);
        }
    }

    /**
     * Execute the store invoice operation.
     *
     * @param  StoreClinicInvoiceRequest  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function storeInvoice(StoreClinicInvoiceRequest $request, Tenant $clinic): RedirectResponse
    {
        abort_unless($this->invoiceUserBelongsToClinic($clinic, $request->integer('user_id') ?: null), 422);

        $invoice = Invoice::query()->create($this->invoiceAttributes($request->validated(), $clinic, $request->user()?->id));

        return redirect()
            ->route('admin.clinics.show', $clinic)
            ->with('status', __('payments.manual_invoice_created', ['number' => $invoice->number]));
    }

    /**
     * Execute the update invoice operation.
     *
     * @param  UpdateClinicInvoiceRequest  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  Invoice  $invoice  The invoice value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function updateInvoice(UpdateClinicInvoiceRequest $request, Tenant $clinic, Invoice $invoice): RedirectResponse
    {
        $this->authorizeClinicInvoice($clinic, $invoice);
        abort_unless($this->invoiceUserBelongsToClinic($clinic, $request->integer('user_id') ?: null), 422);

        $invoice->update($this->invoiceAttributes($request->validated(), $clinic, $request->user()?->id, $invoice));

        return redirect()
            ->route('admin.clinics.show', $clinic)
            ->with('status', __('payments.manual_invoice_updated', ['number' => $invoice->number]));
    }

    /**
     * Execute the approve invoice operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  Invoice  $invoice  The invoice value.
     * @param  BillingService  $billing  The billing value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function approveInvoice(Request $request, Tenant $clinic, Invoice $invoice, BillingService $billing): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('billing.manage'), 403);
        $this->authorizeClinicInvoice($clinic, $invoice);

        try {
            if ($invoice->plan_id) {
                $billing->activateClinicPlanInvoice($invoice, $request->user());
            } else {
                $invoice->forceFill([
                    'status' => 'paid',
                    'paid_at' => $invoice->paid_at ?? now(),
                    'metadata_json' => array_replace($invoice->metadata_json ?? [], [
                        'approved_by' => $request->user()?->id,
                    ]),
                ])->save();
            }

            return redirect()
                ->route('admin.clinics.show', $clinic)
                ->with('status', __('payments.manual_invoice_approved', ['number' => $invoice->number]));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Clinic invoice approval failed.', [
                'clinic_id' => $clinic->id,
                'invoice_id' => $invoice->id,
                'actor_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['invoice' => __('payments.payment_failed_generic')]);
        }
    }

    /**
     * Execute the update sms gateway operation.
     *
     * @param  StoreClinicSmsGatewayRequest  $request  Incoming HTTP request.
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @param  SmsGatewayService  $gateways  The gateways value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function updateSmsGateway(
        StoreClinicSmsGatewayRequest $request,
        Tenant $clinic,
        SmsGatewayManagementService $manager,
        SmsGatewayService $gateways
    ): RedirectResponse {
        $manager->saveClinicSettings($clinic, $request->validated(), $gateways);

        return redirect()
            ->route('admin.clinics.show', $clinic)
            ->with('status', __('sms.private_updated'));
    }

    /**
     * Delete the clinic-scoped SMS gateway panel.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  SmsGatewayManagementService  $manager  The manager value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 2.0.0
     */
    public function destroySmsGateway(Tenant $clinic, SmsGatewayManagementService $manager): RedirectResponse
    {
        Gate::authorize('view', $clinic);

        $gateway = $manager->clinicSettings($clinic);
        abort_unless($gateway, 404);

        Gate::authorize('delete', $gateway);
        $gateway->delete();

        return redirect()
            ->route('admin.clinics.show', $clinic)
            ->with('status', __('sms.private_deleted'));
    }

    /**
     * Execute the authorize clinic invoice operation.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  Invoice  $invoice  The invoice value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function authorizeClinicInvoice(Tenant $clinic, Invoice $invoice): void
    {
        abort_unless($invoice->tenant_id === $clinic->id, 404);
    }

    /**
     * Execute the invoice user belongs to clinic operation.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  int|null  $userId  The user id value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function invoiceUserBelongsToClinic(Tenant $clinic, ?int $userId): bool
    {
        if (! $userId) {
            return true;
        }

        return $clinic->users()
            ->whereKey($userId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Execute the invoice attributes operation.
     *
     * @param  array<string, mixed>  $data
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  int|null  $actorId  The actor id value.
     * @param  Invoice|null  $invoice  The invoice value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function invoiceAttributes(array $data, Tenant $clinic, ?int $actorId, ?Invoice $invoice = null): array
    {
        $metadata = array_replace($invoice?->metadata_json ?? [], array_filter([
            'manual_note' => $data['note'] ?? null,
            'manual_by' => $actorId,
            'source' => 'clinic_manual_invoice',
        ]));

        $plan = filled($data['plan_id'] ?? null) ? Plan::query()->find((int) $data['plan_id']) : null;

        return [
            'tenant_id' => $clinic->id,
            'user_id' => filled($data['user_id'] ?? null) ? (int) $data['user_id'] : null,
            'plan_id' => $plan?->id,
            'number' => $invoice?->number ?? $this->nextInvoiceNumber($plan),
            'status' => $data['status'],
            'amount' => (int) $data['amount'],
            'currency' => $data['currency'],
            'due_at' => $data['due_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'paid_at' => $data['status'] === 'paid' ? ($invoice?->paid_at ?? now()) : null,
            'metadata_json' => $metadata,
        ];
    }

    /**
     * Execute the next invoice number operation.
     *
     * @param  Plan|null  $plan  The plan value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function nextInvoiceNumber(?Plan $plan = null): string
    {
        $prefix = match ($plan?->plan_type?->value) {
            'base_subscription' => 'BS',
            'service_add_on' => 'SA',
            'credit_top_up' => 'CT',
            default => 'MI',
        };
        $next = ((int) Invoice::query()->max('id')) + 1;

        do {
            $number = $prefix.'-'.$next;
            $next++;
        } while (Invoice::query()->where('number', $number)->exists());

        return $number;
    }
}
