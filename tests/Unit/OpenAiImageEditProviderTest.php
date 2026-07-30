<?php

namespace Tests\Unit;

use App\Data\Ai\AiModelRequestData;
use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Services\AI\Providers\OpenAiImageEditProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifies the OpenAI image edit adapter contract.
 *
 * @since 2.0.2
 */
class OpenAiImageEditProviderTest extends TestCase
{
    /**
     * Ensure configured values, the category prompt, and image[] are sent as multipart fields.
     *
     * @return void No value is returned.
     *
     * @since 2.0.2
     */
    public function test_provider_sends_openai_multipart_contract_and_decodes_base64_image(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/source.jpg', 'source-image-bytes');
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response([
                'created' => 123456,
                'output_format' => 'webp',
                'data' => [
                    ['b64_json' => base64_encode('generated-image-bytes')],
                ],
                'usage' => ['total_tokens' => 42],
            ], 200, ['x-request-id' => 'openai-request-id']),
        ]);

        $request = new AiModelRequestData(
            clinic: new Tenant,
            access: new AiModelAccess(['model_key' => 'openai']),
            usageCategoryKey: 'hair_transplant',
            modelConfig: config('ai-models.models.openai'),
            credentials: ['api_key' => 'Bearer test-openai-key'],
            settings: [
                'endpoint_url' => 'https://api.openai.com/v1/images/edits',
                'model' => 'gpt-image-1',
                'quality' => 'medium',
                'size' => '1024x1024',
                'output_format' => 'webp',
                'response_format' => 'url',
                'background' => 'transparent',
                'moderation' => 'low',
                'output_compression' => 55,
                'timeout_seconds' => 1000,
                'connect_timeout_seconds' => 50,
            ],
            prompt: 'fallback prompt',
            payload: [
                'period' => 'final_result',
                'upload_disk' => 'local',
                'upload_path' => 'private/source.jpg',
                'prompt' => 'category output prompt',
            ],
        );

        $response = app(OpenAiImageEditProvider::class)->run($request);

        $this->assertTrue($response->successful);
        $this->assertSame('generated-image-bytes', $response->output['binary_content']);
        $this->assertSame('image/webp', $response->output['mime']);
        $this->assertSame('webp', $response->output['extension']);
        $this->assertSame('openai', $response->metadata['provider']);
        $this->assertSame('openai-request-id', $response->providerReference);
        $this->assertSame(['total_tokens' => 42], $response->metadata['usage']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->body();

            return $request->url() === 'https://api.openai.com/v1/images/edits'
                && $request->header('Authorization') === ['Bearer test-openai-key']
                && $request->header('Accept') === ['application/json']
                && str_contains($request->header('Content-Type')[0] ?? '', 'multipart/form-data; boundary=')
                && str_contains($body, 'name="image[]"; filename="source.jpg"')
                && $this->multipartField($body, 'model') === 'gpt-image-1'
                && $this->multipartField($body, 'prompt') === 'category output prompt'
                && $this->multipartField($body, 'quality') === 'medium'
                && $this->multipartField($body, 'size') === '1024x1024'
                && $this->multipartField($body, 'output_format') === 'webp'
                && $this->multipartField($body, 'response_format') === null
                && $this->multipartField($body, 'background') === 'transparent'
                && $this->multipartField($body, 'moderation') === 'low'
                && $this->multipartField($body, 'n') === '1'
                && $this->multipartField($body, 'output_compression') === '55';
        });
    }

    /**
     * Ensure a proven pre-request connection timeout is retryable and fully logged safely.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_provider_marks_connect_timeout_retryable_and_logs_redacted_diagnostics(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/source.jpg', 'source-image-bytes');
        config([
            'app.debug' => false,
            'hairvision.generation.openai_connection_retries' => 2,
            'hairvision.generation.openai_connection_retry_delay_seconds' => 2,
        ]);
        Log::spy();
        $calls = 0;
        Http::fake(function () use (&$calls): never {
            $calls++;

            throw new ConnectionException(
                'cURL error 28: Connection timed out after 50002 milliseconds; key=test-secret-openai-key; prompt=confidential prompt; file=private/source.jpg',
            );
        });

        $response = app(OpenAiImageEditProvider::class)->run($this->request(
            apiKey: 'test-secret-openai-key',
            prompt: 'confidential prompt',
        ));

        $this->assertFalse($response->successful);
        $this->assertSame('provider_connection_failed', $response->errorCode);
        $this->assertTrue($response->retryable);
        $this->assertSame(2, $response->retryAfterSeconds);
        $this->assertSame(28, $response->metadata['curl_error_code']);
        $this->assertSame(1, $calls);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);

            return $message === 'OpenAI image edit connection failed.'
                && ($context['failure_stage'] ?? null) === 'connect'
                && ($context['retryable'] ?? null) === true
                && data_get($context, 'exception.curl_error_code') === 28
                && is_string($encoded)
                && ! str_contains($encoded, 'test-secret-openai-key')
                && ! str_contains($encoded, 'confidential prompt')
                && ! str_contains($encoded, 'private/source.jpg');
        })->once();
    }

    /**
     * Ensure an ambiguous response timeout is logged but never automatically retried.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_provider_does_not_retry_ambiguous_operation_timeout(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/source.jpg', 'source-image-bytes');
        Log::spy();
        $calls = 0;
        Http::fake(function () use (&$calls): never {
            $calls++;

            throw new ConnectionException('cURL error 28: Operation timed out after 1000000 milliseconds with 0 bytes received');
        });

        $response = app(OpenAiImageEditProvider::class)->run($this->request());

        $this->assertFalse($response->successful);
        $this->assertSame('provider_exception', $response->errorCode);
        $this->assertFalse($response->retryable);
        $this->assertNull($response->retryAfterSeconds);
        $this->assertSame(1, $calls);
    }

    /**
     * Ensure libcurl's alternate connect-timeout wording remains safely retryable.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_provider_retries_failed_to_connect_timeout_wording(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/source.jpg', 'source-image-bytes');
        Log::spy();
        Http::fake(function (): never {
            throw new ConnectionException(
                'cURL error 28: Failed to connect to api.openai.com port 443 after 12005 ms: Timeout was reached',
            );
        });

        $response = app(OpenAiImageEditProvider::class)->run($this->request());

        $this->assertFalse($response->successful);
        $this->assertSame('provider_connection_failed', $response->errorCode);
        $this->assertTrue($response->retryable);
        $this->assertSame('connect', $response->metadata['failure_stage']);
        $this->assertSame(28, $response->metadata['curl_error_code']);
    }

    /**
     * Ensure failed HTTP responses log useful extracted fields without raw sensitive content.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_provider_logs_structured_http_error_without_raw_response_or_secrets(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('private/source.jpg', 'source-image-bytes');
        Log::spy();
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response([
                'error' => [
                    'type' => 'invalid_request_error',
                    'code' => 'invalid_value',
                    'param' => 'size',
                    'message' => 'confidential prompt test-secret-openai-key private/source.jpg',
                ],
            ], 400, ['x-request-id' => 'openai-error-request-id']),
        ]);

        $response = app(OpenAiImageEditProvider::class)->run($this->request(
            apiKey: 'test-secret-openai-key',
            prompt: 'confidential prompt',
        ));

        $this->assertFalse($response->successful);
        $this->assertSame('provider_client_error', $response->errorCode);
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES);

            return $message === 'OpenAI image edit request failed with HTTP error.'
                && ($context['http_status'] ?? null) === 400
                && ($context['request_id'] ?? null) === 'openai-error-request-id'
                && data_get($context, 'provider_error.type') === 'invalid_request_error'
                && data_get($context, 'provider_error.code') === 'invalid_value'
                && data_get($context, 'provider_error.param') === 'size'
                && (int) data_get($context, 'provider_error.message_length') > 0
                && is_string(data_get($context, 'provider_error.message_sha256'))
                && data_get($context, 'provider_error.message') === null
                && is_string($encoded)
                && ! str_contains($encoded, 'test-secret-openai-key')
                && ! str_contains($encoded, 'confidential prompt')
                && ! str_contains($encoded, 'private/source.jpg');
        })->once();
    }

    /**
     * Build a minimal OpenAI request for failure-path tests.
     *
     * @param  string  $apiKey  Test API key.
     * @param  string  $prompt  Test output prompt.
     * @return AiModelRequestData Normalized provider request.
     *
     * @since 2.0.0
     */
    private function request(
        string $apiKey = 'test-openai-key',
        string $prompt = 'category output prompt',
    ): AiModelRequestData {
        return new AiModelRequestData(
            clinic: new Tenant(['id' => 10]),
            access: new AiModelAccess(['id' => 20, 'model_key' => 'openai']),
            usageCategoryKey: 'hair_transplant',
            modelConfig: config('ai-models.models.openai'),
            credentials: ['api_key' => $apiKey],
            settings: [
                'endpoint_url' => 'https://api.openai.com/v1/images/edits',
                'model' => 'gpt-image-2',
                'timeout_seconds' => 1000,
                'connect_timeout_seconds' => 50,
            ],
            prompt: $prompt,
            payload: [
                'generation_request_id' => 'request-uuid',
                'generation_attempt_id' => 'attempt-uuid',
                'period' => 'final_result',
                'upload_disk' => 'local',
                'upload_path' => 'private/source.jpg',
                'prompt' => $prompt,
            ],
        );
    }

    /**
     * Extract a scalar multipart field value from a recorded request body.
     *
     * @param  string  $body  Recorded multipart request body.
     * @param  string  $field  Multipart field name.
     * @return string|null Scalar field value.
     *
     * @since 2.0.2
     */
    private function multipartField(string $body, string $field): ?string
    {
        $pattern = '/name="'.preg_quote($field, '/').'"\RContent-Length: \d+\R\R(?P<value>.*?)\R--/s';

        if (! preg_match($pattern, $body, $matches)) {
            return null;
        }

        return trim((string) $matches['value']);
    }
}
