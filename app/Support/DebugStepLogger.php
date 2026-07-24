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
     * Execute the step operation.
     *
     * @param  array<string, mixed>  $context
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $scope  The scope value.
     * @param  string  $message  The message value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public static function step(Request $request, string $scope, string $message, array $context = []): void
    {
        DebugStepLoggerService::step($request, $scope, $message, $context);
    }

    /**
     * Record a sanitized compatibility debug step.
     *
     * @param  array<string, mixed>  $context
     * @param  string  $scope  The scope value.
     * @param  string  $message  The message value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public static function record(string $scope, string $message, array $context = []): void
    {
        DebugStepLoggerService::record($scope, $message, $context);
    }

    /**
     * Execute the reset operation.
     *
     * @param  string|null  $scope  The scope value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public static function reset(?string $scope = null): void
    {
        DebugStepLoggerService::reset($scope);
    }

    /**
     * Execute the enabled operation.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public static function enabled(): bool
    {
        return DebugStepLoggerService::enabled();
    }

    /**
     * Execute the sanitize operation.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public static function sanitize(array $context): array
    {
        return DebugStepLoggerService::sanitize($context);
    }
}
