<?php

namespace App\Rules;

use App\Support\LandingTemplateVariables;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Allows trusted inline landing markup while rejecting external assets and full documents.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
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

        $variableError = $this->variableValidationError($value);

        if ($variableError !== null) {
            $fail(__($variableError));

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
     * Validate allowlisted template variables and their safe HTML placement.
     *
     * Back-link variables must appear together inside one anchor, with URL and
     * target in quoted attributes and a defensive rel attribute.
     *
     * @param  string  $html  Landing HTML fragment.
     * @return string|null Translation key for the first variable error.
     *
     * @since 2.1.0
     */
    private function variableValidationError(string $html): ?string
    {
        preg_match_all('/\{\{\s*clinic\.[^{}]+\}\}/i', $html, $variableMatches);

        foreach ($variableMatches[0] ?? [] as $candidate) {
            if (! in_array($candidate, LandingTemplateVariables::tokens(), true)) {
                return 'landing_templates.validation.variable_unknown';
            }
        }

        if (preg_match('/<(?:script|style)\b[^>]*>.*?\{\{\s*clinic\./is', $html)) {
            return 'landing_templates.validation.variable_context';
        }

        $remaining = preg_replace_callback(
            '/<a\b[^>]*>.*?<\/a\s*>/is',
            function (array $match): string {
                $anchor = (string) $match[0];

                if (LandingTemplateVariables::usedTokens([$anchor]) === []) {
                    return $anchor;
                }

                return $this->isValidVariableAnchor($anchor) ? '' : $anchor;
            },
            $html
        );

        foreach (LandingTemplateVariables::tokens() as $token) {
            if (str_contains((string) $remaining, $token)) {
                return 'landing_templates.validation.variable_context';
            }
        }

        return null;
    }

    /**
     * Determine whether an anchor follows the complete back-link token pattern.
     *
     * @param  string  $anchor  Complete anchor element markup.
     * @return bool Whether the anchor is safe for runtime variable injection.
     *
     * @since 2.1.0
     */
    private function isValidVariableAnchor(string $anchor): bool
    {
        if (
            substr_count($anchor, LandingTemplateVariables::BACK_LINK_TITLE) !== 1
            || substr_count($anchor, LandingTemplateVariables::BACK_LINK_URL) !== 1
            || substr_count($anchor, LandingTemplateVariables::BACK_LINK_TARGET) !== 1
        ) {
            return false;
        }

        $tagEnd = strpos($anchor, '>');

        if ($tagEnd === false) {
            return false;
        }

        $openingTag = substr($anchor, 0, $tagEnd + 1);
        $body = substr($anchor, $tagEnd + 1);
        $url = preg_quote(LandingTemplateVariables::BACK_LINK_URL, '/');
        $target = preg_quote(LandingTemplateVariables::BACK_LINK_TARGET, '/');

        if (
            preg_match('/\bhref\s*=\s*(["\'])'.$url.'\1/i', $openingTag) !== 1
            || preg_match('/\btarget\s*=\s*(["\'])'.$target.'\1/i', $openingTag) !== 1
            || str_contains($openingTag, LandingTemplateVariables::BACK_LINK_TITLE)
            || ! str_contains($body, LandingTemplateVariables::BACK_LINK_TITLE)
        ) {
            return false;
        }

        if (preg_match('/\brel\s*=\s*(["\'])(.*?)\1/is', $openingTag, $relMatch) !== 1) {
            return false;
        }

        $relValues = preg_split('/\s+/', strtolower(trim((string) ($relMatch[2] ?? '')))) ?: [];

        return in_array('noopener', $relValues, true) && in_array('noreferrer', $relValues, true);
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
