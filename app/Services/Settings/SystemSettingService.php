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
     *
     * @param  string  $key  The key value.
     * @param  mixed  $default  The default value.
     * @return mixed Resolved operation result.
     *
     * @since 1.0.0
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()->get($key, $default);
    }

    /**
     * Return all settings as key/value pairs.
     *
     * @return Collection<string, mixed>
     *
     * @since 1.0.0
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
     *
     * @param  string  $key  The key value.
     * @param  mixed  $value  Input value supplied to the operation.
     * @return SystemSetting Resolved operation result.
     *
     * @since 1.0.0
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
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Load settings as cache-safe scalar and array values.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
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
