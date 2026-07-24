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
     *
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function code(): string;

    /**
     * Whether the provider requires async polling.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function supportsAsync(): bool;

    /**
     * Submit a generation request.
     *
     * @param  ImageGenerationRequest  $request  Incoming HTTP request.
     * @return ImageGenerationResponse HTTP response.
     *
     * @since 1.0.0
     */
    public function submit(ImageGenerationRequest $request): ImageGenerationResponse;

    /**
     * Poll an asynchronous provider job.
     *
     * @param  string  $providerJobId  The provider job id value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function poll(string $providerJobId): string;

    /**
     * Download or resolve completed output metadata.
     *
     * @param  ImageGenerationResponse  $response  The response value.
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function download(ImageGenerationResponse $response): array;
}
