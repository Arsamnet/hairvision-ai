<?php

namespace App\Services\AI;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Models\AiModelAccess;
use App\Support\DebugStepLogger;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Resolves AI model config, categories, and providers.
 *
 * @since 1.0.0
 */
class AiModelResolver
{
    /**
     * Return configured AI models.
     *
     * @return array<string, array<string, mixed>>
     */
    public function models(): array
    {
        return config('ai-models.models', []);
    }

    /**
     * Return configured usage categories.
     *
     * @return array<string, array<string, mixed>>
     */
    public function usageCategories(): array
    {
        return config('ai-models.usage_categories', []);
    }

    /**
     * Resolve one configured model by key.
     *
     * @return array<string, mixed>
     */
    public function model(string $key): array
    {
        $scope = 'ai.model_resolver.model';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'model_key' => $key,
        ]);

        $model = $this->models()[$key] ?? null;

        $step = $this->workflowStep($scope, $step, 'Model lookup completed', [
            'model_key' => $key,
            'model_found' => is_array($model),
        ]);

        if (! is_array($model)) {
            $this->workflowStep($scope, $step, 'Unknown model key', [
                'model_key' => $key,
            ]);

            throw new InvalidArgumentException(__('ai_models.errors.unknown_model'));
        }

        $this->workflowStep($scope, $step, 'Model resolved', [
            'model_key' => $key,
            'config_keys' => array_keys($model),
            'driver' => is_string($model['driver'] ?? null) ? $model['driver'] : null,
            'provider' => is_string($model['provider'] ?? null) ? $model['provider'] : null,
            'is_demo' => (bool) ($model['is_demo'] ?? false),
        ]);

        return $model;
    }

    /**
     * Resolve one configured usage category by key.
     *
     * @return array<string, mixed>
     */
    public function usageCategory(string $key): array
    {
        $scope = 'ai.model_resolver.usage_category';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'usage_category_key' => $key,
        ]);

        $category = $this->usageCategories()[$key] ?? null;

        $step = $this->workflowStep($scope, $step, 'Usage category lookup completed', [
            'usage_category_key' => $key,
            'category_found' => is_array($category),
        ]);

        if (! is_array($category)) {
            $this->workflowStep($scope, $step, 'Unknown usage category key', [
                'usage_category_key' => $key,
            ]);

            throw new InvalidArgumentException(__('ai_models.errors.unknown_usage_category'));
        }

        $this->workflowStep($scope, $step, 'Usage category resolved', [
            'usage_category_key' => $key,
            'config_keys' => array_keys($category),
        ]);

        return $category;
    }

    /**
     * Check whether a model key exists.
     */
    public function hasModel(string $key): bool
    {
        $exists = array_key_exists($key, $this->models());

        DebugStepLogger::workflowStep('ai.model_resolver.has_model', 1, 'Model existence checked', [
            'model_key' => $key,
            'exists' => $exists,
        ]);

        return $exists;
    }

    /**
     * Check whether a usage category key exists.
     */
    public function hasUsageCategory(string $key): bool
    {
        $exists = array_key_exists($key, $this->usageCategories());

        DebugStepLogger::workflowStep('ai.model_resolver.has_usage_category', 1, 'Usage category existence checked', [
            'usage_category_key' => $key,
            'exists' => $exists,
        ]);

        return $exists;
    }

    /**
     * Check whether a model supports a usage category.
     */
    public function modelSupportsUsageCategory(string $modelKey, string $usageCategoryKey): bool
    {
        $scope = 'ai.model_resolver.model_supports_usage_category';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'model_key' => $modelKey,
            'usage_category_key' => $usageCategoryKey,
        ]);

        $supported = Arr::wrap($this->model($modelKey)['supported_usage_categories'] ?? []);
        $supports = in_array($usageCategoryKey, $supported, true);

        $this->workflowStep($scope, $step, 'Support check completed', [
            'model_key' => $modelKey,
            'usage_category_key' => $usageCategoryKey,
            'supported_count' => count($supported),
            'supports' => $supports,
        ]);

        return $supports;
    }

    /**
     * Resolve the provider implementation for an AI model access.
     */
    public function providerFor(AiModelAccess $access): AiModelProviderInterface
    {
        $scope = 'ai.model_resolver.provider_for';
        $step = 0;

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'usage_category_key' => $access->usage_category_key,
        ]);

        $model = $this->model($access->model_key);
        $driver = $model['driver'] ?? null;

        $step = $this->workflowStep($scope, $step, 'Provider driver resolved from config', [
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'driver' => is_string($driver) ? $driver : null,
            'driver_is_string' => is_string($driver),
            'driver_exists' => is_string($driver) && class_exists($driver),
        ]);

        if (! is_string($driver) || ! class_exists($driver)) {
            $this->workflowStep($scope, $step, 'Provider driver unavailable', [
                'ai_model_access_id' => $access->id,
                'model_key' => $access->model_key,
                'driver_is_string' => is_string($driver),
            ]);

            throw new InvalidArgumentException(__('ai_models.errors.provider_unavailable'));
        }

        $provider = app($driver);

        $step = $this->workflowStep($scope, $step, 'Provider instance created', [
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'provider_class' => $provider::class,
            'implements_contract' => $provider instanceof AiModelProviderInterface,
        ]);

        if (! $provider instanceof AiModelProviderInterface) {
            $this->workflowStep($scope, $step, 'Provider does not implement required contract', [
                'ai_model_access_id' => $access->id,
                'model_key' => $access->model_key,
                'provider_class' => $provider::class,
            ]);

            throw new InvalidArgumentException(__('ai_models.errors.provider_unavailable'));
        }

        $this->workflowStep($scope, $step, 'Provider resolved', [
            'ai_model_access_id' => $access->id,
            'model_key' => $access->model_key,
            'provider_class' => $provider::class,
        ]);

        return $provider;
    }

    /**
     * Return a display label for a model.
     */
    public function modelLabel(string $key): string
    {
        $model = $this->models()[$key] ?? [];
        $labelKey = $model['display_name_key'] ?? null;
        $label = is_string($labelKey) ? __($labelKey) : $key;

        DebugStepLogger::workflowStep('ai.model_resolver.model_label', 1, 'Model label resolved', [
            'model_key' => $key,
            'has_label_key' => is_string($labelKey),
        ]);

        return $label;
    }

    /**
     * Return a display label for a usage category.
     */
    public function usageCategoryLabel(string $key): string
    {
        $category = $this->usageCategories()[$key] ?? [];
        $labelKey = $category['label_key'] ?? null;
        $label = is_string($labelKey) ? __($labelKey) : $key;

        DebugStepLogger::workflowStep('ai.model_resolver.usage_category_label', 1, 'Usage category label resolved', [
            'usage_category_key' => $key,
            'has_label_key' => is_string($labelKey),
        ]);

        return $label;
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
    private function workflowStep(string $scope, int $step, string $message, array $context = []): int
    {
        $step++;

        DebugStepLogger::workflowStep($scope, $step, $message, $context);

        return $step;
    }
}