<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\Adapters\LocalPaymentGatewayAdapter;
use App\Services\Payment\Adapters\ZarinpalPaymentGatewayAdapter;
use App\Services\Payment\Contracts\PaymentGatewayAdapter;
use Illuminate\Validation\ValidationException;

/**
 * Resolves default payment gateways and delegates provider operations.
 *
 * @since 1.0.0
 */
class PaymentGatewayService
{
    /**
     * Return the default enabled payment gateway.
     */
    public function default(): ?PaymentGateway
    {
        return PaymentGateway::query()
            ->enabled()
            ->where('is_default', true)
            ->first()
            ?? PaymentGateway::query()->enabled()->orderBy('id')->first();
    }

    /**
     * Mark one gateway as the system default.
     */
    public function markDefault(PaymentGateway $gateway): PaymentGateway
    {
        PaymentGateway::query()->whereKeyNot($gateway->id)->update(['is_default' => false]);
        $gateway->forceFill(['is_default' => true])->save();

        return $gateway->refresh();
    }

    /**
     * Initiate a payment through the selected default gateway.
     *
     * @throws ValidationException
     */
    public function initiate(Payment $payment): Payment
    {
        $gateway = $payment->gateway;

        if (! $gateway || $gateway->status !== 'enabled') {
            throw ValidationException::withMessages([
                'payment_gateway' => __('payments.gateway_inactive'),
            ]);
        }

        $result = $this->adapterFor($gateway)->initiate($gateway, $payment);

        $payment->forceFill([
            'authority' => $result['authority'],
            'gateway_response_json' => $result['response'] + array_filter([
                'redirect_url' => $result['redirect_url'] ?? null,
            ]),
        ])->save();

        return $payment->refresh();
    }

    /**
     * Verify a payment through its gateway adapter.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verify(Payment $payment, array $payload = []): array
    {
        return $this->adapterFor($payment->gateway)->verify($payment->gateway, $payment, $payload);
    }

    private function adapterFor(PaymentGateway $gateway): PaymentGatewayAdapter
    {
        return match ($gateway->provider_code) {
            'zarinpal' => app(ZarinpalPaymentGatewayAdapter::class),
            'local', 'idpay' => app(LocalPaymentGatewayAdapter::class),
            default => app(LocalPaymentGatewayAdapter::class),
        };
    }
}
