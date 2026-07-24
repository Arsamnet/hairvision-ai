<?php

namespace App\Services\AI\Dtos;

/**
 * Immutable provider request payload for one image-generation period.
 *
 * @since 1.0.0
 */
final class ImageGenerationRequest
{
    /**
     * Create a new image generation request instance.
     *
     * @param  string  $tenantUuid  Public UUID for provider path scoping.
     * @param  string  $leadUuid  Public UUID for provider path scoping.
     * @param  string  $uploadPath  Private upload path on the configured disk.
     * @param  string  $period  Stage key such as `two_weeks`.
     * @param  string  $prompt  Provider prompt or local sample description.
     * @param  string|null  $modelName  Optional provider model override.
     * @param  array<string, mixed>  $settings  Provider-specific settings.
     *
     * @since 1.0.0
     */
    public function __construct(
        public readonly string $tenantUuid,
        public readonly string $leadUuid,
        public readonly string $uploadPath,
        public readonly string $period,
        public readonly string $prompt,
        public readonly ?string $modelName = null,
        public readonly array $settings = [],
    ) {}
}
