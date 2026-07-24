<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Allows trusted inline landing markup while rejecting external assets and full documents.
 *
 * @since 2.0.0
 */
class InlineLandingTemplateHtml implements ValidationRule
{
    /**
     * Validate an editable landing template HTML fragment.
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
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (preg_match('/<\s*\/?\s*(?:html|head|body|base|iframe|object|embed)\b/i', $value)) {
            $fail(__('landing_templates.validation.fragment_only'));

            return;
        }

        if (
            preg_match('/<\s*script\b[^>]*\bsrc\s*=/i', $value)
            || preg_match('/<\s*link\b/i', $value)
            || preg_match('/@import\b/i', $value)
        ) {
            $fail(__('landing_templates.validation.inline_only'));

            return;
        }

        if (preg_match('/\b(?:href|src|poster|action|formaction)\s*=\s*(["\'])\s*javascript:/i', $value)) {
            $fail(__('landing_templates.validation.unsafe_scheme'));

            return;
        }

        foreach ($this->assetUrls($value) as $url) {
            if (! $this->isAllowedAssetUrl($url)) {
                $fail(__('landing_templates.validation.local_assets_only'));

                return;
            }
        }
    }

    /**
     * Extract asset URLs from HTML attributes and inline CSS.
     *
     * @param  string  $html  Landing HTML fragment.
     * @return array<int, string> Extracted asset URLs.
     *
     * @since 2.0.0
     */
    private function assetUrls(string $html): array
    {
        $urls = [];

        preg_match_all('/\b(?:src|poster)\s*=\s*(["\'])(.*?)\1/is', $html, $attributeMatches);
        foreach ($attributeMatches[2] ?? [] as $url) {
            $urls[] = trim((string) $url);
        }

        preg_match_all('/\bsrcset\s*=\s*(["\'])(.*?)\1/is', $html, $srcsetMatches);
        foreach ($srcsetMatches[2] ?? [] as $srcset) {
            foreach (explode(',', (string) $srcset) as $candidate) {
                $parts = preg_split('/\s+/', trim($candidate)) ?: [];
                $urls[] = trim((string) ($parts[0] ?? ''));
            }
        }

        preg_match_all('/url\(\s*(["\']?)(.*?)\1\s*\)/is', $html, $cssMatches);
        foreach ($cssMatches[2] ?? [] as $url) {
            $urls[] = trim((string) $url);
        }

        return array_values(array_filter($urls, static fn (string $url): bool => $url !== ''));
    }

    /**
     * Determine whether an asset URL is inline, local, or hosted on the primary HairVision domain.
     *
     * @param  string  $url  Asset URL extracted from template HTML.
     * @return bool Whether the asset source is allowed.
     *
     * @since 2.0.0
     */
    private function isAllowedAssetUrl(string $url): bool
    {
        if (
            str_starts_with($url, '#')
            || str_starts_with($url, 'data:image/')
            || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))
        ) {
            return true;
        }

        $primaryHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $assetHost = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return $scheme === 'https'
            && is_string($primaryHost)
            && $primaryHost !== ''
            && is_string($assetHost)
            && hash_equals(strtolower($primaryHost), strtolower($assetHost));
    }
}
