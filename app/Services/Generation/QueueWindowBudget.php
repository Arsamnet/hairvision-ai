<?php

namespace App\Services\Generation;

/**
 * Keeps a bounded queue-worker window inside its current cron minute.
 *
 * @since 2.0.0
 */
class QueueWindowBudget
{
    /**
     * Last second of the minute that a window may actively occupy.
     */
    private const CUTOFF_SECOND = 57;

    /**
     * Resolve a safe worker duration that releases overlap locks before the next minute.
     *
     * @param  int  $configuredDuration  Configured maximum duration in seconds.
     * @param  int|null  $currentSecond  Current second of the minute, or null to read the clock.
     * @return int Effective duration in seconds.
     *
     * @since 2.0.0
     */
    public function resolve(int $configuredDuration, ?int $currentSecond = null): int
    {
        $second = min(59, max(0, $currentSecond ?? (int) now()->format('s')));
        $remainingMinuteBudget = max(1, self::CUTOFF_SECOND - $second);

        return min(max(1, $configuredDuration), $remainingMinuteBudget);
    }
}
