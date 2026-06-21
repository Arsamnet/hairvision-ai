<?php

namespace App\Services\AI\Dtos;

final class ImageGenerationRequest
{
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
