<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use App\Support\NumberNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Sends SMS messages through the IPPanel edge API.
 *
 * @since 1.0.0
 */
class IppanelSmsGatewayAdapter implements SmsGatewayAdapter
{
    private const DEFAULT_ENDPOINT = 'https://edge.ippanel.com/v1/api/send';

    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void
    {
        $this->post($gateway, [
            'sending_type' => 'webservice',
            'from_number' => $this->fromNumber($gateway),
            'message' => $message,
            'params' => [
                'recipients' => [
                    $this->recipient($phoneNormalized),
                ],
            ],
        ]);
    }

    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void
    {
        $patternCode = data_get($gateway->settings_json ?? [], 'otp_pattern_code');

        if (! is_string($patternCode) || trim($patternCode) === '') {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.missing_otp_pattern_code'),
            ]);
        }

        $this->post($gateway, [
            'sending_type' => 'pattern',
            'from_number' => $this->fromNumber($gateway),
            'code' => $patternCode,
            'recipients' => [
                $this->recipient($phoneNormalized),
            ],
            'params' => [
                'verification-code' => $code,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(SmsGateway $gateway, array $payload): void
    {
        $endpoint = $this->endpoint($gateway);

        try {
            Http::asJson()
                ->timeout($this->timeoutSeconds($gateway))
                ->connectTimeout($this->connectTimeoutSeconds($gateway))
                ->withHeaders([
                    'Authorization' => $this->authorizationToken($gateway),
                ])
                ->post($endpoint, $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            Log::warning('IPPanel SMS gateway connection failed.', [
                'gateway_id' => $gateway->id,
                'endpoint' => $endpoint,
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.ippanel_timeout'),
            ]);
        } catch (RequestException $exception) {
            Log::warning('IPPanel SMS gateway request failed.', [
                'gateway_id' => $gateway->id,
                'endpoint' => $endpoint,
                'status' => $exception->response?->status(),
                'exception' => $exception::class,
            ]);

            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.ippanel_send_failed'),
            ]);
        }
    }

    private function authorizationToken(SmsGateway $gateway): string
    {
        $token = data_get($gateway->encrypted_credentials ?? [], 'api_key');

        if (! is_string($token) || trim($token) === '') {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.missing_api_key'),
            ]);
        }

        return trim($token);
    }

    private function fromNumber(SmsGateway $gateway): string
    {
        $fromNumber = data_get($gateway->settings_json ?? [], 'from_number')
            ?? data_get($gateway->settings_json ?? [], 'sender');

        if (! is_string($fromNumber) || trim($fromNumber) === '') {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.missing_from_number'),
            ]);
        }

        return trim($fromNumber);
    }

    private function endpoint(SmsGateway $gateway): string
    {
        $endpoint = data_get($gateway->settings_json ?? [], 'endpoint_url');

        return is_string($endpoint) && trim($endpoint) !== ''
            ? trim($endpoint)
            : self::DEFAULT_ENDPOINT;
    }

    private function timeoutSeconds(SmsGateway $gateway): int
    {
        return $this->positiveInteger(
            data_get($gateway->settings_json ?? [], 'timeout_seconds'),
            (int) config('hairvision.sms.ippanel.timeout_seconds', 8)
        );
    }

    private function connectTimeoutSeconds(SmsGateway $gateway): int
    {
        return $this->positiveInteger(
            data_get($gateway->settings_json ?? [], 'connect_timeout_seconds'),
            (int) config('hairvision.sms.ippanel.connect_timeout_seconds', 3)
        );
    }

    private function positiveInteger(mixed $value, int $fallback): int
    {
        $number = is_numeric($value) ? (int) $value : $fallback;

        return $number > 0 ? $number : $fallback;
    }

    private function recipient(string $phoneNormalized): string
    {
        $digits = NumberNormalizer::digitsOnly($phoneNormalized);

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+98'.substr($digits, 1);
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '+98'.$digits;
        }

        return str_starts_with($phoneNormalized, '+') ? '+'.$digits : $phoneNormalized;
    }
}
