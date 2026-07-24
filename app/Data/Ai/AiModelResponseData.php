<?php

namespace App\Data\Ai;

/**
 * Normalized provider response stored and returned by AI services.
 *
 * @since 1.0.0
 */
final class AiModelResponseData
{
    /**
     * Create a new ai model response data instance.
     *
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $metadata
     * @param  bool  $successful  The successful value.
     * @param  string  $status  Target lifecycle status.
     * @param  string|null  $providerReference  The provider reference value.
     * @param  string|null  $errorCode  The error code value.
     * @param  string|null  $safeErrorMessage  The safe error message value.
     * @param  int  $creditConsumed  The credit consumed value.
     * @param  bool  $retryable  The retryable value.
     * @param  int|null  $retryAfterSeconds  The retry after seconds value.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $status,
        public readonly array $output = [],
        public readonly array $metadata = [],
        public readonly ?string $providerReference = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $safeErrorMessage = null,
        public readonly int $creditConsumed = 1,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfterSeconds = null,
    ) {}
}
