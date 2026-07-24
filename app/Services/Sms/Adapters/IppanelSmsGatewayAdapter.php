<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use App\Support\NumberNormalizer;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $message  The message value.
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
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
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $scope  The scope value.
     * @param  int  $step  The step value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    private function post(SmsGateway $gateway, array $payload, string $scope, int $step): int
    {
        $endpoint = $this->endpoint($gateway);
        $timeout = $this->timeoutSeconds($gateway);
        $connectTimeout = $this->connectTimeoutSeconds($gateway);
        $sendAttempts = $this->sendAttempts();
        $retryDelayMilliseconds = $this->retryDelayMilliseconds();
        $sendingType = $payload['sending_type'] ?? null;
        $lastResponse = null;
        $lastException = null;

        $step++;
        try {
            $authorizationHeader = $this->authorizationHeader($gateway);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        for ($attempt = 1; $attempt <= $sendAttempts; $attempt++) {
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

                if ($this->isSuccessfulResponse($response)) {
                    return $step + 1;
                }

                $lastResponse = $response;
                $lastException = null;
            } catch (Throwable $exception) {
                $lastResponse = null;
                $lastException = $exception;
            }

            if ($attempt < $sendAttempts && $retryDelayMilliseconds > 0) {
                usleep($retryDelayMilliseconds * 1000);
            }
        }

        $this->logDeliveryFailure(
            $gateway,
            $endpoint,
            $lastResponse,
            $lastException,
            $sendingType,
            $scope,
            $sendAttempts
        );

        throw ValidationException::withMessages([
            'request' => __('sms.delivery_failed_generic'),
        ]);
    }

    /**
     * Build the Authorization header value.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function authorizationHeader(SmsGateway $gateway): string
    {
        $token = $this->authorizationToken($gateway);

        return preg_replace('/^Bearer\s+/i', '', $token) ?: $token;
    }

    /**
     * Resolve the stored API token.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    private function connectTimeoutSeconds(SmsGateway $gateway): int
    {
        return $this->positiveInteger(
            data_get($gateway->settings_json ?? [], 'connect_timeout_seconds'),
            (int) config('hairvision.sms.ippanel.connect_timeout_seconds', 3)
        );
    }

    /**
     * Resolve the bounded number of provider delivery attempts.
     *
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    private function sendAttempts(): int
    {
        return max(3, (int) config('hairvision.sms.ippanel.send_attempts', 3));
    }

    /**
     * Resolve the pause between provider delivery attempts.
     *
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    private function retryDelayMilliseconds(): int
    {
        return max(0, (int) config('hairvision.sms.ippanel.retry_delay_milliseconds', 200));
    }

    /**
     * Return a positive integer or fallback.
     *
     * @param  mixed  $value  Input value supplied to the operation.
     * @param  int  $fallback  The fallback value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
     */
    private function positiveInteger(mixed $value, int $fallback): int
    {
        $number = is_numeric($value) ? (int) $value : $fallback;

        return $number > 0 ? $number : $fallback;
    }

    /**
     * Convert local mobile numbers to IPPanel recipient format.
     *
     * @param  string  $phoneNormalized  The phone normalized value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  Response  $response  The response value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
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
     * Log one safe delivery failure after all retry attempts are exhausted.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $endpoint  The endpoint value.
     * @param  Response|null  $response  The response value.
     * @param  Throwable|null  $exception  Failure being handled.
     * @param  mixed  $sendingType  The sending type value.
     * @param  string  $scope  The scope value.
     * @param  int  $attempts  The attempts value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function logDeliveryFailure(
        SmsGateway $gateway,
        string $endpoint,
        ?Response $response,
        ?Throwable $exception,
        mixed $sendingType,
        string $scope,
        int $attempts
    ): void {
        $body = $response?->json() ?? [];
        $context = [
            'gateway_id' => $gateway->id,
            'endpoint_host' => $this->endpointHost($endpoint),
            'http_status' => $response?->status(),
            'ippanel_status' => data_get($body, 'meta.status'),
            'sending_type' => is_string($sendingType) ? $sendingType : null,
            'operation' => $scope,
            'attempts' => $attempts,
            'failure_type' => $response instanceof Response ? 'rejected_response' : 'request_exception',
            'exception_class' => $exception ? $exception::class : null,
        ];

        Log::warning('IPPanel SMS gateway delivery failed after retries.', $context);
    }

    /**
     * Extract only the endpoint host for logs.
     *
     * @param  string  $endpoint  The endpoint value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
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
