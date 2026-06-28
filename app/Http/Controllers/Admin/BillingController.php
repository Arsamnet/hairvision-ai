<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingService;
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

        $invoices = Invoice::query()
            ->with(['user', 'plan', 'payments.gateway'])
            ->when(! $canManage, fn ($query) => $query->where('user_id', $user->id))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'like', $search)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $search)->orWhere('phone_normalized', 'like', $search))
                        ->orWhereHas('plan', fn ($planQuery) => $planQuery->where('name', 'like', $search));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(20)
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
