<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Restricts trusted tracker snippets to analytics-oriented HTML fragments.
 *
 * @since 2.1.0
 */
class TrackingCodeFragment implements ValidationRule
{
    /**
     * Validate a head or body tracker fragment.
     *
     * @param  string  $attribute  Input attribute name.
     * @param  mixed  $value  Submitted tracker fragment.
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail  Validation failure callback.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        if (! is_string($value)) {
            $fail(__('landing_templates.validation.tracking_fragment'));

            return;
        }

        if (preg_match('/<\s*\/?\s*(?:html|head|body|base|form|input|button|object|embed|link|meta|style)\b/i', $value)) {
            $fail(__('landing_templates.validation.tracking_fragment'));

            return;
        }

        preg_match_all('/<\s*\/?\s*([a-z0-9:-]+)\b/i', $value, $tags);
        $unsupported = array_diff(
            array_map('strtolower', $tags[1] ?? []),
            ['script', 'noscript', 'iframe', 'img']
        );

        if ($unsupported !== []) {
            $fail(__('landing_templates.validation.tracking_tags'));

            return;
        }

        if (
            preg_match('/\b(?:src|href)\s*=\s*(?:["\'])?\s*(?:\/\/|http:\/\/|javascript:|vbscript:|file:|ftp:|blob:|data:)/i', $value)
            || preg_match('/<\s*iframe\b[^>]*\bsrcdoc\s*=/i', $value)
        ) {
            $fail(__('landing_templates.validation.tracking_secure_urls'));
        }
    }
}
