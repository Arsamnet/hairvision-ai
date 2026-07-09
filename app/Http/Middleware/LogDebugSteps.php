<?php

namespace App\Http\Middleware;

use App\Services\Debug\DebugStepLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records safe request lifecycle steps while Laravel debug mode is enabled.
 *
 * @since 1.0.0
 */
final class LogDebugSteps
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! DebugStepLogger::enabled()) {
            return $next($request);
        }

        $startedAt = microtime(true);

        DebugStepLogger::step($request, 'http.request', 'Request lifecycle started', [
            'query' => $request->query->all(),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
        ]);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            DebugStepLogger::step($request, 'http.request', 'Request lifecycle failed', [
                'duration_ms' => self::elapsedMilliseconds($startedAt),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        DebugStepLogger::step($request, 'http.request', 'Request lifecycle completed', [
            'status' => $response->getStatusCode(),
            'duration_ms' => self::elapsedMilliseconds($startedAt),
        ]);

        return $response;
    }

    private static function elapsedMilliseconds(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
