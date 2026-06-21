<?php

namespace App\Services\AI\Providers;

use App\Services\AI\Contracts\ImageGenerationProvider;
use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Local MVP provider that returns configured sample result images.
 *
 * This provider must not call external services or generate/modify images.
 *
 * @since 1.0.0
 */
class SampleImageProvider implements ImageGenerationProvider
{
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
        $sampleKey = config('hairvision.sample_images.'.$request->period);
        $fallback = config('hairvision.sample_images.fallback');

        // Prefer private sample assets under storage/app/private when present
        $privatePath = 'private/'.$sampleKey;
        if (Storage::disk('local')->exists($privatePath)) {
            $sampleKey = $privatePath;
        } elseif (! Storage::disk('local')->exists($sampleKey)) {
            $sampleKey = $fallback;
        }

        if (! Storage::disk('local')->exists($sampleKey)) {
            return new ImageGenerationResponse(
                status: 'failed',
                metadata: ['error' => 'sample_image_not_found'],
            );
        }

        return new ImageGenerationResponse(
            status: 'completed',
            localPath: $sampleKey,
            metadata: [
                'provider' => 'sample_image',
                'period' => $request->period,
            ],
        );
    }

    /**
     * Return the known status for local sample provider jobs.
     */
    public function poll(string $providerJobId): string
    {
        return 'completed';
    }

    /**
     * Return local file metadata for a completed sample response.
     *
     * @return array{disk: string, path: string|null, mime: string, size: int, metadata: array<string, mixed>}
     */
    public function download(ImageGenerationResponse $response): array
    {
        return [
            'disk' => 'local',
            'path' => $response->localPath,
            'mime' => Storage::disk('local')->mimeType($response->localPath) ?: 'image/jpeg',
            'size' => Storage::disk('local')->size($response->localPath),
            'metadata' => $response->metadata,
        ];
    }
}
