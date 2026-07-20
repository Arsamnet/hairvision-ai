<?php

namespace Tests\Feature;

use App\Support\AdminIcon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use SplFileInfo;
use Tests\TestCase;

class IconLibraryContractTest extends TestCase
{
    public function test_all_ui_icons_use_the_local_uicons_library(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertFileExists(resource_path('fonts/uicons/uicons-regular-rounded.woff2'));
        $this->assertStringContainsString("font-family: 'uicons-regular-rounded';", $css);
        $this->assertStringContainsString("url('../fonts/uicons/uicons-regular-rounded.woff2')", $css);

        preg_match_all('/\.fi-rr-([a-z0-9-]+)::before\s*\{/', $css, $definedMatches);
        $definedCodes = array_values(array_unique($definedMatches[1]));

        $this->assertEqualsCanonicalizing(AdminIcon::availableCodes(), $definedCodes);

        foreach (AdminIcon::aliases() as $name => $code) {
            $this->assertContains($code, $definedCodes, "The {$name} icon must resolve to a registered local UIcons glyph.");
            $this->assertSame($code, AdminIcon::resolve($name));
        }

        $renderedIcon = Blade::render('<x-admin.icon name="link" class="h-4 w-4" />');

        $this->assertStringContainsString('class="fi fi-rr-link', $renderedIcon);
        $this->assertStringContainsString('h-4 w-4', $renderedIcon);
        $this->assertStringNotContainsString('<svg', $renderedIcon);

        $this->assertStringContainsString('.fi-rr-filter::before { content: "\\f67e"; }', $css);
        $this->assertStringContainsString('.fi-rr-zoom-in::before { content: "\\ff2d"; }', $css);

        $uiSources = array_merge(
            File::allFiles(resource_path('views')),
            File::allFiles(resource_path('js')),
        );

        foreach ($uiSources as $file) {
            if (! $this->isUiSource($file)) {
                continue;
            }

            $contents = $file->getContents();
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());

            $this->assertDoesNotMatchRegularExpression(
                '/<svg\b/i',
                $contents,
                $relativePath.' must use the local icon library instead of inline SVG icons.'
            );

            preg_match_all('/\bfi-rr-([a-z0-9-]+)/', $contents, $usedMatches);

            foreach (array_unique($usedMatches[1]) as $usedCode) {
                $this->assertContains(
                    $usedCode,
                    $definedCodes,
                    $relativePath.' uses an unregistered UIcons glyph: '.$usedCode
                );
            }

            preg_match_all('/<i\b(?:(?!<\/i>).)*<\/i>/is', $contents, $iconElements);

            foreach ($iconElements[0] as $iconElement) {
                $this->assertMatchesRegularExpression(
                    '/\bfi\b.*\bfi-rr-/s',
                    $iconElement,
                    $relativePath.' contains an <i> icon outside the local UIcons library.'
                );
            }
        }

        foreach (File::allFiles(resource_path('views/admin')) as $view) {
            $this->assertDoesNotMatchRegularExpression(
                '/<(?:i|span)\b[^>]*class\s*=\s*["\'][^"\']*\bfi\b/is',
                $view->getContents(),
                $view->getRelativePathname().' must render dashboard icons through x-admin.icon.'
            );
        }
    }

    private function isUiSource(SplFileInfo $file): bool
    {
        $path = $file->getPathname();

        return str_ends_with($path, '.blade.php') || str_ends_with($path, '.js');
    }
}
