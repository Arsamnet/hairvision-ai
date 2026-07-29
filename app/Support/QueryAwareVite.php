<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

/**
 * Render Vite assets after classifying their URL path independently of query parameters.
 *
 * @since 2.0.0
 */
class QueryAwareVite extends Vite
{
    /**
     * Determine whether a Vite asset URL points to a stylesheet.
     *
     * @param  string  $path  Absolute or relative Vite asset URL.
     * @return bool Whether the URL path has a supported stylesheet extension.
     *
     * @since 2.0.0
     */
    protected function isCssPath($path)
    {
        $urlPath = parse_url($path, PHP_URL_PATH);

        return is_string($urlPath) && parent::isCssPath($urlPath);
    }
}
