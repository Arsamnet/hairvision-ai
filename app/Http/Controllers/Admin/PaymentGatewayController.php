<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\ZarinpalConfiguration;
use App\Support\Pagination\PaginationParameters;
use App\Support\Query\DateRangeFilter;
use App\Support\Query\SearchFilter;
use App\Support\StateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $pagination = PaginationParameters::fromRequest(
            $request,
            ['created_at', 'name', 'provider_code', 'status'],
            'created_at'
        );
        $query = PaymentGateway::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('provider_code'), fn ($query) => $query->where('provider_code', $request->string('provider_code')->toString()));
        SearchFilter::apply($query, $pagination->search, ['name', 'provider_code']);
        DateRangeFilter::apply($query, $request);

        return view('admin.system.payment-gateways.index', [
            'tenant' => $request->attributes->get('adminTenant'),
            'availableTenants' => $request->attributes->get('adminTenants'),
            'gateways' => $query
                ->orderBy($pagination->sort, $pagination->direction)
                ->paginate($pagination->perPage, ['*'], 'page', $pagination->page)
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
            'status' => ['required', Rule::in(StateCatalog::values('payment_gateways.statuses'))],
            'is_default' => ['nullable', 'boolean'],
            'merchant_id' => ['nullable', 'string', 'max:500'],
            'callback_url' => ['nullable', 'url', 'max:500'],
        ]);

        $merchantId = filled($validated['merchant_id'] ?? null)
            ? trim((string) $validated['merchant_id'])
            : data_get($gateway?->encrypted_credentials, 'merchant_id');

        if (
            $validated['provider_code'] === 'zarinpal'
            && $validated['status'] === 'enabled'
            && ! ZarinpalConfiguration::hasUsableMerchantId($merchantId)
        ) {
            throw ValidationException::withMessages([
                'merchant_id' => __('payments.merchant_id_invalid'),
            ]);
        }

        $credentials = array_replace($gateway?->encrypted_credentials ?? [], array_filter([
            'merchant_id' => $merchantId,
        ], 'filled'));
        $settings = $gateway?->settings_json ?? [];

        if ($validated['provider_code'] === 'zarinpal') {
            // Zarinpal requires a payment-specific callback. A fixed dashboard
            // URL would bypass verification and can also cross session domains.
            unset($settings['callback_url']);
        } elseif (array_key_exists('callback_url', $validated)) {
            if (filled($validated['callback_url'])) {
                $settings['callback_url'] = trim((string) $validated['callback_url']);
            } else {
                unset($settings['callback_url']);
            }
        }

        return [
            'name' => $validated['name'],
            'provider_code' => $validated['provider_code'],
            'status' => $validated['status'],
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'encrypted_credentials' => $credentials,
            'settings_json' => $settings,
        ];
    }
}
