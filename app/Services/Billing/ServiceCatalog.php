<?php

namespace App\Services\Billing;

use App\Models\AiModelCategory;
use App\Support\AdminIcon;
use Illuminate\Support\Facades\Schema;

/**
 * Reads stable AI service keys and Persian label metadata from configuration.
 *
 * @since 1.0.0
 */
class ServiceCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $configured = config('hairvision.ai_services', []);

        if (! Schema::hasTable('ai_model_categories')) {
            return $configured;
        }

        $categories = AiModelCategory::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (AiModelCategory $category): array => [
                $category->code => [
                    'label' => $category->name,
                    'description' => $category->description,
                    'icon' => $category->resolvedIcon(),
                ],
            ])
            ->all();

        return array_replace($configured, $categories);
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $service, string $key): array => [$key => $service['label'] ?? __($service['label_key'] ?? 'services.'.$key)])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function icons(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $service, string $key): array => [
                $key => AdminIcon::normalize($service['icon'] ?? null, AdminIcon::DEFAULT_CATEGORY_ICON),
            ])
            ->all();
    }

    public function has(string $serviceKey): bool
    {
        return array_key_exists($serviceKey, $this->all());
    }

    public function label(string $serviceKey): string
    {
        $service = $this->all()[$serviceKey] ?? [];
        $labelKey = $service['label_key'] ?? null;

        return $service['label'] ?? (is_string($labelKey) ? __($labelKey) : $serviceKey);
    }

    public function defaultServiceKey(): string
    {
        return (string) config('hairvision.billing.default_service_key', 'hair_transplant');
    }
}
