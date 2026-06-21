<?php

namespace App\Services\AI\Dtos;

final class ImageGenerationResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerJobId = null,
        public readonly ?string $binaryContent = null,
        public readonly ?string $localPath = null,
        public readonly array $metadata = [],
    ) {}
}
