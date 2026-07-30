<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Verifies the canonical HairVision release metadata.
 *
 * @since 2.0.0
 */
class VersionMetadataTest extends TestCase
{
    /**
     * Ensure runtime defaults and environment documentation identify version 2.1.0.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_project_version_defaults_to_2_1_0(): void
    {
        $this->assertSame('2.1.0', config('hairvision.version'));
        $this->assertStringContainsString(
            'APP_VERSION=2.1.0',
            (string) file_get_contents(base_path('.env.example'))
        );
        $this->assertStringContainsString(
            'APP_VERSION=2.1.0',
            (string) file_get_contents(base_path('.env.cpanel.example'))
        );
        $this->assertStringContainsString(
            '<env name="APP_VERSION" value="2.1.0"/>',
            (string) file_get_contents(base_path('phpunit.xml'))
        );
    }

    /**
     * Ensure release history exists only in the canonical root changelog.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_release_notes_have_one_canonical_root_file(): void
    {
        $releaseNotes = (string) file_get_contents(base_path('CHANGELOG.md'));

        $this->assertFileExists(base_path('CHANGELOG.md'));
        $this->assertFileDoesNotExist(base_path('docs/release-notes.md'));
        $this->assertStringContainsString('## 2.0.0', $releaseNotes);
        $this->assertStringContainsString('## 2.0.1', $releaseNotes);
        $this->assertStringContainsString('## 2.0.2', $releaseNotes);
        $this->assertStringContainsString('## 2.0.3', $releaseNotes);
        $this->assertStringContainsString('## 2.1.0', $releaseNotes);
        $this->assertStringContainsString('## Unreleased', $releaseNotes);

        foreach ([
            'README.md',
            'AGENTS.md',
            'docs/agent-instructions.md',
            'docs/business-rules-fa.md',
            'docs/architecture.md',
            'docs/api.md',
        ] as $documentation) {
            $contents = (string) file_get_contents(base_path($documentation));

            $this->assertStringContainsString('CHANGELOG.md', $contents, $documentation);
            $this->assertStringContainsString(
                '2.1.0',
                $contents,
                $documentation
            );
        }
    }
}
