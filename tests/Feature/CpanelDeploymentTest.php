<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verifies the checked-in cPanel deployment assets and environment baseline.
 *
 * @since 2.0.0
 */
class CpanelDeploymentTest extends TestCase
{
    /**
     * Ensure public assets are copied into the configured cPanel webroot.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_public_directory_is_deployed_to_the_cpanel_webroot(): void
    {
        $configuration = file_get_contents(base_path('.cpanel.yml'));

        $this->assertIsString($configuration);
        $this->assertStringContainsString(
            'export DEPLOYPATH=/home/hairvision/public_html/',
            $configuration,
        );
        $this->assertStringContainsString(
            '/bin/cp -Rf public/. "$DEPLOYPATH"',
            $configuration,
        );
    }

    /**
     * Ensure the copied front controller resolves the private repository files.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_deployed_front_controller_uses_the_cpanel_repository_path(): void
    {
        $configuration = file_get_contents(base_path('.cpanel.yml'));

        $this->assertIsString($configuration);
        $this->assertStringContainsString(
            "/usr/bin/sed -i \"s#__DIR__.'/../#__DIR__.'/../repositories/hairvision-ai/#g\" \"\${DEPLOYPATH}index.php\"",
            $configuration,
        );

        $frontController = file_get_contents(public_path('index.php'));

        $this->assertIsString($frontController);

        $deployedFrontController = str_replace(
            "__DIR__.'/../",
            "__DIR__.'/../repositories/hairvision-ai/",
            $frontController,
        );

        $this->assertSame(3, substr_count($deployedFrontController, '../repositories/hairvision-ai/'));
        $this->assertStringContainsString(
            "__DIR__.'/../repositories/hairvision-ai/storage/framework/maintenance.php'",
            $deployedFrontController,
        );
        $this->assertStringContainsString(
            "__DIR__.'/../repositories/hairvision-ai/vendor/autoload.php'",
            $deployedFrontController,
        );
        $this->assertStringContainsString(
            "__DIR__.'/../repositories/hairvision-ai/bootstrap/app.php'",
            $deployedFrontController,
        );
    }

    /**
     * Ensure the cPanel template is complete, production-safe, and secret-free.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_cpanel_environment_template_matches_the_complete_environment_contract(): void
    {
        $localTemplate = (string) file_get_contents(base_path('.env.example'));
        $cpanelTemplate = (string) file_get_contents(base_path('.env.cpanel.example'));

        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $localTemplate, $localMatches);
        preg_match_all('/^([A-Z][A-Z0-9_]*)=/m', $cpanelTemplate, $cpanelMatches);

        $localKeys = $localMatches[1];
        $cpanelKeys = $cpanelMatches[1];

        $this->assertSame($localKeys, array_values(array_unique($localKeys)));
        $this->assertSame($cpanelKeys, array_values(array_unique($cpanelKeys)));

        sort($localKeys);
        sort($cpanelKeys);

        $this->assertSame($localKeys, $cpanelKeys);
        $this->assertStringContainsString('APP_ENV=production', $cpanelTemplate);
        $this->assertStringContainsString('APP_DEBUG=false', $cpanelTemplate);
        $this->assertStringContainsString('SESSION_SECURE_COOKIE=true', $cpanelTemplate);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $cpanelTemplate);
        $this->assertStringContainsString('HAIRVISION_CPANEL_QUEUE_CRON_ENABLED=true', $cpanelTemplate);
        $this->assertStringContainsString('HAIRVISION_DEBUG_FAST_SAMPLES=false', $cpanelTemplate);
        $this->assertStringContainsString("APP_KEY=\n", $cpanelTemplate);
        $this->assertStringContainsString("DB_PASSWORD=\n", $cpanelTemplate);
        $this->assertStringContainsString("HAIRVISION_IPPANEL_AUTHORIZATION_TOKEN=\n", $cpanelTemplate);
    }
}
