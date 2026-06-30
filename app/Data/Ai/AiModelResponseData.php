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
     * @param  array<string, mixed>  $output
     * @param  array<string, mixed>  $metadata
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
    ) {}
}
