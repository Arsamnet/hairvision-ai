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
        $timeout = max(900, $this->positiveInt($settings['timeout_seconds'] ?? $modelConfig['default_timeout'] ?? 900, 900));
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
     * @return array<string, mixed>|null
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
            ?? $request->modelConfig['default_model']
            ?? 'gpt-image-2');
    }

    /**
     * Resolve the requested image size.
     */
    private function imageSize(AiModelRequestData $request): string
    {
        return (string) ($request->settings['size']
            ?? $request->modelConfig['default_size']
            ?? '1024x1024');
    }

    /**
     * Resolve the provider response format.
     */
    private function responseFormat(AiModelRequestData $request): string
    {
        return (string) ($request->settings['response_format']
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
}
