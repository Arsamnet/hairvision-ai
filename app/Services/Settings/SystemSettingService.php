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
    public function all()
    {
        return Cache::remember('hairvision:system-settings', 300, function () {
            return SystemSetting::query()
                ->get()
                ->mapWithKeys(fn (SystemSetting $setting): array => [
                    $setting->key => $setting->is_encrypted
                        ? $setting->encrypted_value
                        : ($setting->value_json['value'] ?? null),
                ]);
        });
    }

    /**
     * Store a value and clear the settings cache.
     */
    public function set(string $key, mixed $value): SystemSetting
    {
        $setting = SystemSetting::query()->where('key', $key)->firstOrFail();

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
        Cache::forget('hairvision:system-settings');
    }
}
