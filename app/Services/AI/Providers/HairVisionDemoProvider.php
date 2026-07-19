<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Services\Generation\GenerationStageCatalog;
use Illuminate\Support\Facades\Storage;

/**
 * Local sample-image provider.
 *
 * @since 1.0.0
 */
class HairVisionDemoProvider implements AiModelProviderInterface
{
    /**
     * Return local sample image bytes for the requested stage.
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $scope = 'ai.provider.demo.run';

        if (($request->settings['force_failure'] ?? false) || ($request->payload['force_failure'] ?? false)) {
            return $this->failed('demo_failed', __('ai_models.errors.provider_failed'));
        }

        $catalog = app(GenerationStageCatalog::class);
        $stageKey = $catalog->normalizeKey((string) data_get($request->payload, 'period'));

        $samplePath = $this->samplePath($request, $stageKey, $catalog, $scope);

        if (! $samplePath || ! Storage::disk('local')->exists($samplePath)) {
            return $this->failed('sample_image_not_found', __('ai_models.errors.sample_image_not_found'), $stageKey);
        }

        $disk = Storage::disk('local');
        $binary = $disk->get($samplePath);
        $mime = $this->mimeForPath($samplePath);

        $response = new AiModelResponseData(
            successful: true,
            status: 'completed',
            output: [
                'binary_content' => $binary,
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

        return $response;
    }

    /**
     * Resolve the local sample image path.
     */
    private function samplePath(
        AiModelRequestData $request,
        string $stageKey,
        GenerationStageCatalog $catalog,
        string $scope
    ): ?string {
        $configured = data_get($request->payload, 'output_config.sample_path')
            ?? data_get($request->payload, 'sample_path')
            ?? data_get($request->settings, 'sample_path')
            ?? data_get($request->settings, 'demo_images.'.$stageKey)
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

        if (is_string($fallback) && Storage::disk('local')->exists($fallback)) {
            return $fallback;
        }

        return $configured;
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
     * Build a failed AI model response.
     */
    private function failed(string $code, string $message, ?string $stageKey = null): AiModelResponseData
    {
        return new AiModelResponseData(
            successful: false,
            status: 'failed',
            metadata: [
                'provider' => 'sample_image',
                'stage_key' => $stageKey,
            ],
            errorCode: $code,
            safeErrorMessage: $message,
            creditConsumed: 0,
        );
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
