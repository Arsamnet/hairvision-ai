<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Restricts template asset paths to local files or the primary HairVision host.
 *
 * @since 2.0.0
 */
class PrimaryLandingAssetPath implements ValidationRule
{
    /**
     * Validate a landing template asset path.
     *
     * @param  string  $attribute  Input attribute name.
     * @param  mixed  $value  Submitted input value.
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  Validation failure callback.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value)) {
            $fail(__('landing_templates.validation.local_assets_only'));

            return;
        }

        $path = trim($value);

        if (str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
            return;
        }

        $primaryHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $assetHost = parse_url($path, PHP_URL_HOST);
        $scheme = parse_url($path, PHP_URL_SCHEME);

        if (
            $scheme !== 'https'
            || ! is_string($primaryHost)
            || $primaryHost === ''
            || ! is_string($assetHost)
            || ! hash_equals(strtolower($primaryHost), strtolower($assetHost))
        ) {
            $fail(__('landing_templates.validation.local_assets_only'));
        }
    }
}
