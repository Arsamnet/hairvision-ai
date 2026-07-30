<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use Illuminate\Support\Str;

/**
 * Deterministic non-network provider used by queue and load-test scenarios.
 *
 * It is intentionally absent from the public model registry and cannot be
 * assigned to a clinic without an explicit test-only binding.
 *
 * @since 2.0.0
 */
class FakeImageProvider implements AiModelProviderInterface
{
    /**
     * Simulate provider latency and a selected safe response class.
     *
     * @param  AiModelRequestData  $request  Test-only provider request.
     * @return AiModelResponseData Simulated provider response.
     *
     * @since 2.0.0
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $minimum = max(0, (int) data_get($request->payload, 'fake_delay_min_ms', 20_000));
        $maximum = max($minimum, (int) data_get($request->payload, 'fake_delay_max_ms', 30_000));
        $delay = random_int($minimum, $maximum);

        if ($delay > 0) {
            usleep($delay * 1000);
        }

        $scenario = (string) data_get($request->payload, 'fake_scenario', 'success');
        $reference = 'fake-'.Str::uuid();

        return match ($scenario) {
            'timeout' => $this->failure('provider_exception', $reference),
            'rate_limited' => $this->failure('provider_rate_limited', $reference, true, 2),
            'server_error' => $this->failure('provider_server_error', $reference),
            'invalid' => new AiModelResponseData(
                successful: true,
                status: 'completed',
                output: ['binary_content' => 'not-an-image'],
                providerReference: $reference,
            ),
            default => new AiModelResponseData(
                successful: true,
                status: 'completed',
                output: [
                    'binary_content' => (string) base64_decode(
                        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                        true,
                    ),
                    'mime' => 'image/png',
                    'extension' => 'png',
                ],
                metadata: ['provider' => 'fake', 'delay_ms' => $delay],
                providerReference: $reference,
            ),
        };
    }

    /**
     * Build one simulated failed response.
     *
     * @param  string  $code  Safe failure code.
     * @param  string  $reference  Opaque request reference.
     * @param  bool  $retryable  Whether the rejection is definite and retryable.
     * @param  int|null  $retryAfter  Suggested delay in seconds.
     * @return AiModelResponseData Simulated failure.
     *
     * @since 2.0.0
     */
    private function failure(
        string $code,
        string $reference,
        bool $retryable = false,
        ?int $retryAfter = null,
    ): AiModelResponseData {
        return new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: ['provider' => 'fake'],
            providerReference: $reference,
            errorCode: $code,
            safeErrorMessage: __('ai_models.errors.provider_failed'),
            creditConsumed: 0,
            retryable: $retryable,
            retryAfterSeconds: $retryAfter,
        );
    }
}
