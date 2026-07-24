<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\ImageGenerationProvider;
use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;
use App\Services\Generation\GenerationStageCatalog;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Local provider that returns configured sample result images.
 *
 * @since 1.0.0
 */
class SampleImageProvider implements ImageGenerationProvider
{
    private const DISK = 'local';

    /**
     * Execute the code operation.
     *
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function code(): string
    {
        return 'sample_image';
    }

    /**
     * Execute the supports async operation.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function supportsAsync(): bool
    {
        return false;
    }

    /**
     * Resolve a local sample image for the requested progress period.
     *
     * @param  ImageGenerationRequest  $request  Incoming HTTP request.
     * @return ImageGenerationResponse HTTP response.
     *
     * @since 1.0.0
     */
    public function submit(ImageGenerationRequest $request): ImageGenerationResponse
    {
        $scope = 'ai.provider.sample_image.submit';

        $catalog = app(GenerationStageCatalog::class);
        $period = $catalog->normalizeKey($request->period);

        $configured = $catalog->sampleImagePath($period)
            ?? config('hairvision.sample_images.'.$period)
            ?? config('hairvision.sample_images.'.$request->period);

        $fallback = config('hairvision.sample_images.fallback');

        $samplePath = $this->resolveSamplePath($configured, $fallback, $period, $scope);

        if (! $samplePath) {
            return new ImageGenerationResponse(
                status: 'failed',
                metadata: [
                    'provider' => $this->code(),
                    'period' => $period,
                    'error' => 'sample_image_not_found',
                ],
            );
        }

        $response = new ImageGenerationResponse(
            status: 'completed',
            localPath: $samplePath,
            metadata: [
                'provider' => $this->code(),
                'period' => $period,
            ],
        );

        return $response;
    }

    /**
     * Return the known status for local sample provider jobs.
     *
     * @param  string  $providerJobId  The provider job id value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function poll(string $providerJobId): string
    {
        $scope = 'ai.provider.sample_image.poll';

        return 'completed';
    }

    /**
     * Return local file metadata for a completed sample response.
     *
     * @param  ImageGenerationResponse  $response  The response value.
     * @return array{disk: string, path: string|null, mime: string, size: int, metadata: array<string, mixed>}
     *
     * @since 1.0.0
     */
    public function download(ImageGenerationResponse $response): array
    {
        $scope = 'ai.provider.sample_image.download';

        $path = $response->localPath;

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Sample image response does not contain a local path.');
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            throw new RuntimeException('Sample image file is missing.');
        }

        $mime = $this->mimeForPath($path);
        $size = $disk->size($path);

        return [
            'disk' => self::DISK,
            'path' => $path,
            'mime' => $mime,
            'size' => $size,
            'metadata' => $response->metadata,
        ];
    }

    /**
     * Resolve an existing local sample path.
     *
     * @param  mixed  $configured  The configured value.
     * @param  mixed  $fallback  The fallback value.
     * @param  string  $period  The period value.
     * @param  string  $scope  The scope value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function resolveSamplePath(mixed $configured, mixed $fallback, string $period, string $scope): ?string
    {
        if (! is_string($configured) || $configured === '') {
            return $this->fallbackPath($fallback, $period, $scope);
        }

        $privatePath = 'private/'.$configured;

        if (Storage::disk(self::DISK)->exists($privatePath)) {
            return $privatePath;
        }

        if (Storage::disk(self::DISK)->exists($configured)) {
            return $configured;
        }

        return $this->fallbackPath($fallback, $period, $scope);
    }

    /**
     * Resolve an existing fallback path.
     *
     * @param  mixed  $fallback  The fallback value.
     * @param  string  $period  The period value.
     * @param  string  $scope  The scope value.
     * @return string|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private function fallbackPath(mixed $fallback, string $period, string $scope): ?string
    {
        if (is_string($fallback) && $fallback !== '' && Storage::disk(self::DISK)->exists($fallback)) {
            return $fallback;
        }

        return null;
    }

    /**
     * Infer MIME type from the file extension.
     *
     * @param  string  $path  Storage or resource path.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function mimeForPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }

    /**
     * Infer extension from MIME type.
     *
     * @param  string|null  $mime  The mime value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    private function extensionForMime(?string $mime): string
    {
        return match (strtolower((string) $mime)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
