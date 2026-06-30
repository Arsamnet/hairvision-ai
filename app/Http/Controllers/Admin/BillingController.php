<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingService;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows invoices and handles secure local payment verification flow.
 *
 * @since 1.0.0
 */
class BillingController extends Controller
{
    public function index(Request $request, BillingService $billing): View
    {
        $user = $request->user();
        $canManage = $user->hasPermission('billing.manage');
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'number', 'amount', 'status'],
            'created_at'
        );

        $invoices = Invoice::query()
            ->with(['user', 'plan', 'payments.gateway'])
            ->when(! $canManage, fn ($query) => $query->where('user_id', $user->id))
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
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'invoices' => $invoices,
            'activeSubscription' => $billing->activeSubscription($user),
            'canManageBilling' => $canManage,
        ]);
    }

    public function pay(Request $request, Invoice $invoice, BillingService $billing): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id || $request->user()->hasPermission('billing.manage'), 403);

        $payment = $billing->initiatePayment($invoice);
        $billing->verifyPayment($payment);

        return back()->with('status', __('payments.payment_activated'));
    }

    public function verify(Request $request, Payment $payment, BillingService $billing): RedirectResponse
    {
        abort_unless($payment->user_id === $request->user()->id || $request->user()->hasPermission('billing.manage'), 403);

        $billing->verifyPayment($payment, $request->only(['reference_id']));

        return back()->with('status', __('payments.payment_verified'));
    }
}
