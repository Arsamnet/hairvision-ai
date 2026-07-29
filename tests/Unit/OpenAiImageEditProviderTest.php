<?php

namespace Tests\Unit;

use App\Data\Ai\AiModelRequestData;
use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Services\AI\Providers\OpenAiImageEditProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
                'background' => 'transparent',
                'moderation' => 'low',
                'output_compression' => 55,
                'timeout_seconds' => 900,
                'connect_timeout_seconds' => 30,
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
                && $this->multipartField($body, 'background') === 'transparent'
                && $this->multipartField($body, 'moderation') === 'low'
                && $this->multipartField($body, 'n') === '1'
                && $this->multipartField($body, 'output_compression') === '55';
        });
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
