<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;

/**
 * Local demo provider for the mockup model.
 *
 * This provider never calls external AI services and never generates images.
 *
 * @since 1.0.0
 */
class MockupAiProvider implements AiModelProviderInterface
{
    /**
     * Execute the mockup ai provider operation.
     *
     * @param  AiModelRequestData  $request  Incoming HTTP request.
     * @return AiModelResponseData HTTP response.
     *
     * @since 1.0.0
     */
    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $scope = 'ai.provider.mockup';

        $forceFailure = (bool) (($request->settings['force_failure'] ?? false) || ($request->payload['force_failure'] ?? false));

        if ($forceFailure) {
            return new AiModelResponseData(
                successful: false,
                status: 'failed',
                metadata: ['provider' => 'mockup'],
                errorCode: 'mockup_failed',
                safeErrorMessage: __('ai_models.errors.provider_failed'),
                creditConsumed: 0,
            );
        }

        return new AiModelResponseData(
            successful: true,
            status: 'completed',
            output: [
                'model_key' => $request->access->model_key,
                'usage_category_key' => $request->usageCategoryKey,
            ],
            metadata: [
                'provider' => 'mockup',
                'model_key' => $request->access->model_key,
            ],
            providerReference: 'mockup-'.now()->timestamp,
            creditConsumed: 1,
        );
    }
}
