<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NumericInputContractTest extends TestCase
{
    public function test_admin_numeric_inputs_accept_supported_digits_and_use_shared_formatting(): void
    {
        foreach (File::allFiles(resource_path('views/admin')) as $view) {
            $contents = $view->getContents();

            $this->assertDoesNotMatchRegularExpression(
                '/type\s*=\s*["\']number["\']/i',
                $contents,
                $view->getRelativePathname().' must not use native number inputs because they reject Persian and Arabic digits.'
            );

            preg_match_all('/<input\b[^>]*data-number-input[^>]*>/is', $contents, $numericInputs);

            foreach ($numericInputs[0] as $input) {
                $this->assertMatchesRegularExpression('/inputmode\s*=\s*["\']numeric["\']/i', $input);
            }
        }

        $adminEntry = File::get(resource_path('js/admin/index.js'));
        $numericInputs = File::get(resource_path('js/admin/numeric-inputs.js'));

        $this->assertStringContainsString("from './numeric-inputs.js'", $adminEntry);
        $this->assertStringContainsString('data-number-input', $numericInputs);
        $this->assertStringContainsString('data-digit-input', $numericInputs);
    }
}
