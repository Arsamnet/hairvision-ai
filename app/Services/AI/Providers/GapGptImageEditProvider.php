<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Support\DebugStepLogger;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * GapGPT image edit provider.
 *
 * @since 1.0.0
 */
class GapGptImageEditProvider implements AiModelProviderInterface
{
    private int $debugStepCounter = 0;

    /**
     * Run one staged image edit request.
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $scope = 'ai.provider.gapgpt.image_edit';
        $this->debugStepCounter = 0;

        $stageKey = (string) data_get($request->payload, 'period');

        $this->debugStep($scope, 'Request received', [
            'provider' => 'gapgpt',
            'clinic_id' => $request->clinic->id,
            'ai_model_access_id' => $request->access->id,
            'model_key' => $request->access->model_key,
            'usage_category_key' => $request->usageCategoryKey,
            'stage_key' => $stageKey,
            'payload_keys' => array_keys($request->payload),
        ]);

        $apiKey = data_get($request->credentials, 'api_key') ?: config('services.gapgpt.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            $this->debugStep($scope, 'API key missing', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
            ]);

            return $this->failed('missing_credentials', __('ai_models.errors.missing_credentials'), $stageKey);
        }

        $apiKey = $this->normalizeBearerToken($apiKey);

        $this->debugStep($scope, 'API key resolved', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'has_api_key' => $apiKey !== '',
        ]);

        $disk = (string) data_get($request->payload, 'upload_disk', 'local');
        $path = (string) data_get($request->payload, 'upload_path');
        $prompt = (string) data_get($request->payload, 'prompt', $request->prompt ?? '');

        $this->debugStep($scope, 'Upload payload resolved', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'disk' => $disk,
            'has_upload_path' => $path !== '',
            'prompt_length' => strlen($prompt),
        ]);

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            $this->debugStep($scope, 'Upload file missing', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'disk' => $disk,
                'has_upload_path' => $path !== '',
            ]);

            return $this->failed('missing_upload', __('ai_models.errors.missing_upload'), $stageKey);
        }

        try {
            $response = $this->postImageEdit($request, $apiKey, $disk, $path, $prompt, $scope);
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'GapGPT image edit request exception', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'exception' => $exception::class,
                'endpoint_host' => $this->endpointHost($request),
            ]);

            return $this->failed('provider_exception', __('ai_models.errors.provider_failed'), $stageKey);
        }

        $this->debugStep($scope, 'GapGPT image edit response received', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'http_status' => $response->status(),
            'http_successful' => $response->successful(),
            'request_id' => $this->requestId($response),
            'response_json_keys' => $this->responseJsonKeys($response),
        ]);

        if (! $response->successful()) {
            $context = [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'http_status' => $response->status(),
                'request_id' => $this->requestId($response),
                'endpoint_host' => $this->endpointHost($request),
                'response_body_length' => strlen($response->body()),
            ];

            Log::warning('GapGPT image edit request failed with HTTP error.', $context);

            $this->debugStep($scope, 'GapGPT image edit request failed', $context);

            return $this->failed(
                'provider_http_error',
                __('ai_models.errors.provider_failed'),
                $stageKey,
                [
                    'http_status' => $response->status(),
                    'request_id' => $this->requestId($response),
                ]
            );
        }

        $image = $this->extractImage($response, $request, $stageKey, $scope);

        if (! $image) {
            $this->debugStep($scope, 'GapGPT response missing usable image', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'http_status' => $response->status(),
                'request_id' => $this->requestId($response),
                'response_json_keys' => $this->responseJsonKeys($response),
            ]);

            return $this->failed(
                'missing_generated_image',
                __('ai_models.errors.missing_generated_image'),
                $stageKey,
                ['request_id' => $this->requestId($response)]
            );
        }

        $this->debugStep($scope, 'Generated image extracted', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'mime' => $image['mime'] ?? null,
            'extension' => $image['extension'] ?? null,
            'source' => $image['source'] ?? 'response_body',
            'binary_size' => is_string($image['binary_content'] ?? null) ? strlen($image['binary_content']) : null,
        ]);

        $result = new AiModelResponseData(
            successful: true,
            status: 'completed',
            output: $image + [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
            ],
            metadata: [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'model' => $this->modelName($request),
                'request_id' => $this->requestId($response),
                'output_format' => $image['extension'] ?? null,
                'usage' => $this->safeUsage($response->json('usage')),
            ],
            providerReference: $this->requestId($response) ?: (string) Str::uuid(),
            creditConsumed: 1,
        );

        $this->debugStep($scope, 'Provider response prepared', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'status' => $result->status,
            'provider_reference' => $result->providerReference,
            'credit_consumed' => $result->creditConsumed,
        ]);

        return $result;
    }

    /**
     * Submit the image edit request to GapGPT.
     */
    private function postImageEdit(
        AiModelRequestData $request,
        string $apiKey,
        string $disk,
        string $path,
        string $prompt,
        string $scope
    ): Response {
        $settings = $request->settings;
        $modelConfig = $request->modelConfig;
        $endpoint = (string) ($settings['endpoint_url'] ?? config('services.gapgpt.image_edit_endpoint') ?? $modelConfig['endpoint']);
        $timeout = $this->positiveInt($settings['timeout_seconds'] ?? $modelConfig['default_timeout'] ?? 90, 90);
        $connectTimeout = $this->positiveInt($settings['connect_timeout_seconds'] ?? $modelConfig['default_connect_timeout'] ?? 10, 10);
        $retryAttempts = max(1, $this->positiveInt($settings['retry_attempts'] ?? 1, 1));
        $retrySleep = $this->positiveInt($settings['retry_sleep_ms'] ?? 750, 750);

        $this->debugStep($scope, 'HTTP request settings resolved', [
            'provider' => 'gapgpt',
            'stage_key' => (string) data_get($request->payload, 'period'),
            'endpoint_host' => $this->urlHost($endpoint),
            'timeout_seconds' => $timeout,
            'connect_timeout_seconds' => $connectTimeout,
            'retry_attempts' => $retryAttempts,
            'retry_sleep_ms' => $retrySleep,
            'model' => $this->modelName($request),
            'response_format' => $this->responseFormat($request),
            'size' => $this->imageSize($request),
        ]);

        $contents = Storage::disk($disk)->get($path);

        $this->debugStep($scope, 'Upload content loaded for provider request', [
            'provider' => 'gapgpt',
            'stage_key' => (string) data_get($request->payload, 'period'),
            'disk' => $disk,
            'upload_size' => strlen($contents),
        ]);

        $fields = [
            'model' => $this->modelName($request),
            'prompt' => $prompt,
            'n' => 1,
            'response_format' => $this->responseFormat($request),
            'size' => $this->imageSize($request),
        ];

        $this->debugStep($scope, 'HTTP multipart payload prepared', [
            'provider' => 'gapgpt',
            'stage_key' => (string) data_get($request->payload, 'period'),
            'field_keys' => array_keys($fields),
            'prompt_length' => strlen($prompt),
            'has_image_attachment' => true,
        ]);

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry($retryAttempts, $retrySleep, throw: false)
            ->attach('image', $contents, basename($path))
            ->post($endpoint, $fields);
    }

