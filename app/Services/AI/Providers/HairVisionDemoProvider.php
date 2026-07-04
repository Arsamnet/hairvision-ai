<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Services\Generation\GenerationStageCatalog;
use App\Support\DebugStepLogger;
use Illuminate\Support\Facades\Storage;

/**
 * Local sample-image provider.
 *
 * @since 1.0.0
 */
class HairVisionDemoProvider implements AiModelProviderInterface
{
    private int $debugStepCounter = 0;

    /**
     * Return local sample image bytes for the requested stage.
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $scope = 'ai.provider.demo.run';
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Request received', [
            'provider' => 'sample_image',
            'clinic_id' => $request->clinic->id,
            'ai_model_access_id' => $request->access->id,
            'model_key' => $request->access->model_key,
            'usage_category_key' => $request->usageCategoryKey,
            'payload_keys' => array_keys($request->payload),
            'settings_keys' => array_keys($request->settings),
        ]);

        if (($request->settings['force_failure'] ?? false) || ($request->payload['force_failure'] ?? false)) {
            $this->debugStep($scope, 'Forced failure requested', [
                'provider' => 'sample_image',
                'ai_model_access_id' => $request->access->id,
                'model_key' => $request->access->model_key,
            ]);

            return $this->failed('demo_failed', __('ai_models.errors.provider_failed'));
        }

        $catalog = app(GenerationStageCatalog::class);
        $stageKey = $catalog->normalizeKey((string) data_get($request->payload, 'period'));

        $this->debugStep($scope, 'Stage key resolved', [
            'provider' => 'sample_image',
            'stage_key' => $stageKey,
        ]);

        $samplePath = $this->samplePath($request, $stageKey, $catalog, $scope);

        if (! $samplePath || ! Storage::disk('local')->exists($samplePath)) {
            $this->debugStep($scope, 'Sample image missing', [
                'provider' => 'sample_image',
                'model_key' => $request->access->model_key,
                'ai_model_access_id' => $request->access->id,
                'clinic_id' => $request->clinic->id,
                'usage_category_key' => $request->usageCategoryKey,
                'stage_key' => $stageKey,
                'has_sample_path' => is_string($samplePath) && $samplePath !== '',
            ]);

            return $this->failed('sample_image_not_found', __('ai_models.errors.sample_image_not_found'), $stageKey);
        }

        $disk = Storage::disk('local');
        $binary = $disk->get($samplePath);
        $mime = $this->mimeForPath($samplePath);

        $this->debugStep($scope, 'Sample image loaded', [
            'provider' => 'sample_image',
            'stage_key' => $stageKey,
            'mime' => $mime,
            'extension' => $this->extensionForMime($mime),
            'binary_size' => strlen($binary),
        ]);

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

        $this->debugStep($scope, 'Provider response prepared', [
            'provider' => 'sample_image',
            'stage_key' => $stageKey,
            'status' => $response->status,
            'provider_reference' => $response->providerReference,
            'credit_consumed' => $response->creditConsumed,
        ]);

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
        $configured = data_get($request->settings, 'demo_images.'.$stageKey)
            ?? $catalog->sampleImagePath($stageKey)
            ?? config('hairvision.sample_images.'.$stageKey);

        $this->debugStep($scope, 'Configured sample path resolved', [
            'provider' => 'sample_image',
            'stage_key' => $stageKey,
            'has_configured_path' => is_string($configured) && $configured !== '',
            'source' => data_get($request->settings, 'demo_images.'.$stageKey) ? 'settings' : 'catalog_or_config',
        ]);

        if (! is_string($configured) || $configured === '') {
            return null;
        }

        $privatePath = 'private/'.$configured;

        if (Storage::disk('local')->exists($privatePath)) {
            $this->debugStep($scope, 'Private sample image path selected', [
                'provider' => 'sample_image',
                'stage_key' => $stageKey,
                'path_exists' => true,
            ]);

            return $privatePath;
        }

        if (Storage::disk('local')->exists($configured)) {
            $this->debugStep($scope, 'Configured sample image path selected', [
                'provider' => 'sample_image',
                'stage_key' => $stageKey,
                'path_exists' => true,
            ]);

            return $configured;
        }

        $fallback = config('hairvision.sample_images.fallback');

        if (is_string($fallback) && Storage::disk('local')->exists($fallback)) {
            $this->debugStep($scope, 'Fallback sample image path selected', [
                'provider' => 'sample_image',
                'stage_key' => $stageKey,
                'fallback_exists' => true,
            ]);

            return $fallback;
        }

        $this->debugStep($scope, 'No existing sample image path found', [
            'provider' => 'sample_image',
            'stage_key' => $stageKey,
            'has_fallback' => is_string($fallback) && $fallback !== '',
        ]);

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
    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}