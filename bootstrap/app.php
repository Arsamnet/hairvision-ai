<?php

use App\Http\Middleware\EnsureActivePlanAccess;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\LogDebugSteps;
use App\Http\Middleware\UseScopedSessionCookie;
use App\Services\Debug\DebugStepLogger;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(LogDebugSteps::class);

        $middleware->web(prepend: [
            UseScopedSessionCookie::class,
        ]);

        $middleware->api(
            prepend: [
                UseScopedSessionCookie::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
            ],
            append: [
                StartSession::class,
            ],
        );

        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('admin') || $request->is('admin/*')
            ? route('admin.login')
            : null);

        $middleware->alias([
            'active_plan' => EnsureActivePlanAccess::class,
            'permission' => EnsureAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): void {
            DebugStepLogger::record('exception.report', 'Exception reported', [
                'exception' => $exception,
            ]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
