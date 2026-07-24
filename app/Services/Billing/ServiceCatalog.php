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
     * Execute the all operation.
     *
     * @return array<string, array<string, mixed>>
     *
     * @since 1.0.0
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
     * Execute the options operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function options(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $service, string $key): array => [$key => $service['label'] ?? __($service['label_key'] ?? 'services.'.$key)])
            ->all();
    }

    /**
     * Execute the icons operation.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function icons(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $service, string $key): array => [
                $key => AdminIcon::normalize($service['icon'] ?? null, AdminIcon::DEFAULT_CATEGORY_ICON),
            ])
            ->all();
    }

    /**
     * Determine whether has.
     *
     * @param  string  $serviceKey  The service key value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function has(string $serviceKey): bool
    {
        return array_key_exists($serviceKey, $this->all());
    }

    /**
     * Execute the label operation.
     *
     * @param  string  $serviceKey  The service key value.
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function label(string $serviceKey): string
    {
        $service = $this->all()[$serviceKey] ?? [];
        $labelKey = $service['label_key'] ?? null;

        return $service['label'] ?? (is_string($labelKey) ? __($labelKey) : $serviceKey);
    }

    /**
     * Execute the default service key operation.
     *
     * @return string Resolved string value.
     *
     * @since 1.0.0
     */
    public function defaultServiceKey(): string
    {
        return (string) config('hairvision.billing.default_service_key', 'hair_transplant');
    }
}
