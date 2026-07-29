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
     * Ensure runtime defaults and environment documentation identify version 2.0.2.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_project_version_defaults_to_2_0_2(): void
    {
        $this->assertSame('2.0.2', config('hairvision.version'));
        $this->assertStringContainsString(
            'APP_VERSION=2.0.2',
            (string) file_get_contents(base_path('.env.example'))
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
                '2.0.2',
                $contents,
                $documentation
            );
        }
    }
}
