<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Services\Generation\GenerationStageCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Local HairVision demo provider backed by configured sample images.
 *
 * This provider never calls external services and never generates or edits
 * images. It only returns existing local sample image bytes for the requested
 * generation stage.
 *
 * @since 1.0.0
 */
class HairVisionDemoProvider implements AiModelProviderInterface
{
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        if (($request->settings['force_failure'] ?? false) || ($request->payload['force_failure'] ?? false)) {
            return $this->failed('demo_failed', __('ai_models.errors.provider_failed'));
        }

        $catalog = app(GenerationStageCatalog::class);
        $stageKey = $catalog->normalizeKey((string) data_get($request->payload, 'period'));
        $samplePath = $this->samplePath($request, $stageKey, $catalog);

        if (! $samplePath || ! Storage::disk('local')->exists($samplePath)) {
            Log::warning('HairVision demo sample image is missing.', [
                'provider' => 'sample_image',
                'model_key' => $request->access->model_key,
                'ai_model_access_id' => $request->access->id,
                'clinic_id' => $request->clinic->id,
                'usage_category_key' => $request->usageCategoryKey,
                'stage_key' => $stageKey,
            ]);

            return $this->failed('sample_image_not_found', __('ai_models.errors.sample_image_not_found'), $stageKey);
        }

        $disk = Storage::disk('local');
        $mime = $disk->mimeType($samplePath) ?: 'image/jpeg';

        return new AiModelResponseData(
            successful: true,
            status: 'completed',
            output: [
                'binary_content' => $disk->get($samplePath),
                'mime' => str_starts_with($mime, 'image/') ? $mime : 'image/jpeg',
                'extension' => $this->extensionForMime($mime),
            ],
            metadata: [
                'provider' => 'sample_image',
                'model_key' => $request->access->model_key,
                'stage_key' => $stageKey,
            ],
            providerReference: 'sample-image-'.$stageKey,
            creditConsumed: 1,
        );
    }

    private function samplePath(AiModelRequestData $request, string $stageKey, GenerationStageCatalog $catalog): ?string
    {
        $configured = data_get($request->settings, 'demo_images.'.$stageKey)
            ?? $catalog->sampleImagePath($stageKey)
            ?? config('hairvision.sample_images.'.$stageKey);

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        $privatePath = 'private/'.$configured;

        if (Storage::disk('local')->exists($privatePath)) {
            return $privatePath;
        }

        if (Storage::disk('local')->exists($configured)) {
            return $configured;
        }

        $fallback = config('hairvision.sample_images.fallback');

        return is_string($fallback) && Storage::disk('local')->exists($fallback)
            ? $fallback
            : $configured;
    }

    private function extensionForMime(?string $mime): string
    {
        return match (strtolower((string) $mime)) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
    }

    private function failed(string $code, string $message, ?string $stageKey = null): AiModelResponseData
    {
        return new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: ['provider' => 'sample_image', 'stage_key' => $stageKey],
            errorCode: $code,
            safeErrorMessage: $message,
            creditConsumed: 0,
        );
    }
}
