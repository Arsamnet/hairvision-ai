<?php

namespace Tests\Feature;

use Tests\TestCase;

class CpanelDeploymentTest extends TestCase
{
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
}
