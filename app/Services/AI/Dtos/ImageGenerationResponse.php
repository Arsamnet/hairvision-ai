<?php

namespace App\Services\AI\Dtos;

/**
 * Immutable provider response payload for a submitted image-generation job.
 *
 * @since 1.0.0
 */
final class ImageGenerationResponse
{
    /**
     * @param  string  $status  Provider status such as `completed` or `failed`.
     * @param  string|null  $providerJobId  External provider job id when async.
     * @param  string|null  $binaryContent  Optional returned binary content.
     * @param  string|null  $localPath  Private local sample path for MVP provider.
     * @param  array<string, mixed>  $metadata  Provider metadata safe for internal storage.
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerJobId = null,
        public readonly ?string $binaryContent = null,
        public readonly ?string $localPath = null,
        public readonly array $metadata = [],
    ) {}
}
