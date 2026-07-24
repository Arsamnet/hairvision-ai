<?php

namespace App\Services\Payment\Contracts;

use App\Models\Payment;
use App\Models\PaymentGateway;

/**
 * Common adapter contract for supported payment gateways.
 *
 * @since 1.0.0
 */
interface PaymentGatewayAdapter
{
    /**
     * Start a payment transaction through the selected gateway.
     *
     * @param  PaymentGateway  $gateway  The gateway value.
     * @param  Payment  $payment  The payment value.
     * @return array{authority: string|null, reference_id?: string|null, redirect_url?: string|null, response: array<string, mixed>}
     *
     * @since 1.0.0
     */
    public function initiate(PaymentGateway $gateway, Payment $payment): array;

    /**
     * Verify a payment transaction in an idempotent way.
     *
     * @param  array<string, mixed>  $payload
     * @param  PaymentGateway  $gateway  The gateway value.
     * @param  Payment  $payment  The payment value.
     * @return array{reference_id: string|null, response: array<string, mixed>}
     *
     * @since 1.0.0
     */
    public function verify(PaymentGateway $gateway, Payment $payment, array $payload = []): array;
}
