<?php

namespace App\Services\Billing;

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
        return config('hairvision.ai_services', []);
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->all())
            ->mapWithKeys(fn (array $service, string $key): array => [$key => __($service['label_key'] ?? 'services.'.$key)])
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

        return is_string($labelKey) ? __($labelKey) : $serviceKey;
    }

    public function defaultServiceKey(): string
    {
        return (string) config('hairvision.billing.default_service_key', 'hair_transplant');
    }
}
