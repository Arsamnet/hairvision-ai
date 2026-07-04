<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Logs ordered debug steps.
 *
 * @since 1.0.0
 */
final class DebugStepLogger
{
    private const COUNTER_KEY = '_debug_step_logger_counter';

    /**
     * Log a request-scoped step when debug mode is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    public static function step(Request $request, string $scope, string $message, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        $step = ((int) $request->attributes->get(self::COUNTER_KEY, 0)) + 1;

        $request->attributes->set(self::COUNTER_KEY, $step);

        Log::debug(sprintf('[%s] Step %02d: %s', $scope, $step, $message), $context);
    }

    /**
     * Log a workflow step when no Request instance is available.
     *
     * Use this for queued jobs, console commands, and background workflows.
     *
     * @param  array<string, mixed>  $context
     */
    public static function workflowStep(string $scope, int $step, string $message, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::debug(sprintf('[%s] Step %02d: %s', $scope, $step, $message), $context);
    }
}