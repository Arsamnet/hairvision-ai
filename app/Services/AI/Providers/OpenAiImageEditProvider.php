<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Support\SafeThrowableContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * OpenAI GPT Image edit provider.
 *
 * @since 2.0.2
 */
class OpenAiImageEditProvider implements AiModelProviderInterface
{
    /**
     * Run one OpenAI image edit request and return decoded image bytes.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @return AiModelResponseData Normalized provider response.
     *
     * @since 2.0.2
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $stageKey = (string) data_get($request->payload, 'period');
        $apiKey = data_get($request->credentials, 'api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return $this->failed('missing_credentials', __('ai_models.errors.missing_credentials'), $stageKey);
        }

        $disk = (string) data_get($request->payload, 'upload_disk', 'local');
        $path = (string) data_get($request->payload, 'upload_path');
        $prompt = (string) data_get($request->payload, 'prompt', $request->prompt ?? '');

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return $this->failed('missing_upload', __('ai_models.errors.missing_upload'), $stageKey);
        }

        try {
            $response = $this->postImageEdit(
                $request,
                $this->normalizeBearerToken($apiKey),
                $disk,
                $path,
                $prompt,
            );
        } catch (ConnectionException $exception) {
            $diagnostics = SafeThrowableContext::make($exception, [$apiKey, $prompt, $path]);
            $retryable = $this->isRetryablePreRequestConnectionFailure($diagnostics);
            $failureStage = $this->connectionFailureStage($diagnostics);

            Log::warning('OpenAI image edit connection failed.', $this->logContext($request, $stageKey, $prompt) + [
                'failure_stage' => $failureStage,
                'retryable' => $retryable,
                'retry_limit' => max(0, (int) config('hairvision.generation.openai_connection_retries', 2)),
                'retry_delay_seconds' => max(1, (int) config('hairvision.generation.openai_connection_retry_delay_seconds', 2)),
                'exception' => $diagnostics,
            ]);

            return $this->failed(
                $retryable ? 'provider_connection_failed' : 'provider_exception',
                __('ai_models.errors.provider_failed'),
                $stageKey,
                [
                    'failure_stage' => $failureStage,
                    'curl_error_code' => $diagnostics['curl_error_code'] ?? null,
                    'exception_class' => $exception::class,
                ],
                retryable: $retryable,
                retryAfterSeconds: $retryable
                    ? max(1, (int) config('hairvision.generation.openai_connection_retry_delay_seconds', 2))
                    : null,
            );
        } catch (Throwable $exception) {
            Log::error('OpenAI image edit request raised an unexpected exception.', $this->logContext($request, $stageKey, $prompt) + [
                'retryable' => false,
                'exception' => SafeThrowableContext::make($exception, [$apiKey, $prompt, $path]),
            ]);

            return $this->failed(
                'provider_exception',
                __('ai_models.errors.provider_failed'),
                $stageKey,
                [
                    'exception_class' => $exception::class,
                ],
            );
        }

        if (! $response->successful()) {
            Log::warning('OpenAI image edit request failed with HTTP error.', $this->logContext($request, $stageKey, $prompt) + [
                'http_status' => $response->status(),
                'request_id' => $this->requestId($response),
                'response_body_length' => strlen($response->body()),
                'retry_after_seconds' => $response->status() === 429 ? $this->retryAfterSeconds($response) : null,
                'provider_error' => $this->safeProviderError($response, [$apiKey, $prompt, $path]),
            ]);

            if ($response->status() === 429) {
                return $this->failed(
                    'provider_rate_limited',
                    __('ai_models.errors.provider_failed'),
                    $stageKey,
                    [
                        'http_status' => $response->status(),
                        'request_id' => $this->requestId($response),
                    ],
                    retryable: true,
                    retryAfterSeconds: $this->retryAfterSeconds($response),
                );
            }

            return $this->failed(
                $response->clientError() ? 'provider_client_error' : 'provider_server_error',
                __('ai_models.errors.provider_failed'),
                $stageKey,
                [
                    'http_status' => $response->status(),
                    'request_id' => $this->requestId($response),
                ],
            );
        }

        $binary = $this->decodeImage($response);

        if ($binary === null) {
            Log::warning('OpenAI image edit response did not contain a valid image.', $this->logContext($request, $stageKey, $prompt) + [
                'http_status' => $response->status(),
                'request_id' => $this->requestId($response),
                'response_body_length' => strlen($response->body()),
                'data_item_count' => is_array($response->json('data')) ? count($response->json('data')) : 0,
                'output_format' => $response->json('output_format'),
            ]);

            return $this->failed(
                'missing_generated_image',
                __('ai_models.errors.missing_generated_image'),
                $stageKey,
                ['request_id' => $this->requestId($response)],
            );
        }

        $format = $this->outputFormat($request, $response);

        return new AiModelResponseData(
            successful: true,
            status: 'completed',
            output: [
                'binary_content' => $binary,
                'mime' => $this->mimeForFormat($format),
                'extension' => $this->extensionForFormat($format),
                'provider' => 'openai',
                'stage_key' => $stageKey,
            ],
            metadata: [
                'provider' => 'openai',
                'stage_key' => $stageKey,
                'model' => $this->modelName($request),
                'request_id' => $this->requestId($response),
                'output_format' => $format,
                'usage' => $this->safeUsage($response->json('usage')),
            ],
            providerReference: $this->requestId($response) ?: (string) Str::uuid(),
            creditConsumed: 1,
        );
    }

    /**
     * Submit a multipart image edit request to the configured OpenAI-compatible URL.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @param  string  $apiKey  Bearer credential without a duplicated prefix.
     * @param  string  $disk  Private storage disk.
     * @param  string  $path  Private source image path.
     * @param  string  $prompt  Category-output prompt snapshot.
     * @return Response OpenAI HTTP response.
     *
     * @since 2.0.2
     */
    private function postImageEdit(
        AiModelRequestData $request,
        string $apiKey,
        string $disk,
        string $path,
        string $prompt,
    ): Response {
        $endpoint = $this->endpoint($request);
        $timeout = $this->requestTimeout($request);
        $connectTimeout = $this->connectTimeout($request);

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->attach('image[]', Storage::disk($disk)->get($path), basename($path))
            ->post($endpoint, [
                'model' => $this->modelName($request),
                'prompt' => $prompt,
                'quality' => $this->stringSetting($request, 'quality', 'default_quality', 'low'),
                'size' => $this->stringSetting($request, 'size', 'default_size', '816x816'),
                'output_format' => $this->stringSetting($request, 'output_format', 'default_output_format', 'jpeg'),
                'background' => $this->stringSetting($request, 'background', 'default_background', 'opaque'),
                'moderation' => $this->stringSetting($request, 'moderation', 'default_moderation', 'auto'),
                'n' => 1,
                'output_compression' => $this->boundedIntSetting(
                    $request,
                    'output_compression',
                    'default_output_compression',
                    70,
                    0,
                    100,
                ),
            ]);
    }

