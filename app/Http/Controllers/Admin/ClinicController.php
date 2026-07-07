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
use App\Services\AI\AiModelResolver;
use App\Services\Admin\ClinicManagementService;
use App\Services\Admin\SmsGatewayManagementService;
use App\Services\Billing\BillingService;
use App\Services\Billing\ServiceCatalog;
use App\Services\Sms\SmsGatewayService;
use App\Support\Pagination\PaginationParameters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

    public function store(StoreClinicRequest $request, ClinicManagementService $clinics): RedirectResponse
    {
        $clinic = $clinics->create($request->validated());

        return redirect()
            ->route('admin.clinics.edit', $clinic)
            ->with('status', __('clinics.messages.created'));
    }

    public function show(
        Request $request,
        Tenant $clinic,
        ServiceCatalog $services,
        AiModelResolver $resolver,
        SmsGatewayManagementService $smsGateways
    ): View
    {
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
                ->where('is_demo', false)
                ->where('is_protected', false)
                ->orderBy('usage_category_key')
                ->orderBy('name')
                ->get(),
            'clinicSmsGateway' => $smsGateways->clinicSettings($clinic),
            'smsProviderOptions' => $smsGateways->providerOptionsForPrivate($smsGateways->clinicSettings($clinic)),
        ]);
    }

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

    public function update(UpdateClinicRequest $request, Tenant $clinic, ClinicManagementService $clinics): RedirectResponse
    {
        $clinics->update($clinic, $request->validated());

        return redirect()
            ->route('admin.clinics.edit', $clinic)
            ->with('status', __('clinics.messages.updated'));
    }

    public function destroy(Tenant $clinic, ClinicManagementService $clinics): RedirectResponse
    {
        Gate::authorize('delete', $clinic);

        $clinics->delete($clinic);

        return redirect()
            ->route('admin.clinics.index')
            ->with('status', __('clinics.messages.deleted'));
    }

    public function assignPlan(AssignClinicPlanRequest $request, Tenant $clinic, BillingService $billing): RedirectResponse
    {
        $plan = Plan::query()->with('serviceEntitlements')->findOrFail((int) $request->input('plan_id'));

        try {
            $billing->invoiceForClinicPlan($plan, $clinic, $request->user());

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

    public function storeInvoice(StoreClinicInvoiceRequest $request, Tenant $clinic): RedirectResponse
    {
        abort_unless($this->invoiceUserBelongsToClinic($clinic, $request->integer('user_id') ?: null), 422);

        $invoice = Invoice::query()->create($this->invoiceAttributes($request->validated(), $clinic, $request->user()?->id));

        return redirect()
            ->route('admin.clinics.show', $clinic)
            ->with('status', __('payments.manual_invoice_created', ['number' => $invoice->number]));
    }

    public function updateInvoice(UpdateClinicInvoiceRequest $request, Tenant $clinic, Invoice $invoice): RedirectResponse
    {
        $this->authorizeClinicInvoice($clinic, $invoice);
        abort_unless($this->invoiceUserBelongsToClinic($clinic, $request->integer('user_id') ?: null), 422);

        $invoice->update($this->invoiceAttributes($request->validated(), $clinic, $request->user()?->id, $invoice));

        return redirect()
            ->route('admin.clinics.show', $clinic)
            ->with('status', __('payments.manual_invoice_updated', ['number' => $invoice->number]));
    }

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

    private function authorizeClinicInvoice(Tenant $clinic, Invoice $invoice): void
    {
        abort_unless($invoice->tenant_id === $clinic->id, 404);
    }

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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function invoiceAttributes(array $data, Tenant $clinic, ?int $actorId, ?Invoice $invoice = null): array
    {
        $metadata = array_replace($invoice?->metadata_json ?? [], array_filter([
            'manual_note' => $data['note'] ?? null,
            'manual_by' => $actorId,
            'source' => 'clinic_manual_invoice',
        ]));

        return [
            'tenant_id' => $clinic->id,
            'user_id' => filled($data['user_id'] ?? null) ? (int) $data['user_id'] : null,
            'plan_id' => filled($data['plan_id'] ?? null) ? (int) $data['plan_id'] : null,
            'number' => $invoice?->number ?? $this->nextInvoiceNumber(),
            'status' => $data['status'],
            'amount' => (int) $data['amount'],
            'currency' => $data['currency'],
            'due_at' => $data['due_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'paid_at' => $data['status'] === 'paid' ? ($invoice?->paid_at ?? now()) : null,
            'metadata_json' => $metadata,
        ];
    }

    private function nextInvoiceNumber(): string
    {
        do {
            $number = 'HV-MAN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (Invoice::query()->where('number', $number)->exists());

        return $number;
    }
}