    /**
     * Extract generated image bytes from GapGPT response.
     *
     * @return array<string, mixed>|null
     */
    private function extractImage(
        Response $response,
        AiModelRequestData $request,
        string $stageKey,
        string $scope
    ): ?array {
        $item = $response->json('data.0');

        $this->debugStep($scope, 'Image response item inspected', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'item_found' => is_array($item),
            'item_keys' => is_array($item) ? array_keys($item) : [],
        ]);

        if (! is_array($item)) {
            return null;
        }

        $b64 = $item['b64_json'] ?? null;

        if (is_string($b64) && $b64 !== '') {
            $binary = $this->decodeBase64Image($b64);

            $this->debugStep($scope, 'Base64 image inspected', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'base64_length' => strlen($b64),
                'decoded' => $binary !== null,
                'binary_size' => is_string($binary) ? strlen($binary) : null,
            ]);

            if ($binary === null) {
                return null;
            }

            return [
                'binary_content' => $binary,
                'mime' => $this->mimeFromResponse($response, $request),
                'extension' => $this->extensionFromResponse($response, $request),
            ];
        }

        $url = $item['url'] ?? null;

        if (! is_string($url) || $url === '') {
            $this->debugStep($scope, 'Image URL missing from response', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
            ]);

            return null;
        }

        $sourceHost = $this->urlHost($url);

        $this->debugStep($scope, 'Downloading generated image from provider URL', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'source_host' => $sourceHost,
        ]);

        try {
            $download = Http::timeout(45)->get($url);
        } catch (Throwable $exception) {
            $this->debugStep($scope, 'Generated image download exception', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'request_id' => $this->requestId($response),
                'source_host' => $sourceHost,
                'exception' => $exception::class,
            ]);

            return null;
        }

        $this->debugStep($scope, 'Generated image download response received', [
            'provider' => 'gapgpt',
            'stage_key' => $stageKey,
            'request_id' => $this->requestId($response),
            'source_host' => $sourceHost,
            'http_status' => $download->status(),
            'http_successful' => $download->successful(),
            'body_size' => strlen($download->body()),
        ]);

        if (! $download->successful() || $download->body() === '') {
            $this->debugStep($scope, 'Generated image download failed', [
                'provider' => 'gapgpt',
                'stage_key' => $stageKey,
                'request_id' => $this->requestId($response),
                'source_host' => $sourceHost,
                'http_status' => $download->status(),
                'body_size' => strlen($download->body()),
            ]);

            return null;
        }

        $mime = $download->header('Content-Type') ?: $this->mimeFromResponse($response, $request);
        $safeMime = str_starts_with($mime, 'image/') ? $mime : 'image/jpeg';

        return [
            'binary_content' => $download->body(),
            'mime' => $safeMime,
            'extension' => $this->extensionForMime($safeMime),
            'source' => 'provider_url',
            'stage_key' => $stageKey,
        ];
    }

    /**
     * Decode a base64 image payload.
     */
    private function decodeBase64Image(string $value): ?string
    {
        if (str_contains($value, ',')) {
            $value = (string) Str::of($value)->after(',');
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Resolve the provider model name.
     */
    private function modelName(AiModelRequestData $request): string
    {
        return (string) ($request->settings['model']
            ?? config('services.gapgpt.image_model')
            ?? $request->modelConfig['default_model']
            ?? 'gpt-image-2');
    }

    /**
     * Resolve the requested image size.
     */
    private function imageSize(AiModelRequestData $request): string
    {
        return (string) ($request->settings['size']
            ?? config('services.gapgpt.image_size')
            ?? $request->modelConfig['default_size']
            ?? '1024x1024');
    }

    /**
     * Resolve the provider response format.
     */
    private function responseFormat(AiModelRequestData $request): string
    {
        return (string) ($request->settings['response_format']
            ?? config('services.gapgpt.image_response_format')
            ?? $request->modelConfig['default_response_format']
            ?? 'url');
    }

    /**
     * Normalize bearer token input.
     */
    private function normalizeBearerToken(string $apiKey): string
    {
        $token = trim($apiKey);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }

    /**
     * Infer MIME type from provider response.
     */
    private function mimeFromResponse(Response $response, AiModelRequestData $request): string
    {
        $format = $response->json('output_format') ?: ($request->settings['output_format'] ?? 'jpeg');

        return match ($format) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    /**
     * Infer extension from provider response.
     */
    private function extensionFromResponse(Response $response, AiModelRequestData $request): string
    {
        return match ($this->mimeFromResponse($response, $request)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Infer extension from MIME type.
     */
    private function extensionForMime(?string $mime): string
    {
        return match (strtolower((string) $mime)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Resolve provider request id.
     */
    private function requestId(Response $response): ?string
    {
        $header = $response->header('x-request-id') ?: $response->header('x-gapgpt-request-id');
        $bodyId = $response->json('id');

        return is_string($header) && $header !== ''
            ? $header
            : (is_string($bodyId) ? $bodyId : null);
    }

    /**
     * Return safe JSON keys for response debugging.
     *
     * @return array<int, string>
     */
    private function responseJsonKeys(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? array_keys($json) : [];
    }

    /**
     * Resolve configured endpoint host.
     */
    private function endpointHost(AiModelRequestData $request): ?string
    {
        $endpoint = (string) ($request->settings['endpoint_url'] ?? $request->modelConfig['endpoint'] ?? '');

        return $this->urlHost($endpoint);
    }

    /**
     * Extract URL host for safe logs.
     */
    private function urlHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * Return safe usage metadata.
     *
     * @return array<string, mixed>|null
     */
    private function safeUsage(mixed $usage): ?array
    {
        return is_array($usage) ? $usage : null;
    }

    /**
     * Cast a value to a positive integer.
     */
    private function positiveInt(mixed $value, int $default): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int !== false && $int > 0 ? $int : $default;
    }

    /**
     * Build a failed AI model response.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function failed(string $code, string $message, ?string $stageKey = null, array $metadata = []): AiModelResponseData
    {
        return new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: ['provider' => 'gapgpt', 'stage_key' => $stageKey] + $metadata,
            errorCode: $code,
            safeErrorMessage: $message,
            creditConsumed: 0,
        );
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}
