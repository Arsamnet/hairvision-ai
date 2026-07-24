<?php

namespace Tests\Unit;

use App\Data\Ai\AiModelRequestData;
use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Services\AI\Providers\GapGptImageEditProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GapGptRateLimitTest extends TestCase
{
    public function test_explicit_rate_limit_response_is_safe_to_delay_and_retry(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('source.jpg', 'image-bytes');
        Http::fake([
            'https://api.gapgpt.app/v1/images/edits' => Http::response([], 429, [
                'Retry-After' => '45',
            ]),
        ]);

        $request = new AiModelRequestData(
            clinic: new Tenant,
            access: new AiModelAccess(['model_key' => 'gapgpt']),
            usageCategoryKey: 'hair_transplant',
            modelConfig: config('ai-models.models.gapgpt'),
            credentials: ['api_key' => 'test-key'],
            settings: [
                'endpoint_url' => 'https://api.gapgpt.app/v1/images/edits',
                'timeout_seconds' => 900,
            ],
            prompt: 'safe test prompt',
            payload: [
                'period' => 'final_result',
                'upload_disk' => 'local',
                'upload_path' => 'source.jpg',
                'prompt' => 'safe test prompt',
            ],
        );

        $response = app(GapGptImageEditProvider::class)->run($request);

        $this->assertFalse($response->successful);
        $this->assertSame('provider_rate_limited', $response->errorCode);
        $this->assertTrue($response->retryable);
        $this->assertSame(45, $response->retryAfterSeconds);
        Http::assertSentCount(1);
    }
}
