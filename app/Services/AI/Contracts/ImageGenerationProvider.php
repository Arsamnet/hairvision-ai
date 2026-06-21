<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;

interface ImageGenerationProvider
{
    public function code(): string;

    public function supportsAsync(): bool;

    public function submit(ImageGenerationRequest $request): ImageGenerationResponse;

    public function poll(string $providerJobId): string;

    public function download(ImageGenerationResponse $response): array;
}
