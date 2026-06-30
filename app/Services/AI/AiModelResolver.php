<?php

namespace App\Services\AI;

use App\Contracts\Ai\AiModelProviderInterface;
use App\Models\AiModelAccess;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Resolves configured AI model definitions, usage categories, and providers.
 *
 * @since 1.0.0
 */
class AiModelResolver
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function models(): array
    {
        return config('ai-models.models', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function usageCategories(): array
    {
        return config('ai-models.usage_categories', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function model(string $key): array
    {
        $model = $this->models()[$key] ?? null;

        if (! is_array($model)) {
            throw new InvalidArgumentException(__('ai_models.errors.unknown_model'));
        }

        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function usageCategory(string $key): array
    {
        $category = $this->usageCategories()[$key] ?? null;

        if (! is_array($category)) {
            throw new InvalidArgumentException(__('ai_models.errors.unknown_usage_category'));
        }

        return $category;
    }

    public function hasModel(string $key): bool
    {
        return array_key_exists($key, $this->models());
    }

    public function hasUsageCategory(string $key): bool
    {
        return array_key_exists($key, $this->usageCategories());
    }

    public function modelSupportsUsageCategory(string $modelKey, string $usageCategoryKey): bool
    {
        $supported = Arr::wrap($this->model($modelKey)['supported_usage_categories'] ?? []);

        return in_array($usageCategoryKey, $supported, true);
    }

    public function providerFor(AiModelAccess $access): AiModelProviderInterface
    {
        $driver = $this->model($access->model_key)['driver'] ?? null;

        if (! is_string($driver) || ! class_exists($driver)) {
            throw new InvalidArgumentException(__('ai_models.errors.provider_unavailable'));
        }

        $provider = app($driver);

        if (! $provider instanceof AiModelProviderInterface) {
            throw new InvalidArgumentException(__('ai_models.errors.provider_unavailable'));
        }

        return $provider;
    }

    public function modelLabel(string $key): string
    {
        $model = $this->models()[$key] ?? [];
        $labelKey = $model['display_name_key'] ?? null;

        return is_string($labelKey) ? __($labelKey) : $key;
    }

    public function usageCategoryLabel(string $key): string
    {
        $category = $this->usageCategories()[$key] ?? [];
        $labelKey = $category['label_key'] ?? null;

        return is_string($labelKey) ? __($labelKey) : $key;
    }
}
