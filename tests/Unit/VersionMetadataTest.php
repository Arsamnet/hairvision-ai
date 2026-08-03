<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Verifies the canonical HairVision release metadata.
 *
 * @since 2.0.0
 * @since 2.1.0 Coverage includes JavaScript package metadata and source markers.
 * @since 2.1.1 Verifies the 2.1.1 runtime, docs, changelog, and source markers.
 */
class VersionMetadataTest extends TestCase
{
    /**
     * Ensure runtime defaults and environment documentation identify version 2.1.1.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     * @since 2.1.0 Verifies package and lockfile release metadata.
     * @since 2.1.1 Updates expected release metadata.
     */
    public function test_project_version_defaults_to_2_1_1(): void
    {
        $this->assertSame('2.1.1', config('hairvision.version'));
        $this->assertStringContainsString(
            'APP_VERSION=2.1.1',
            (string) file_get_contents(base_path('.env.example'))
        );
        $this->assertStringContainsString(
            'APP_VERSION=2.1.1',
            (string) file_get_contents(base_path('.env.cpanel.example'))
        );
        $this->assertStringContainsString(
            '<env name="APP_VERSION" value="2.1.1"/>',
            (string) file_get_contents(base_path('phpunit.xml'))
        );

        $package = json_decode((string) file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
        $packageLock = json_decode((string) file_get_contents(base_path('package-lock.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('2.1.1', $package['version']);
        $this->assertSame('2.1.1', $packageLock['version']);
        $this->assertSame('2.1.1', $packageLock['packages']['']['version']);
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
        $this->assertStringContainsString('## 2.1.0 - 2026-08-03', $releaseNotes);
        $this->assertStringContainsString('## 2.1.1 - 2026-08-03', $releaseNotes);
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
                '2.1.1',
                $contents,
                $documentation
            );
        }
    }

    /**
     * Ensure the closest-owner release-marker contract is documented and represented.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     * @since 2.1.1 Checks the closest owners changed by the 2.1.1 release.
     */
    public function test_release_scoped_sources_carry_version_markers(): void
    {
        $agentGuide = (string) file_get_contents(base_path('AGENTS.md'));

        $this->assertStringContainsString('closest', $agentGuide);
        $this->assertStringContainsString('stable owner', $agentGuide);

        foreach ([
            'app/Actions/Ai/RunAiModelAction.php',
            'app/Services/Analytics/ClinicPerformanceReportService.php',
            'app/Services/Admin/LeadDashboardService.php',
            'database/migrations/2026_08_03_000002_backfill_completed_ai_model_usage_credits.php',
            'lang/fa/reports.php',
            'resources/views/admin/leads/index.blade.php',
            'resources/views/admin/reports.blade.php',
        ] as $source) {
            $this->assertStringContainsString(
                '2.1.1',
                (string) file_get_contents(base_path($source)),
                $source
            );
        }
    }
}
