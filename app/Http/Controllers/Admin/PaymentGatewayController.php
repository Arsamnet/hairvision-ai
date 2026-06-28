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
            'gateways' => PaymentGateway::query()
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = '%'.$request->string('search')->toString().'%';
                    $query->where(function ($query) use ($search): void {
                        $query->where('name', 'like', $search)
                            ->orWhere('provider_code', 'like', $search);
                    });
                })
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
                ->when($request->filled('provider_code'), fn ($query) => $query->where('provider_code', $request->string('provider_code')->toString()))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
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
        $paymentGateway->update($this->validated($request, $paymentGateway));

        if ($paymentGateway->is_default) {
            $gateways->markDefault($paymentGateway);
        }

        return back()->with('status', __('messages.payment_gateway_updated'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?PaymentGateway $gateway = null): array
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
            'encrypted_credentials' => array_filter([
                'merchant_id' => $validated['merchant_id'] ?? data_get($gateway?->encrypted_credentials, 'merchant_id'),
            ]),
            'settings_json' => array_filter([
                'callback_url' => $validated['callback_url'] ?? data_get($gateway?->settings_json, 'callback_url'),
            ]),
        ];
    }
}
