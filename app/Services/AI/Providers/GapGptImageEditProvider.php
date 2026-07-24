<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
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
    /**
     * Run one staged image edit request.
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return AiModelResponseData HTTP response.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $scope = 'ai.provider.gapgpt.image_edit';

        $stageKey = (string) data_get($request->payload, 'period');

        $apiKey = data_get($request->credentials, 'api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            return $this->failed('missing_credentials', __('ai_models.errors.missing_credentials'), $stageKey);
        }

        $apiKey = $this->normalizeBearerToken($apiKey);

        $disk = (string) data_get($request->payload, 'upload_disk', 'local');
        $path = (string) data_get($request->payload, 'upload_path');
        $prompt = (string) data_get($request->payload, 'prompt', $request->prompt ?? '');

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return $this->failed('missing_upload', __('ai_models.errors.missing_upload'), $stageKey);
        }

        try {
            $response = $this->postImageEdit($request, $apiKey, $disk, $path, $prompt, $scope);
        } catch (Throwable $exception) {
            return $this->failed('provider_exception', __('ai_models.errors.provider_failed'), $stageKey);
        }

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
            return $this->failed(
                'missing_generated_image',
                __('ai_models.errors.missing_generated_image'),
                $stageKey,
                ['request_id' => $this->requestId($response)]
            );
        }

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

        return $result;
    }

    /**
     * Submit the image edit request to GapGPT.
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @param  string  $apiKey  The api key value.
     * @param  string  $disk  The disk value.
     * @param  string  $path  Storage or resource path.
     * @param  string  $prompt  The prompt value.
     * @param  string  $scope  The scope value.
     * @return Response HTTP response.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
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
        $endpoint = (string) ($settings['endpoint_url'] ?? $modelConfig['endpoint']);
        $timeout = min(900, max(60, $this->positiveInt($settings['timeout_seconds'] ?? $modelConfig['default_timeout'] ?? 900, 900)));
        $connectTimeout = max(30, $this->positiveInt($settings['connect_timeout_seconds'] ?? $modelConfig['default_connect_timeout'] ?? 30, 30));

        $contents = Storage::disk($disk)->get($path);

        $fields = [
            'model' => $this->modelName($request),
            'prompt' => $prompt,
            'n' => 1,
            'response_format' => $this->responseFormat($request),
            'size' => $this->imageSize($request),
        ];

        return Http::withToken($apiKey)
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->attach('image', $contents, basename($path))
            ->post($endpoint, $fields);
    }

    /**
     * Extract generated image bytes from GapGPT response.
     *
     * @param  Response  $response  The response value.
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @param  string  $stageKey  The stage key value.
     * @param  string  $scope  The scope value.
     * @return array<string, mixed>|null
     *
     * @since 1.0.0
     */
    private function extractImage(
        Response $response,
        AiModelRequestData $request,
        string $stageKey,
        string $scope
    ): ?array {
        $item = $response->json('data.0');

        if (! is_array($item)) {
            return null;
        }

        $b64 = $item['b64_json'] ?? null;

        if (is_string($b64) && $b64 !== '') {
            $binary = $this->decodeBase64Image($b64);

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
            return null;
        }

        $sourceHost = $this->urlHost($url);

        try {
            $download = Http::timeout(45)->get($url);
        } catch (Throwable $exception) {
            return null;
        }

        if (! $download->successful() || $download->body() === '') {
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
     *
     * @param  string  $value  Input value supplied to the operation.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
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
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function modelName(AiModelRequestData $request): string
    {
        return (string) ($request->settings['model']
            ?? $request->modelConfig['default_model']
            ?? 'gpt-image-2');
    }

    /**
     * Resolve the requested image size.
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function imageSize(AiModelRequestData $request): string
    {
        return (string) ($request->settings['size']
            ?? $request->modelConfig['default_size']
            ?? '1024x1024');
    }

    /**
     * Resolve the provider response format.
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function responseFormat(AiModelRequestData $request): string
    {
        return (string) ($request->settings['response_format']
            ?? $request->modelConfig['default_response_format']
            ?? 'url');
    }

    /**
     * Normalize bearer token input.
     *
     * @param  string  $apiKey  The api key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  Response  $response  The response value.
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  Response  $response  The response value.
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  string|null  $mime  The mime value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
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
     *
     * @param  Response  $response  The response value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
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
     * @param  Response  $response  The response value.
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function responseJsonKeys(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? array_keys($json) : [];
    }

    /**
     * Resolve configured endpoint host.
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function endpointHost(AiModelRequestData $request): ?string
    {
        $endpoint = (string) ($request->settings['endpoint_url'] ?? $request->modelConfig['endpoint'] ?? '');

        return $this->urlHost($endpoint);
    }

    /**
     * Extract URL host for safe logs.
     *
     * @param  string  $url  The url value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function urlHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    /**
     * Return safe usage metadata.
     *
     * @param  mixed  $usage  The usage value.
     * @return array<string, mixed>|null
     *
     * @since 1.0.0
     */
    private function safeUsage(mixed $usage): ?array
    {
        return is_array($usage) ? $usage : null;
    }

    /**
     * Cast a value to a positive integer.
     *
     * @param  mixed  $value  Input value supplied to the operation.
     * @param  int  $default  The default value.
     * @return int Calculated numeric value.
     *
     * @since 1.0.0
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
     * @param  string  $code  The code value.
     * @param  string  $message  The message value.
     * @param  string|null  $stageKey  The stage key value.
     * @param  bool  $retryable  The retryable value.
     * @param  int|null  $retryAfterSeconds  The retry after seconds value.
     * @return AiModelResponseData HTTP response.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
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
            metadata: ['provider' => 'gapgpt', 'stage_key' => $stageKey] + $metadata,
            errorCode: $code,
            safeErrorMessage: $message,
            creditConsumed: 0,
            retryable: $retryable,
            retryAfterSeconds: $retryAfterSeconds,
        );
    }

    /**
     * Retry after seconds.
     *
     * @param  Response  $response  The response value.
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
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

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
