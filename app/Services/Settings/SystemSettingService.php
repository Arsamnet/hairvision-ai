<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes cached database-backed system settings.
 *
 * @since 1.0.0
 */
class SystemSettingService
{
    private const CACHE_KEY = 'hairvision:system-settings';

    /**
     * Return a setting value or default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()->get($key, $default);
    }

    /**
     * Return all settings as key/value pairs.
     *
     * @return Collection<string, mixed>
     */
    public function all(): Collection
    {
        $values = Cache::get(self::CACHE_KEY);

        if (! is_array($values)) {
            $values = $this->valuesFromDatabase();
            Cache::put(self::CACHE_KEY, $values, 300);
        }

        $values = array_filter(
            $values,
            fn (string $key): bool => ! str_starts_with($key, 'catalog.'),
            ARRAY_FILTER_USE_KEY
        );

        return collect($values);
    }

    /**
     * Store a value and clear the settings cache.
     */
    public function set(string $key, mixed $value): SystemSetting
    {
        $setting = SystemSetting::query()
            ->where('group', '!=', 'catalog')
            ->where('key', $key)
            ->firstOrFail();

        if ($setting->is_encrypted) {
            $setting->encrypted_value = (string) $value;
        } else {
            $setting->value_json = ['value' => $value];
        }

        $setting->save();
        $this->forget();

        return $setting;
    }

    /**
     * Clear cached settings.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Load settings as cache-safe scalar and array values.
     *
     * @return array<string, mixed>
     */
    private function valuesFromDatabase(): array
    {
        return SystemSetting::query()
            ->where('group', '!=', 'catalog')
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [
                $setting->key => $setting->is_encrypted
                    ? $setting->encrypted_value
                    : ($setting->value_json['value'] ?? null),
            ])
            ->all();
    }
}
