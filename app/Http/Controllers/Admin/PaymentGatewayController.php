<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages system payment gateway definitions.
 *
 * @since 1.0.0
 */
class PaymentGatewayController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.system.payment-gateways.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => PaymentGateway::query()->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, PaymentGatewayService $gateways): RedirectResponse
    {
        $gateway = PaymentGateway::create($this->validated($request));

        if ($gateway->is_default) {
            $gateways->markDefault($gateway);
        }

        return back()->with('status', __('messages.payment_gateway_created'));
    }

    public function update(Request $request, PaymentGateway $paymentGateway, PaymentGatewayService $gateways): RedirectResponse
    {
        $paymentGateway->update($this->validated($request));

        if ($paymentGateway->is_default) {
            $gateways->markDefault($paymentGateway);
        }

        return back()->with('status', __('messages.payment_gateway_updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80'],
            'status' => ['required', 'in:enabled,disabled'],
            'is_default' => ['nullable', 'boolean'],
            'merchant_id' => ['nullable', 'string', 'max:500'],
            'callback_url' => ['nullable', 'url', 'max:500'],
        ]);

        return [
            'name' => $validated['name'],
            'provider_code' => $validated['provider_code'],
            'status' => $validated['status'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'encrypted_credentials' => array_filter(['merchant_id' => $validated['merchant_id'] ?? null]),
            'settings_json' => array_filter(['callback_url' => $validated['callback_url'] ?? null]),
        ];
    }
}
