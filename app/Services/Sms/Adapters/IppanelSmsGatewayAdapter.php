<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use App\Support\NumberNormalizer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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

    /**
     * Send a normal SMS message.
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void
    {
        $scope = 'sms.adapter.ippanel.send';
        $step = 0;

        $step++;
        try {
            $fromNumber = $this->fromNumber($gateway);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $step++;
        $this->post($gateway, [
            'sending_type' => 'webservice',
            'from_number' => $fromNumber,
            'params' => [
                'recipients' => [
                    $this->recipient($phoneNormalized),
                ],
                'message' => $message,
            ],
        ], $scope, $step);
    }

    /**
     * Send an OTP SMS through an IPPanel pattern.
     */
    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void
    {
        $scope = 'sms.adapter.ippanel.send_otp';
        $step = 0;

        $step++;
        $patternCode = data_get($gateway->settings_json ?? [], 'otp_pattern_code');

        if (! is_string($patternCode) || trim($patternCode) === '') {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.missing_otp_pattern_code'),
            ]);
        }

        try {
            $fromNumber = $this->fromNumber($gateway);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $step++;
        $this->post($gateway, [
            'sending_type' => 'pattern',
            'from_number' => $fromNumber,
            'code' => trim($patternCode),
            'recipients' => [
                $this->recipient($phoneNormalized),
            ],
            'params' => [
                $this->otpPatternVariable($gateway) => $code,
            ],
        ], $scope, $step);
    }

    /**
     * Send the prepared payload to IPPanel.
     *
     * @param  array<string, mixed>  $payload
     */
    private function post(SmsGateway $gateway, array $payload, string $scope, int $step): int
    {
        $endpoint = $this->endpoint($gateway);
        $timeout = $this->timeoutSeconds($gateway);
        $connectTimeout = $this->connectTimeoutSeconds($gateway);
        $sendingType = $payload['sending_type'] ?? null;

        $step++;
        try {
            $authorizationHeader = $this->authorizationHeader($gateway);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $step++;
        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Authorization' => $authorizationHeader,
                ])
                ->post($endpoint, $payload);

            $step++;
            if (! $this->isSuccessfulResponse($response)) {
                $this->logFailedResponse($gateway, $endpoint, $response, $sendingType, $scope, $step);

                throw ValidationException::withMessages([
                    'sms_gateway' => __('sms.ippanel_send_failed'),
                ]);
            }

            return $step + 1;
        } catch (ConnectionException $exception) {
            throw ValidationException::withMessages([
                'sms_gateway' => __('sms.ippanel_timeout'),
            ]);
        }
    }

    /**
     * Build the Authorization header value.
     */
    private function authorizationHeader(SmsGateway $gateway): string
    {
        $token = $this->authorizationToken($gateway);

        return preg_replace('/^Bearer\s+/i', '', $token) ?: $token;
    }

    /**
     * Resolve the stored API token.
     */
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

    /**
     * Resolve the sender number.
     */
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

    /**
     * Resolve the OTP pattern variable.
     */
    private function otpPatternVariable(SmsGateway $gateway): string
    {
        $variable = data_get($gateway->settings_json ?? [], 'otp_pattern_variable')
            ?? config('hairvision.sms.ippanel.otp_pattern_variable', 'verification-code');

        return is_string($variable) && trim($variable) !== ''
            ? trim($variable)
            : 'verification-code';
    }

    /**
     * Resolve the IPPanel endpoint.
     */
    private function endpoint(SmsGateway $gateway): string
    {
        $endpoint = data_get($gateway->settings_json ?? [], 'endpoint_url');

        return is_string($endpoint) && trim($endpoint) !== ''
            ? trim($endpoint)
            : self::DEFAULT_ENDPOINT;
    }

    /**
     * Resolve request timeout.
     */
    private function timeoutSeconds(SmsGateway $gateway): int
    {
        return $this->positiveInteger(
            data_get($gateway->settings_json ?? [], 'timeout_seconds'),
            (int) config('hairvision.sms.ippanel.timeout_seconds', 8)
        );
    }

    /**
     * Resolve connection timeout.
     */
    private function connectTimeoutSeconds(SmsGateway $gateway): int
    {
        return $this->positiveInteger(
            data_get($gateway->settings_json ?? [], 'connect_timeout_seconds'),
            (int) config('hairvision.sms.ippanel.connect_timeout_seconds', 3)
        );
    }

    /**
     * Return a positive integer or fallback.
     */
    private function positiveInteger(mixed $value, int $fallback): int
    {
        $number = is_numeric($value) ? (int) $value : $fallback;

        return $number > 0 ? $number : $fallback;
    }

    /**
     * Convert local mobile numbers to IPPanel recipient format.
     */
    private function recipient(string $phoneNormalized): string
    {
        $digits = NumberNormalizer::digitsOnly($phoneNormalized);

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '98'.substr($digits, 1);
        }

        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '9') && strlen($digits) === 10) {
            return '98'.$digits;
        }

        return $digits !== '' ? $digits : $phoneNormalized;
    }

    /**
     * Determine whether IPPanel accepted the request.
     */
    private function isSuccessfulResponse(Response $response): bool
    {
        if (! $response->successful()) {
            return false;
        }

        $metaStatus = data_get($response->json() ?? [], 'meta.status');

        if (is_bool($metaStatus)) {
            return $metaStatus;
        }

        if (is_string($metaStatus) && in_array(strtolower($metaStatus), ['false', '0'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Log a safe failed IPPanel response.
     */
    private function logFailedResponse(
        SmsGateway $gateway,
        string $endpoint,
        Response $response,
        mixed $sendingType,
        string $scope,
        int $step
    ): int {
        $body = $response->json() ?? [];
        $context = [
            'gateway_id' => $gateway->id,
            'endpoint_host' => $this->endpointHost($endpoint),
            'http_status' => $response->status(),
            'ippanel_status' => data_get($body, 'meta.status'),
            'ippanel_message' => $this->safeContextText(data_get($body, 'meta.message')),
            'sending_type' => is_string($sendingType) ? $sendingType : null,
        ];

        Log::warning('IPPanel SMS gateway request failed.', $context);

        return $step + 1;
    }

    /**
     * Sanitize external context text before logging.
     */
    private function safeContextText(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = (string) $value;
        $text = preg_replace('/\+?\d{6,}/', '[number]', $text) ?: $text;

        return mb_strlen($text) > 200 ? mb_substr($text, 0, 200) : $text;
    }

    /**
     * Extract only the endpoint host for logs.
     */
    private function endpointHost(string $endpoint): ?string
    {
        $host = parse_url($endpoint, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
