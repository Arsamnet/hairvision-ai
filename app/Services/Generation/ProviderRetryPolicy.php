<?php

namespace App\Services\Generation;

use App\Data\Ai\AiModelResponseData;

/**
 * Resolves bounded retry decisions for definite, non-billable provider failures.
 *
 * @since 2.0.0
 */
final class ProviderRetryPolicy
{
    /**
     * Resolve the next retry delay after a known number of prior retries.
     *
     * @param  AiModelResponseData  $response  Failed provider result.
     * @param  int  $previousRetries  Retry attempts already scheduled for the same reason.
     * @return array{reason: string, retry_number: int, max_retries: int, delay_seconds: int, cool_down_model: bool}|null Retry decision.
     *
     * @since 2.0.0
     */
    public function decision(AiModelResponseData $response, int $previousRetries): ?array
    {
        if (! $response->retryable) {
            return null;
        }

        return match ($response->errorCode) {
            'provider_rate_limited' => $this->rateLimitDecision($response, $previousRetries),
            'provider_connection_failed' => $this->openAiConnectionDecision($response, $previousRetries),
            default => null,
        };
    }

    /**
     * Resolve a bounded Retry-After decision for a definite HTTP 429 rejection.
     *
     * @param  AiModelResponseData  $response  Failed provider result.
     * @param  int  $previousRetries  Retry attempts already scheduled.
     * @return array{reason: string, retry_number: int, max_retries: int, delay_seconds: int, cool_down_model: bool}|null Retry decision.
     *
     * @since 2.0.0
     */
    private function rateLimitDecision(AiModelResponseData $response, int $previousRetries): ?array
    {
        $maxRetries = min(10, max(0, (int) config('hairvision.generation.provider_rate_limit_retries', 3)));

        if ($previousRetries >= $maxRetries) {
            return null;
        }

        $maximum = max(5, (int) config('hairvision.generation.provider_rate_limit_max_delay_seconds', 300));
        $delay = max(5, min(
            $maximum,
            $response->retryAfterSeconds
                ?? (int) config('hairvision.generation.provider_rate_limit_delay_seconds', 30),
        ));

        return [
            'reason' => 'provider_rate_limited',
            'retry_number' => $previousRetries + 1,
            'max_retries' => $maxRetries,
            'delay_seconds' => $delay,
            'cool_down_model' => true,
        ];
    }

    /**
     * Resolve exponential backoff for an OpenAI failure known to occur before delivery.
     *
     * @param  AiModelResponseData  $response  Failed provider result.
     * @param  int  $previousRetries  Retry attempts already scheduled.
     * @return array{reason: string, retry_number: int, max_retries: int, delay_seconds: int, cool_down_model: bool}|null Retry decision.
     *
     * @since 2.0.0
     */
    private function openAiConnectionDecision(AiModelResponseData $response, int $previousRetries): ?array
    {
        if (($response->metadata['provider'] ?? null) !== 'openai') {
            return null;
        }

        $maxRetries = min(10, max(0, (int) config('hairvision.generation.openai_connection_retries', 2)));

        if ($previousRetries >= $maxRetries) {
            return null;
        }

        $baseDelay = max(1, (int) config('hairvision.generation.openai_connection_retry_delay_seconds', 2));
        $maximum = max($baseDelay, (int) config('hairvision.generation.openai_connection_retry_max_delay_seconds', 20));
        $jitter = max(0, (int) config('hairvision.generation.openai_connection_retry_jitter_seconds', 2));
        $exponential = min($maximum, $baseDelay * (2 ** min($previousRetries, 8)));
        $delay = min($maximum, $exponential + ($jitter > 0 ? random_int(0, $jitter) : 0));

        return [
            'reason' => 'provider_connection_failed',
            'retry_number' => $previousRetries + 1,
            'max_retries' => $maxRetries,
            'delay_seconds' => max(1, $delay),
            'cool_down_model' => false,
        ];
    }
}
