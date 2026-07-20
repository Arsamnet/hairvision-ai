<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Resolves code-owned state catalogs and their localized labels.
 *
 * @since 2.0.0
 */
final class StateCatalog
{
    /**
     * Return localized options for a dot-notated catalog path.
     *
     * @return array<string, string>
     */
    public static function options(string $catalog): array
    {
        $definitions = config('state-catalog.'.$catalog, []);

        if (! is_array($definitions)) {
            return [];
        }

        return collect($definitions)
            ->filter(fn (mixed $translationKey, mixed $value): bool => is_string($value) && is_string($translationKey))
            ->mapWithKeys(fn (string $translationKey, string $value): array => [
                $value => Lang::has($translationKey) ? __($translationKey) : $value,
            ])
            ->all();
    }

    /**
     * Return the allowed technical values for a catalog.
     *
     * @return array<int, string>
     */
    public static function values(string $catalog): array
    {
        return array_keys(self::options($catalog));
    }

    /**
     * Return a localized label while safely preserving unknown historical values.
     */
    public static function label(string $catalog, string $value): string
    {
        return self::options($catalog)[$value] ?? $value;
    }
}
