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
     * Ensure runtime defaults and environment documentation identify version 2.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_project_version_defaults_to_2_0_0(): void
    {
        $this->assertSame('2.0.0', config('hairvision.version'));
        $this->assertStringContainsString(
            'APP_VERSION=2.0.0',
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
        $this->assertFileExists(base_path('CHANGELOG.md'));
        $this->assertFileDoesNotExist(base_path('docs/release-notes.md'));
        $this->assertStringContainsString(
            '## 2.0.0',
            (string) file_get_contents(base_path('CHANGELOG.md'))
        );

        foreach (['README.md', 'AGENTS.md', 'docs/agent-instructions.md'] as $documentation) {
            $this->assertStringContainsString(
                'CHANGELOG.md',
                (string) file_get_contents(base_path($documentation)),
                $documentation
            );
        }
    }
}
