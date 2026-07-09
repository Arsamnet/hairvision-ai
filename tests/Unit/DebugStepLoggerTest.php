<?php

namespace Tests\Unit;

use App\Http\Middleware\LogDebugSteps;
use App\Services\Debug\DebugStepLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class DebugStepLoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        DebugStepLogger::reset();

        parent::tearDown();
    }

    public function test_it_is_silent_when_app_debug_is_disabled(): void
    {
        config(['app.debug' => false]);

        Log::shouldReceive('debug')->never();

        DebugStepLogger::record('test.scope', 'This should not be logged', [
            'tenant_id' => 1,
        ]);
    }

    public function test_it_redacts_sensitive_context_before_logging(): void
    {
        config(['app.debug' => true]);

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $this->assertSame('[test.scope] Step 01: Sensitive step', $message);
                $this->assertSame('[redacted]', $context['password']);
                $this->assertSame('[redacted]', $context['credentials']);
                $this->assertSame('[redacted]', $context['private_path']);
                $this->assertSame('0912***89', $context['phone']);
                $this->assertStringContainsString('[truncated]', $context['long_value']);

                return true;
            });

        DebugStepLogger::record('test.scope', 'Sensitive step', [
            'password' => 'secret',
            'credentials' => ['api_key' => 'real-key'],
            'private_path' => 'tenants/clinic/leads/person/photo.jpg',
            'phone' => '09123456789',
            'long_value' => str_repeat('x', 350),
        ]);
    }

    public function test_request_middleware_logs_safe_lifecycle_steps(): void
    {
        config(['app.debug' => true]);

        $contexts = [];

        Log::shouldReceive('debug')
            ->twice()
            ->withArgs(function (string $message, array $context) use (&$contexts): bool {
                $contexts[] = $context;

                return str_starts_with($message, '[http.request] Step ');
            });

        $request = Request::create('/debug-test?token=secret&phone=09123456789', 'GET', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'DebugStepTest',
        ]);

        $response = (new LogDebugSteps)->handle(
            $request,
            fn (): Response => new Response('', 204),
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('[redacted]', $contexts[0]['query']['token']);
        $this->assertSame('0912***89', $contexts[0]['query']['phone']);
        $this->assertArrayHasKey('ip_hash', $contexts[0]);
        $this->assertSame(204, $contexts[1]['status']);
    }
}
