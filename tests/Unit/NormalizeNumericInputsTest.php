<?php

namespace Tests\Unit;

use App\Http\Middleware\NormalizeNumericInputs;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class NormalizeNumericInputsTest extends TestCase
{
    public function test_it_normalizes_nested_numeric_inputs_before_the_request_is_handled(): void
    {
        $request = Request::create('/admin/plans?per_page=۱۰۰', 'POST', [
            'price' => '۳,۵۰۰,۰۰۰,۰۰۰',
            'phone' => '٠٩١٢٣٤٥٦٧٨٩',
            'settings' => [
                'timeout_seconds' => '١٬٨٠٠',
            ],
            'name' => 'پلن ۱۲۳',
            'credentials' => [
                'api_key' => '۱۲۳۴۵۶',
            ],
        ]);

        (new NormalizeNumericInputs)->handle($request, function (Request $handled): Response {
            $this->assertSame('100', $handled->query('per_page'));
            $this->assertSame('3500000000', $handled->input('price'));
            $this->assertSame('09123456789', $handled->input('phone'));
            $this->assertSame('1800', $handled->input('settings.timeout_seconds'));
            $this->assertSame('پلن ۱۲۳', $handled->input('name'));
            $this->assertSame('۱۲۳۴۵۶', $handled->input('credentials.api_key'));

            return new Response;
        });
    }
}
