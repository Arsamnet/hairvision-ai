<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;

/**
 * Contract for image-generation provider adapters.
 *
 * Controllers must not depend on concrete providers; jobs and services resolve
 * implementations through this interface.
 *
 * @since 1.0.0
 */
interface ImageGenerationProvider
{
    /**
     * Provider code stored in provider settings and job metadata.
     */
    public function code(): string;

    /**
     * Whether the provider requires async polling.
     */
    public function supportsAsync(): bool;

    /**
     * Submit a generation request.
     */
    public function submit(ImageGenerationRequest $request): ImageGenerationResponse;

    /**
     * Poll an asynchronous provider job.
     */
    public function poll(string $providerJobId): string;

    /**
     * Download or resolve completed output metadata.
     *
     * @return array<string, mixed>
     */
    public function download(ImageGenerationResponse $response): array;
}
