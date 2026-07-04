<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\ImageGenerationProvider;
use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;
use App\Services\Generation\GenerationStageCatalog;
use App\Support\DebugStepLogger;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Local MVP provider that returns configured sample result images.
 *
 * @since 1.0.0
 */
class SampleImageProvider implements ImageGenerationProvider
{
    private const DISK = 'local';

    private int $debugStepCounter = 0;

    public function code(): string
    {
        return 'sample_image';
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    /**
     * Resolve a local sample image for the requested progress period.
     */
    public function submit(ImageGenerationRequest $request): ImageGenerationResponse
    {
        $scope = 'ai.provider.sample_image.submit';
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Request received', [
            'provider' => $this->code(),
            'requested_period' => $request->period,
        ]);

        $catalog = app(GenerationStageCatalog::class);
        $period = $catalog->normalizeKey($request->period);

        $this->debugStep($scope, 'Period normalized', [
            'provider' => $this->code(),
            'requested_period' => $request->period,
            'normalized_period' => $period,
        ]);

        $configured = $catalog->sampleImagePath($period)
            ?? config('hairvision.sample_images.'.$period)
            ?? config('hairvision.sample_images.'.$request->period);

        $fallback = config('hairvision.sample_images.fallback');

        $this->debugStep($scope, 'Sample image configuration resolved', [
            'provider' => $this->code(),
            'period' => $period,
            'has_configured_sample' => is_string($configured) && $configured !== '',
            'has_fallback_sample' => is_string($fallback) && $fallback !== '',
        ]);

        $samplePath = $this->resolveSamplePath($configured, $fallback, $period, $scope);

        if (! $samplePath) {
            $this->debugStep($scope, 'Sample image not found', [
                'provider' => $this->code(),
                'period' => $period,
            ]);

            return new ImageGenerationResponse(
                status: 'failed',
                metadata: [
                    'provider' => $this->code(),
                    'period' => $period,
                    'error' => 'sample_image_not_found',
                ],
            );
        }

        $this->debugStep($scope, 'Sample image resolved', [
            'provider' => $this->code(),
            'period' => $period,
            'disk' => self::DISK,
            'path_exists' => true,
        ]);

        $response = new ImageGenerationResponse(
            status: 'completed',
            localPath: $samplePath,
            metadata: [
                'provider' => $this->code(),
                'period' => $period,
            ],
        );

        $this->debugStep($scope, 'Provider response prepared', [
            'provider' => $this->code(),
            'period' => $period,
            'status' => $response->status,
            'has_local_path' => is_string($response->localPath) && $response->localPath !== '',
        ]);

        return $response;
    }

    /**
     * Return the known status for local sample provider jobs.
     */
    public function poll(string $providerJobId): string
    {
        $scope = 'ai.provider.sample_image.poll';
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Poll requested', [
            'provider' => $this->code(),
            'has_provider_job_id' => $providerJobId !== '',
            'provider_job_id_hash' => $providerJobId !== '' ? hash('sha256', $providerJobId) : null,
            'status' => 'completed',
        ]);

        return 'completed';
    }

    /**
     * Return local file metadata for a completed sample response.
     *
     * @return array{disk: string, path: string|null, mime: string, size: int, metadata: array<string, mixed>}
     */
    public function download(ImageGenerationResponse $response): array
    {
        $scope = 'ai.provider.sample_image.download';
        $this->debugStepCounter = 0;

        $path = $response->localPath;

        $this->debugStep($scope, 'Download requested', [
            'provider' => $this->code(),
            'status' => $response->status,
            'has_local_path' => is_string($path) && $path !== '',
            'metadata_keys' => array_keys($response->metadata),
        ]);

        if (! is_string($path) || $path === '') {
            $this->debugStep($scope, 'Download failed because local path is missing', [
                'provider' => $this->code(),
                'status' => $response->status,
            ]);

            throw new RuntimeException('Sample image response does not contain a local path.');
        }

        $disk = Storage::disk(self::DISK);

        if (! $disk->exists($path)) {
            $this->debugStep($scope, 'Download failed because sample image is missing', [
                'provider' => $this->code(),
                'disk' => self::DISK,
                'path_exists' => false,
            ]);

            throw new RuntimeException('Sample image file is missing.');
        }

        $mime = $this->mimeForPath($path);
        $size = $disk->size($path);

        $this->debugStep($scope, 'Sample image metadata resolved', [
            'provider' => $this->code(),
            'disk' => self::DISK,
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
            'size' => $size,
        ]);

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
     */
    private function resolveSamplePath(mixed $configured, mixed $fallback, string $period, string $scope): ?string
    {
        if (! is_string($configured) || $configured === '') {
            $this->debugStep($scope, 'Configured sample path is empty', [
                'provider' => $this->code(),
                'period' => $period,
            ]);

            return $this->fallbackPath($fallback, $period, $scope);
        }

        $privatePath = 'private/'.$configured;

        if (Storage::disk(self::DISK)->exists($privatePath)) {
            $this->debugStep($scope, 'Private sample image selected', [
                'provider' => $this->code(),
                'period' => $period,
                'source' => 'private',
            ]);

            return $privatePath;
        }

        if (Storage::disk(self::DISK)->exists($configured)) {
            $this->debugStep($scope, 'Configured sample image selected', [
                'provider' => $this->code(),
                'period' => $period,
                'source' => 'configured',
            ]);

            return $configured;
        }

        $this->debugStep($scope, 'Configured sample image does not exist', [
            'provider' => $this->code(),
            'period' => $period,
        ]);

        return $this->fallbackPath($fallback, $period, $scope);
    }

    /**
     * Resolve an existing fallback path.
     */
    private function fallbackPath(mixed $fallback, string $period, string $scope): ?string
    {
        if (is_string($fallback) && $fallback !== '' && Storage::disk(self::DISK)->exists($fallback)) {
            $this->debugStep($scope, 'Fallback sample image selected', [
                'provider' => $this->code(),
                'period' => $period,
                'source' => 'fallback',
            ]);

            return $fallback;
        }

        $this->debugStep($scope, 'Fallback sample image unavailable', [
            'provider' => $this->code(),
            'period' => $period,
            'has_fallback' => is_string($fallback) && $fallback !== '',
        ]);

        return null;
    }

    /**
     * Infer MIME type from the file extension.
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
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}