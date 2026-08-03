<?php

namespace Tests\Unit;

use App\Support\QueryAwareVite;
use Tests\TestCase;

/**
 * Verifies that release query strings preserve Vite asset element types.
 *
 * @since 2.1.0
 * @since 2.1.1 Updates the release query-string expectation.
 */
class QueryAwareViteTest extends TestCase
{
    public function test_dotted_version_queries_preserve_asset_types_for_relative_and_absolute_urls(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('build/manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $cssFiles = [
            $manifest['resources/css/app.css']['file'],
            ...$manifest['resources/js/app.js']['css'],
        ];
        $javascriptFile = $manifest['resources/js/app.js']['file'];

        foreach (['', 'https://tenant.example.test'] as $origin) {
            $vite = (new QueryAwareVite)->createAssetPathsUsing(
                static fn (string $path): string => $origin.'/'.$path.'?v=2.1.1',
            );
            $html = (string) $vite(['resources/css/app.css', 'resources/js/app.js']);

            foreach ($cssFiles as $cssFile) {
                $versionedUrl = $origin.'/build/'.$cssFile.'?v=2.1.1';
                $quotedUrl = preg_quote($versionedUrl, '/');

                $this->assertSame(
                    1,
                    preg_match_all('/<link rel="stylesheet" href="'.$quotedUrl.'"/', $html),
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/<script[^>]+src="'.$quotedUrl.'"/',
                    $html,
                );
                $this->assertDoesNotMatchRegularExpression(
                    '/<link[^>]+rel="modulepreload"[^>]+href="'.$quotedUrl.'"/',
                    $html,
                );
            }

            $this->assertSame(
                1,
                preg_match_all(
                    '/<script type="module" src="'.preg_quote($origin.'/build/'.$javascriptFile.'?v=2.1.1', '/').'">/',
                    $html,
                ),
            );

            preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $html, $stylesheetMatches);
            preg_match_all('/<script type="module" src="([^"]+)">/', $html, $scriptMatches);

            $this->assertSame($stylesheetMatches[1], array_values(array_unique($stylesheetMatches[1])));
            $this->assertSame($scriptMatches[1], array_values(array_unique($scriptMatches[1])));
        }
    }
}