    /**
     * Decode the first base64 image returned by OpenAI.
     *
     * @param  Response  $response  OpenAI HTTP response.
     * @return string|null Decoded image bytes when present and valid.
     *
     * @since 2.0.2
     */
    private function decodeImage(Response $response): ?string
    {
        $value = $response->json('data.0.b64_json');

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_contains($value, ',')) {
            $value = (string) Str::of($value)->after(',');
        }

        $decoded = base64_decode($value, true);

        return $decoded === false || $decoded === '' ? null : $decoded;
    }

    /**
     * Resolve the configured provider model.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @return string Provider model identifier.
     *
     * @since 2.0.2
     */
    private function modelName(AiModelRequestData $request): string
    {
        return $this->stringSetting($request, 'model', 'default_model', 'gpt-image-2');
    }

    /**
     * Resolve a string setting with model-level and code-owned fallbacks.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @param  string  $settingKey  Persisted model setting key.
     * @param  string  $defaultKey  Code-owned model default key.
     * @param  string  $fallback  Final safe fallback.
     * @return string Resolved setting value.
     *
     * @since 2.0.2
     */
    private function stringSetting(
        AiModelRequestData $request,
        string $settingKey,
        string $defaultKey,
        string $fallback,
    ): string {
        return (string) ($request->settings[$settingKey]
            ?? $request->modelConfig[$defaultKey]
            ?? $fallback);
    }

    /**
     * Resolve and bound an integer setting.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @param  string  $settingKey  Persisted model setting key.
     * @param  string  $defaultKey  Code-owned model default key.
     * @param  int  $fallback  Final safe fallback.
     * @param  int  $minimum  Minimum accepted value.
     * @param  int  $maximum  Maximum accepted value.
     * @return int Bounded integer setting.
     *
     * @since 2.0.2
     */
    private function boundedIntSetting(
        AiModelRequestData $request,
        string $settingKey,
        string $defaultKey,
        int $fallback,
        int $minimum,
        int $maximum,
    ): int {
        $value = filter_var(
            $request->settings[$settingKey] ?? $request->modelConfig[$defaultKey] ?? $fallback,
            FILTER_VALIDATE_INT,
        );

        return min($maximum, max($minimum, $value === false ? $fallback : $value));
    }

    /**
     * Resolve the output format reported by OpenAI or requested by the model.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @param  Response  $response  OpenAI HTTP response.
     * @return string Output format identifier.
     *
     * @since 2.0.2
     */
    private function outputFormat(AiModelRequestData $request, Response $response): string
    {
        $format = $response->json('output_format');

        return is_string($format) && $format !== ''
            ? $format
            : $this->stringSetting($request, 'output_format', 'default_output_format', 'jpeg');
    }

    /**
     * Map an output format to a safe image MIME type.
     *
     * @param  string  $format  Provider output format.
     * @return string Image MIME type.
     *
     * @since 2.0.2
     */
    private function mimeForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Map an output format to a safe file extension.
     *
     * @param  string  $format  Provider output format.
     * @return string Safe file extension.
     *
     * @since 2.0.2
     */
    private function extensionForFormat(string $format): string
    {
        return match (strtolower($format)) {
            'png' => 'png',
            'webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Normalize an API key that may already include a Bearer prefix.
     *
     * @param  string  $apiKey  Configured API key.
     * @return string Token without the prefix.
     *
     * @since 2.0.2
     */
    private function normalizeBearerToken(string $apiKey): string
    {
        $token = trim($apiKey);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }

    /**
     * Resolve the opaque provider request identifier.
     *
     * @param  Response  $response  OpenAI HTTP response.
     * @return string|null Opaque request identifier.
     *
     * @since 2.0.2
     */
    private function requestId(Response $response): ?string
    {
        $header = $response->header('x-request-id');
        $bodyId = $response->json('id');

        return is_string($header) && $header !== ''
            ? $header
            : (is_string($bodyId) && $bodyId !== '' ? $bodyId : null);
    }

    /**
     * Keep only structured usage metadata returned by the provider.
     *
     * @param  mixed  $usage  Provider usage payload.
     * @return array<string, mixed>|null Safe usage metadata.
     *
     * @since 2.0.2
     */
    private function safeUsage(mixed $usage): ?array
    {
        return is_array($usage) ? $usage : null;
    }

    /**
     * Cast a positive timeout value or return its fallback.
     *
     * @param  mixed  $value  Candidate timeout value.
     * @param  int  $default  Fallback timeout.
     * @return int Positive timeout value.
     *
     * @since 2.0.2
     */
    private function positiveInt(mixed $value, int $default): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return $integer !== false && $integer > 0 ? $integer : $default;
    }

    /**
     * Resolve the configured provider endpoint.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @return string Provider endpoint.
     *
     * @since 2.0.2
     */
    private function endpoint(AiModelRequestData $request): string
    {
        return (string) ($request->settings['endpoint_url']
            ?? $request->settings['endpoint']
            ?? $request->modelConfig['endpoint']
            ?? 'https://api.openai.com/v1/images/edits');
    }

    /**
     * Resolve the total provider response timeout.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @return int Timeout in seconds.
     *
     * @since 2.0.2
     */
    private function requestTimeout(AiModelRequestData $request): int
    {
        $fallback = max(1, (int) config('hairvision.image_queue.provider_request_timeout', 1000));

        return max(60, $this->positiveInt(
            $request->settings['timeout_seconds'] ?? $request->modelConfig['default_timeout'] ?? 1000,
            $fallback,
        ));
    }

    /**
     * Resolve the provider connection timeout.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @return int Timeout in seconds.
     *
     * @since 2.0.2
     */
    private function connectTimeout(AiModelRequestData $request): int
    {
        $fallback = max(1, (int) config('hairvision.image_queue.provider_connect_timeout', 50));

        return max(1, $this->positiveInt(
            $request->settings['connect_timeout_seconds'] ?? $request->modelConfig['default_connect_timeout'] ?? 50,
            $fallback,
        ));
    }

    /**
     * Build safe request correlation and timeout context for provider errors.
     *
     * @param  AiModelRequestData  $request  Normalized model request.
     * @param  string  $stageKey  Category-output key.
     * @param  string  $prompt  Provider prompt, used only to report its length.
     * @return array<string, mixed> Safe provider log context.
     *
     * @since 2.0.2
     */
    private function logContext(AiModelRequestData $request, string $stageKey, string $prompt): array
    {
        $endpoint = $this->endpoint($request);

        return [
            'provider' => 'openai',
            'tenant_id' => $request->clinic->id,
            'ai_model_access_id' => $request->access->id,
            'generation_request_uuid' => data_get($request->payload, 'generation_request_id'),
            'generation_attempt_uuid' => data_get($request->payload, 'generation_attempt_id'),
            'generation_job_uuid' => data_get($request->payload, 'generation_job_id'),
            'usage_category' => $request->usageCategoryKey,
            'stage_key' => $stageKey,
            'model' => $this->modelName($request),
            'endpoint_scheme' => parse_url($endpoint, PHP_URL_SCHEME),
            'endpoint_host' => parse_url($endpoint, PHP_URL_HOST),
            'endpoint_path' => parse_url($endpoint, PHP_URL_PATH),
            'request_timeout_seconds' => $this->requestTimeout($request),
            'connect_timeout_seconds' => $this->connectTimeout($request),
            'prompt_length' => mb_strlen($prompt),
        ];
    }

    /**
     * Keep only redacted error fields from a failed provider response.
     *
     * @param  Response  $response  Failed provider response.
     * @param  array<int, string|null>  $redactValues  Request-specific values to remove.
     * @return array<string, mixed> Safe provider error summary.
     *
     * @since 2.0.2
     */
    private function safeProviderError(Response $response, array $redactValues): array
    {
        $message = $response->json('error.message');
        $safeMessage = is_string($message)
            ? SafeThrowableContext::redactMessage($message, $redactValues)
            : null;

        return array_filter([
            'type' => $response->json('error.type'),
            'code' => $response->json('error.code'),
            'param' => $response->json('error.param'),
            'message_length' => is_string($message) ? mb_strlen($message) : null,
            'message_sha256' => is_string($safeMessage) ? hash('sha256', $safeMessage) : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Determine whether cURL proved that no HTTP request reached the provider.
     *
     * @param  array<string, mixed>  $diagnostics  Sanitized exception diagnostics.
     * @return bool Whether automatic retry is safe.
     *
     * @since 2.0.2
     */
    private function isRetryablePreRequestConnectionFailure(array $diagnostics): bool
    {
        $curlErrorCode = $diagnostics['curl_error_code'] ?? null;

        if (in_array($curlErrorCode, [6, 7], true)) {
            return true;
        }

        if ($curlErrorCode !== 28) {
            return false;
        }

        foreach ($diagnostics['chain'] ?? [] as $failure) {
            $message = strtolower((string) ($failure['message'] ?? ''));
            $explicitConnectTimeout = str_contains($message, 'connection timed out')
                || (str_contains($message, 'failed to connect') && str_contains($message, 'timeout was reached'));

            if ($explicitConnectTimeout
                && ! str_contains($message, 'operation timed out')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Classify the failed pre-request transport stage.
     *
     * @param  array<string, mixed>  $diagnostics  Sanitized exception diagnostics.
     * @return string Safe connection-stage label.
     *
     * @since 2.0.2
     */
    private function connectionFailureStage(array $diagnostics): string
    {
        return match ($diagnostics['curl_error_code'] ?? null) {
            6 => 'dns',
            7, 28 => 'connect',
            default => 'unknown',
        };
    }

    /**
     * Build a safe failed provider response.
     *
     * @param  string  $code  Safe machine-readable error code.
     * @param  string  $message  Localized generic error message.
     * @param  string|null  $stageKey  Category-output key.
     * @param  array<string, mixed>  $metadata  Safe provider metadata.
     * @param  bool  $retryable  Whether a definite provider rejection may be retried.
     * @param  int|null  $retryAfterSeconds  Provider cooldown in seconds.
     * @return AiModelResponseData Failed provider response.
     *
     * @since 2.0.2
     */
    private function failed(
        string $code,
        string $message,
        ?string $stageKey = null,
        array $metadata = [],
        bool $retryable = false,
        ?int $retryAfterSeconds = null,
    ): AiModelResponseData {
        return new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: ['provider' => 'openai', 'stage_key' => $stageKey] + $metadata,
            errorCode: $code,
            safeErrorMessage: $message,
            creditConsumed: 0,
            retryable: $retryable,
            retryAfterSeconds: $retryAfterSeconds,
        );
    }

    /**
     * Parse a bounded Retry-After response header.
     *
     * @param  Response  $response  OpenAI HTTP response.
     * @return int Cooldown in seconds.
     *
     * @since 2.0.2
     */
    private function retryAfterSeconds(Response $response): int
    {
        $header = trim((string) $response->header('Retry-After'));

        if (ctype_digit($header)) {
            return max(1, (int) $header);
        }

        $timestamp = $header !== '' ? strtotime($header) : false;

        return $timestamp === false ? 30 : max(1, $timestamp - time());
    }
}
