<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Services\Billing\ServiceEntitlementService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use App\Support\Tenancy\HostClassifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Shows invoices and handles gateway payment handoff and verification.
 *
 * @since 1.0.0
 */
class BillingController extends Controller
{
    /**
     * Display the billing listing.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  BillingService  $billing  The billing value.
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function index(Request $request, BillingService $billing, ServiceEntitlementService $entitlements): View
    {
        $user = $request->user();
        $canManage = $user->hasPermission('billing.manage');
        $tenant = $request->attributes->get('adminTenant');
        $showActiveSubscriptionBox = $this->isClinicAdminHost($request);
        $activeSubscription = $showActiveSubscriptionBox
            ? $billing->activeSubscription($user, $tenant)
            : null;
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'number', 'amount', 'status'],
            'created_at'
        );

        $invoices = Invoice::query()
            ->with(['tenant', 'user', 'plan', 'payments.gateway'])
            ->when(! $canManage, fn ($query) => $query->where(function ($query) use ($user, $tenant): void {
                $query->where('user_id', $user->id)
                    ->when($tenant, fn ($query) => $query->orWhere(function ($query) use ($tenant): void {
                        $query->whereNull('user_id')->where('tenant_id', $tenant->id);
                    }));
            }))
            ->when(! $canManage && $tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->when($canManage && $tenant, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()));
        SearchFilter::apply($invoices, $pagination->search, ['number'], [
            'user' => ['name', 'phone_normalized'],
            'plan' => ['name'],
        ]);
        DateRangeFilter::apply($invoices, $request);
        $invoices = $invoices
            ->orderBy($pagination->sort, $pagination->direction)
            ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
            ->withQueryString();

        return view('admin.billing.index', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'invoices' => $invoices,
            'activeSubscription' => $activeSubscription,
            'serviceSummaries' => $entitlements->summaryForSubscription($activeSubscription),
            'remainingCredit' => $entitlements->remainingCreditForSubscription($activeSubscription),
            'totalCredit' => $entitlements->totalCreditForSubscription($activeSubscription),
            'canManageBilling' => $canManage,
            'showActiveSubscriptionBox' => $showActiveSubscriptionBox,
        ]);
    }

    /**
     * Execute the pay operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Invoice  $invoice  The invoice value.
     * @param  BillingService  $billing  The billing value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function pay(Request $request, Invoice $invoice, BillingService $billing): RedirectResponse
    {
        abort_unless($this->canAccessInvoice($request, $invoice), 403);

        try {
            $payment = $billing->initiatePayment($invoice);
            $redirectUrl = data_get($payment->gateway_response_json, 'redirect_url');

            if (is_string($redirectUrl) && trim($redirectUrl) !== '') {
                return $this->redirectToGateway(trim($redirectUrl));
            }

            $billing->verifyPayment($payment);

            return $this->redirectToPaymentResult($payment);
        } catch (ValidationException $exception) {
            Log::warning('Billing payment initiation was rejected.', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            $message = data_get($exception->errors(), 'payment_gateway.0')
                ?? data_get($exception->errors(), 'invoice.0')
                ?? data_get($exception->errors(), 'plan.0')
                ?? __('payments.payment_failed_generic');

            return redirect()
                ->route('admin.billing.index')
                ->withErrors(['payment' => $message]);
        } catch (Throwable $exception) {
            Log::warning('Billing payment initiation failed.', [
                'invoice_id' => $invoice->id,
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return redirect()
                ->route('admin.billing.index')
                ->withErrors(['payment' => __('payments.payment_failed_generic')]);
        }
    }

    /**
     * Verify a payment gateway callback.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Payment  $payment  The payment value.
     * @param  BillingService  $billing  The billing value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    public function verify(Request $request, Payment $payment, BillingService $billing): RedirectResponse
    {
        $payment->loadMissing('invoice');

        abort_unless($payment->invoice && $this->canAccessInvoice($request, $payment->invoice), 403);

        if ($this->gatewayRejectedCallback($request)) {
            Log::warning('Billing payment callback was rejected by gateway.', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'gateway_status' => $request->input('Status', $request->input('status')),
            ]);

            try {
                $billing->markPaymentFailed($payment, $this->callbackPayload($request));
            } catch (Throwable $exception) {
                Log::warning('Failed to mark rejected payment callback.', [
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                    'exception' => $exception::class,
                ]);
            }

            return $this->redirectToPaymentResult($payment);
        }

        try {
            $billing->verifyPayment($payment, $this->callbackPayload($request));

            return $this->redirectToPaymentResult($payment);
        } catch (Throwable $exception) {
            Log::warning('Billing payment verification failed.', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return $this->redirectToPaymentResult($payment);
        }
    }

    /**
     * Show the persisted server-side result without trusting callback query data.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Payment  $payment  The payment value.
     * @return View Rendered response view.
     *
     * @since 1.0.0
     */
    public function result(Request $request, Payment $payment): View
    {
        $payment->loadMissing(['invoice.plan', 'gateway']);

        abort_unless($payment->invoice && $this->canAccessInvoice($request, $payment->invoice), 403);

        $resultState = match ($payment->status) {
            'paid' => 'success',
            'failed', 'canceled' => 'failed',
            default => 'pending',
        };

        return view('admin.billing.result', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'resultState' => $resultState,
        ]);
    }

    /**
     * Execute the can access invoice operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Invoice  $invoice  The invoice value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function canAccessInvoice(Request $request, Invoice $invoice): bool
    {
        $user = $request->user();

        if ($user->hasPermission('billing.manage')) {
            return true;
        }

        $tenant = $request->attributes->get('adminTenant');
        $invoiceUserId = $invoice->user_id === null ? null : (int) $invoice->user_id;
        $invoiceTenantId = $invoice->tenant_id === null ? null : (int) $invoice->tenant_id;
        $userId = (int) $user->getKey();
        $tenantId = $tenant ? (int) $tenant->getKey() : null;
        $isUserOwned = $invoiceUserId === $userId;
        $isUnassignedTenantInvoice = $invoiceUserId === null
            && $tenantId !== null
            && $invoiceTenantId === $tenantId;
        $isInCurrentTenant = $invoiceTenantId === null
            || ($tenantId !== null && $invoiceTenantId === $tenantId);

        return ($isUserOwned || $isUnassignedTenantInvoice) && $isInCurrentTenant;
    }

    /**
     * Determine whether clinic admin host.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function isClinicAdminHost(Request $request): bool
    {
        return ! HostClassifier::isPrimaryHost($request->getHost());
    }

    /**
     * Execute the redirect to gateway operation.
     *
     * @param  string  $url  The url value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    private function redirectToGateway(string $url): RedirectResponse
    {
        // Payment starts from a POST form. A 303 guarantees the external
        // StartPay endpoint is opened with GET instead of replaying that POST.
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return redirect()->away($url, Response::HTTP_SEE_OTHER);
        }

        return redirect()->to($url, Response::HTTP_SEE_OTHER);
    }

    /**
     * Execute the redirect to payment result operation.
     *
     * @param  Payment  $payment  The payment value.
     * @return RedirectResponse HTTP redirect response.
     *
     * @since 1.0.0
     */
    private function redirectToPaymentResult(Payment $payment): RedirectResponse
    {
        return redirect()->route(
            'admin.billing.result',
            $payment,
            Response::HTTP_SEE_OTHER
        );
    }

    /**
     * Execute the gateway rejected callback operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function gatewayRejectedCallback(Request $request): bool
    {
        if (! $request->hasAny(['Status', 'status'])) {
            return false;
        }

        return strtoupper((string) $request->input('Status', $request->input('status'))) !== 'OK';
    }

    /**
     * Execute the callback payload operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    private function callbackPayload(Request $request): array
    {
        return $request->only(['reference_id', 'Authority', 'authority', 'Status', 'status']);
    }
}
