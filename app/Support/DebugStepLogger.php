<?php

namespace App\Support;

use App\Services\Debug\DebugStepLogger as DebugStepLoggerService;
use Illuminate\Http\Request;

/**
 * Backward-compatible bridge for legacy imports.
 *
 * New application code should use App\Services\Debug\DebugStepLogger.
 *
 * @since 1.0.0
 */
final class DebugStepLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public static function step(Request $request, string $scope, string $message, array $context = []): void
    {
        DebugStepLoggerService::step($request, $scope, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function record(string $scope, string $message, array $context = []): void
    {
        DebugStepLoggerService::record($scope, $message, $context);
    }

    public static function reset(?string $scope = null): void
    {
        DebugStepLoggerService::reset($scope);
    }

    public static function enabled(): bool
    {
        return DebugStepLoggerService::enabled();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitize(array $context): array
    {
        return DebugStepLoggerService::sanitize($context);
    }
}
