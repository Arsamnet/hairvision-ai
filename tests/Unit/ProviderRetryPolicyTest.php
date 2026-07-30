<?php

namespace Tests\Unit;

use App\Data\Ai\AiModelResponseData;
use App\Services\Generation\ProviderRetryPolicy;
use Tests\TestCase;

/**
 * Verifies bounded provider retry decisions.
 *
 * @since 2.0.0
 */
final class ProviderRetryPolicyTest extends TestCase
{
    /**
     * Ensure OpenAI connection retries use bounded exponential backoff.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_openai_connection_retries_are_bounded_and_exponential(): void
    {
        config([
            'hairvision.generation.openai_connection_retries' => 2,
            'hairvision.generation.openai_connection_retry_delay_seconds' => 2,
            'hairvision.generation.openai_connection_retry_max_delay_seconds' => 20,
            'hairvision.generation.openai_connection_retry_jitter_seconds' => 0,
        ]);
        $response = new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: ['provider' => 'openai'],
            errorCode: 'provider_connection_failed',
            retryable: true,
            retryAfterSeconds: 2,
        );
        $policy = app(ProviderRetryPolicy::class);

        $first = $policy->decision($response, 0);
        $second = $policy->decision($response, 1);

        $this->assertSame(2, $first['delay_seconds']);
        $this->assertSame(1, $first['retry_number']);
        $this->assertSame(4, $second['delay_seconds']);
        $this->assertSame(2, $second['retry_number']);
        $this->assertNull($policy->decision($response, 2));
    }

    /**
     * Ensure a connection retry cannot be enabled for another provider accidentally.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_connection_retry_is_restricted_to_openai(): void
    {
        $response = new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: ['provider' => 'gapgpt'],
            errorCode: 'provider_connection_failed',
            retryable: true,
        );

        $this->assertNull(app(ProviderRetryPolicy::class)->decision($response, 0));
    }
}
