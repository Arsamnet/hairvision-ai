<?php

namespace App\Services\Payment\Adapters;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\Contracts\PaymentGatewayAdapter;

/**
 * Local deterministic payment adapter for tests and non-external flows.
 *
 * @since 1.0.0
 */
class LocalPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    public function initiate(PaymentGateway $gateway, Payment $payment): array
    {
        return [
            'authority' => 'local-'.$payment->uuid,
            'reference_id' => null,
            'response' => ['provider' => 'local', 'status' => 'initiated'],
        ];
    }

    public function verify(PaymentGateway $gateway, Payment $payment, array $payload = []): array
    {
        return [
            'reference_id' => $payload['reference_id'] ?? 'local-ref-'.$payment->uuid,
            'response' => ['provider' => 'local', 'status' => 'verified'],
        ];
    }
}
