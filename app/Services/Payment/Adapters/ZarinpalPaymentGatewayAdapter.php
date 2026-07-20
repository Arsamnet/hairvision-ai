<?php

namespace App\Services\Payment\Adapters;

use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payment\Contracts\PaymentGatewayAdapter;
use App\Services\Payment\ZarinpalConfiguration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Zarinpal sandbox payment adapter for clinic billing handoff and callback verification.
 *
 * @since 1.0.0
 */
class ZarinpalPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private const DEFAULT_REQUEST_URL = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';

    private const DEFAULT_VERIFY_URL = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';

    private const DEFAULT_START_PAY_URL = 'https://sandbox.zarinpal.com/pg/StartPay/';

    public function initiate(PaymentGateway $gateway, Payment $payment): array
    {
        $payment->loadMissing(['invoice.user']);

        $response = $this->post($gateway, $payment, $this->requestUrl($gateway), [
            'merchant_id' => $this->merchantId($gateway),
            'amount' => $payment->amount,
            'callback_url' => $this->callbackUrl($payment),
            'description' => __('payments.zarinpal_description', [
                'number' => $payment->invoice?->number ?? $payment->uuid,
            ]),
            'metadata' => array_filter([
                'mobile' => $payment->invoice?->user?->phone_normalized,
                'email' => $payment->invoice?->user?->email,
            ]),
        ], 'request');

        $data = data_get($response, 'data');
        $code = (int) data_get($data, 'code');
        $authority = data_get($data, 'authority');

        if ($code !== 100 || ! is_string($authority) || trim($authority) === '') {
            $this->fail('Zarinpal payment request rejected.', $gateway, $payment, [
                'code' => $code,
            ] + $this->providerErrorContext($response));
        }

        $redirectUrl = rtrim($this->startPayUrl($gateway), '/').'/'.trim($authority);

        return [
            'authority' => trim($authority),
            'reference_id' => null,
            'redirect_url' => $redirectUrl,
            'response' => [
                'provider' => 'zarinpal',
                'status' => 'initiated',
                'data' => $data,
                'redirect_url' => $redirectUrl,
            ],
        ];
    }

    public function verify(PaymentGateway $gateway, Payment $payment, array $payload = []): array
    {
        $authority = $this->authority($payment, $payload);

        $response = $this->post($gateway, $payment, $this->verifyUrl($gateway), [
            'merchant_id' => $this->merchantId($gateway),
            'amount' => $payment->amount,
            'authority' => $authority,
        ], 'verify');

        $data = data_get($response, 'data');
        $code = (int) data_get($data, 'code');

        if (! in_array($code, [100, 101], true)) {
            $this->fail('Zarinpal payment verification rejected.', $gateway, $payment, [
                'code' => $code,
                'authority_hash' => hash('sha256', $authority),
            ] + $this->providerErrorContext($response));
        }

        $referenceId = data_get($data, 'ref_id');

        return [
            'reference_id' => filled($referenceId) ? (string) $referenceId : null,
            'response' => [
                'provider' => 'zarinpal',
                'status' => 'verified',
                'data' => $data,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(PaymentGateway $gateway, Payment $payment, string $url, array $payload, string $operation): array
    {
        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout($this->timeoutSeconds($gateway))
                ->connectTimeout($this->connectTimeoutSeconds($gateway))
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Zarinpal payment gateway connection failed.', [
                'gateway_id' => $gateway->id,
                'payment_id' => $payment->id,
                'operation' => $operation,
                'endpoint' => $url,
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'payment_gateway' => __('payments.payment_failed_generic'),
            ]);
        } catch (Throwable $exception) {
            Log::error('Zarinpal payment gateway request crashed.', [
                'gateway_id' => $gateway->id,
                'payment_id' => $payment->id,
                'operation' => $operation,
                'endpoint' => $url,
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'payment_gateway' => __('payments.payment_failed_generic'),
            ]);
        }

        $body = $response->json();

        if ($response->failed() || ! is_array($body)) {
            $this->fail('Zarinpal payment gateway returned an invalid response.', $gateway, $payment, [
                'operation' => $operation,
                'endpoint' => $url,
                'status' => $response->status(),
            ] + $this->providerErrorContext($body));
        }

        return $body;
    }

    private function merchantId(PaymentGateway $gateway): string
    {
        $merchantId = data_get($gateway->encrypted_credentials ?? [], 'merchant_id');

        if (! ZarinpalConfiguration::hasUsableMerchantId($merchantId)) {
            Log::warning('Zarinpal payment gateway merchant id is not configured.', [
                'gateway_id' => $gateway->id,
            ]);

            throw ValidationException::withMessages([
                'payment_gateway' => __('payments.gateway_not_configured'),
            ]);
        }

        return trim($merchantId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function authority(Payment $payment, array $payload): string
    {
        $authority = $payload['Authority'] ?? $payload['authority'] ?? $payment->authority;

        if (! is_string($authority) || trim($authority) === '') {
            Log::warning('Zarinpal payment callback is missing authority.', [
                'payment_id' => $payment->id,
            ]);

            throw ValidationException::withMessages([
                'payment_gateway' => __('payments.payment_failed_generic'),
            ]);
        }

        return trim($authority);
    }

    private function callbackUrl(Payment $payment): string
    {
        // The callback contains the payment identity and uses the same host on
        // which payment started, preserving the authenticated admin session.
        return route('admin.billing.verify', $payment);
    }

    private function requestUrl(PaymentGateway $gateway): string
    {
        return $this->stringSetting($gateway, 'request_url', self::DEFAULT_REQUEST_URL);
    }

    private function verifyUrl(PaymentGateway $gateway): string
    {
        return $this->stringSetting($gateway, 'verify_url', self::DEFAULT_VERIFY_URL);
    }

    private function startPayUrl(PaymentGateway $gateway): string
    {
        return $this->stringSetting($gateway, 'start_pay_url', self::DEFAULT_START_PAY_URL);
    }

    private function timeoutSeconds(PaymentGateway $gateway): int
    {
        return max(1, (int) data_get($gateway->settings_json ?? [], 'timeout_seconds', 8));
    }

    private function connectTimeoutSeconds(PaymentGateway $gateway): int
    {
        return max(1, (int) data_get($gateway->settings_json ?? [], 'connect_timeout_seconds', 3));
    }

    private function stringSetting(PaymentGateway $gateway, string $key, string $default): string
    {
        $value = data_get($gateway->settings_json ?? [], $key);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /**
     * Extract only non-sensitive diagnostics from a failed provider response.
     *
     * @return array<string, mixed>
     */
    private function providerErrorContext(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        $code = data_get($body, 'errors.code');
        $validations = data_get($body, 'errors.validations');

        return array_filter([
            'provider_code' => is_int($code) || is_string($code) ? $code : null,
            'provider_validation_fields' => is_array($validations) ? array_keys($validations) : null,
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function fail(string $message, PaymentGateway $gateway, Payment $payment, array $context = []): never
    {
        Log::warning($message, $context + [
            'gateway_id' => $gateway->id,
            'payment_id' => $payment->id,
        ]);

        throw ValidationException::withMessages([
            'payment_gateway' => __('payments.payment_failed_generic'),
        ]);
    }
}
