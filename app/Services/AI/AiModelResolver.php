<?php

namespace App\Services\AI;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
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
        $configured = config('ai-models.usage_categories', []);

        if (! Schema::hasTable('ai_model_categories')) {
            return $configured;
        }

        $categories = AiModelCategory::query()
            ->whereIn('status', ['active', 'inactive'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (AiModelCategory $category): array => [
                $category->code => [
                    'key' => $category->code,
                    'label' => $category->name,
                    'description' => $category->description,
                    'status' => $category->status,
                ],
            ])
            ->all();

        return array_replace($configured, $categories);
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

        $step++;
        $model = $this->models()[$key] ?? null;

        $step++;
        if (! is_array($model)) {
            throw new InvalidArgumentException(__('ai_models.errors.unknown_model'));
        }

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

        $step++;
        $category = $this->usageCategories()[$key] ?? null;

        $step++;
        if (! is_array($category)) {
            throw new InvalidArgumentException(__('ai_models.errors.unknown_usage_category'));
        }

        return $category;
    }

    /**
     * Check whether a model key exists.
     */
    public function hasModel(string $key): bool
    {
        $exists = array_key_exists($key, $this->models());

        return $exists;
    }

    /**
     * Check whether a usage category key exists.
     */
    public function hasUsageCategory(string $key): bool
    {
        $exists = array_key_exists($key, $this->usageCategories());

        return $exists;
    }

    /**
     * Check whether a model supports a usage category.
     */
    public function modelSupportsUsageCategory(string $modelKey, string $usageCategoryKey): bool
    {
        $scope = 'ai.model_resolver.model_supports_usage_category';
        $step = 0;

        $step++;
        $supported = Arr::wrap($this->model($modelKey)['supported_usage_categories'] ?? []);
        if (in_array('*', $supported, true)) {
            return $this->hasUsageCategory($usageCategoryKey);
        }

        $supports = in_array($usageCategoryKey, $supported, true);

        return $supports;
    }

    /**
     * Resolve the provider implementation for an AI model access.
     */
    public function providerFor(AiModelAccess $access): AiModelProviderInterface
    {
        $scope = 'ai.model_resolver.provider_for';
        $step = 0;

        $step++;
        $model = $this->model($access->model_key);
        $driver = $model['driver'] ?? null;

        $step++;
        if (! is_string($driver) || ! class_exists($driver)) {
            throw new InvalidArgumentException(__('ai_models.errors.provider_unavailable'));
        }

        $provider = app($driver);

        $step++;
        if (! $provider instanceof AiModelProviderInterface) {
            throw new InvalidArgumentException(__('ai_models.errors.provider_unavailable'));
        }

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

        return $label;
    }

    /**
     * Return a display label for a usage category.
     */
    public function usageCategoryLabel(string $key): string
    {
        $category = $this->usageCategories()[$key] ?? [];
        $labelKey = $category['label_key'] ?? null;
        $label = $category['label'] ?? (is_string($labelKey) ? __($labelKey) : $key);

        return $label;
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
