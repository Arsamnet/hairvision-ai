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
     * @param  string  $catalog  The catalog value.
     * @return array<string, string>
     *
     * @since 2.0.0
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
     * @param  string  $catalog  The catalog value.
     * @return array<int, string>
     *
     * @since 2.0.0
     */
    public static function values(string $catalog): array
    {
        return array_keys(self::options($catalog));
    }

    /**
     * Return a localized label while safely preserving unknown historical values.
     *
     * @param  string  $catalog  The catalog value.
     * @param  string  $value  Input value supplied to the operation.
     * @return string Resolved string value.
     *
     * @since 2.0.0
     */
    public static function label(string $catalog, string $value): string
    {
        return self::options($catalog)[$value] ?? $value;
    }
}
