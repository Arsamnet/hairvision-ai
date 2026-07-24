<?php

namespace App\Support;

/**
 * Resolves tenant landing assets without permitting arbitrary external resources.
 *
 * @since 2.0.0
 */
final class LandingAssetUrl
{
    /**
     * Resolve a configured asset URL or return the trusted application fallback.
     *
     * @param  string|null  $configuredPath  Tenant or template asset path.
     * @param  string  $fallback  Trusted application asset URL.
     * @return string Safe asset URL.
     *
     * @since 2.0.0
     */
    public static function resolve(?string $configuredPath, string $fallback): string
    {
        $path = trim((string) $configuredPath);

        if ($path === '' || str_starts_with($path, '//')) {
            return $fallback;
        }

        if (str_starts_with($path, '/')) {
            $localPath = parse_url($path, PHP_URL_PATH);

            if (! is_string($localPath) || ! is_file(public_path(ltrim($localPath, '/')))) {
                return $fallback;
            }

            return $path;
        }

        $primaryHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $assetHost = parse_url($path, PHP_URL_HOST);
        $assetScheme = parse_url($path, PHP_URL_SCHEME);

        return $assetScheme === 'https'
            && is_string($primaryHost)
            && $primaryHost !== ''
            && is_string($assetHost)
            && hash_equals(strtolower($primaryHost), strtolower($assetHost))
                ? $path
                : $fallback;
    }
}
