<?php

namespace App\Services\AI\Providers;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Support\DebugStepLogger;

/**
 * Local demo provider for the protected MVP mockup model.
 *
 * This provider never calls external AI services and never generates images.
 *
 * @since 1.0.0
 */
class MockupAiProvider implements AiModelProviderInterface
{
    private int $debugStepCounter = 0;

    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $scope = 'ai.provider.mockup';
        $this->debugStepCounter = 0;

        $this->debugStep($scope, 'Mockup AI provider run started', [
            'model_key' => $request->access->model_key,
            'usage_category_key' => $request->usageCategoryKey,
            'settings_keys' => array_keys($request->settings),
            'payload_keys' => array_keys($request->payload),
        ]);

        $forceFailure = (bool) (($request->settings['force_failure'] ?? false) || ($request->payload['force_failure'] ?? false));

        $this->debugStep($scope, 'Mockup force failure flag evaluated', [
            'model_key' => $request->access->model_key,
            'usage_category_key' => $request->usageCategoryKey,
            'force_failure' => $forceFailure,
        ]);

        if ($forceFailure) {
            $this->debugStep($scope, 'Mockup provider returning forced failure response', [
                'provider' => 'mockup',
                'status' => 'failed',
                'error_code' => 'mockup_failed',
                'credit_consumed' => 0,
            ]);

            return new AiModelResponseData(
                successful: false,
                status: 'failed',
                metadata: ['provider' => 'mockup'],
                errorCode: 'mockup_failed',
                safeErrorMessage: __('ai_models.errors.provider_failed'),
                creditConsumed: 0,
            );
        }

        $this->debugStep($scope, 'Mockup provider returning completed response', [
            'provider' => 'mockup',
            'status' => 'completed',
            'model_key' => $request->access->model_key,
            'usage_category_key' => $request->usageCategoryKey,
            'credit_consumed' => 1,
        ]);

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

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
    }
}