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
use Illuminate\View\View;
use Throwable;

/**
 * Shows invoices and handles gateway payment handoff and verification.
 *
 * @since 1.0.0
 */
class BillingController extends Controller
{
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

            return redirect()->route('admin.billing.index')->with('status', __('payments.payment_activated'));
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

            return redirect()
                ->route('admin.billing.index')
                ->withErrors(['payment' => __('payments.payment_failed_generic')]);
        }

        try {
            $billing->verifyPayment($payment, $this->callbackPayload($request));

            return redirect()->route('admin.billing.index')->with('status', __('payments.payment_verified'));
        } catch (Throwable $exception) {
            Log::warning('Billing payment verification failed.', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
                'user_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            return redirect()
                ->route('admin.billing.index')
                ->withErrors(['payment' => __('payments.payment_failed_generic')]);
        }
    }

    private function canAccessInvoice(Request $request, Invoice $invoice): bool
    {
        $user = $request->user();

        if ($user->hasPermission('billing.manage')) {
            return true;
        }

        $tenant = $request->attributes->get('adminTenant');

        return ($invoice->user_id === $user->id || ($invoice->user_id === null && $tenant && $invoice->tenant_id === $tenant->id))
            && (! $invoice->tenant_id || ($tenant && $invoice->tenant_id === $tenant->id));
    }

    private function isClinicAdminHost(Request $request): bool
    {
        return ! HostClassifier::isPrimaryHost($request->getHost());
    }

    private function redirectToGateway(string $url): RedirectResponse
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return redirect()->away($url);
        }

        return redirect()->to($url);
    }

    private function gatewayRejectedCallback(Request $request): bool
    {
        if (! $request->hasAny(['Status', 'status'])) {
            return false;
        }

        return strtoupper((string) $request->input('Status', $request->input('status'))) !== 'OK';
    }

    /**
     * @return array<string, mixed>
     */
    private function callbackPayload(Request $request): array
    {
        return $request->only(['reference_id', 'Authority', 'authority', 'Status', 'status']);
    }
}
