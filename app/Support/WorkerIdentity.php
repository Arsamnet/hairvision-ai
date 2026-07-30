<?php

namespace App\Support;

/**
 * Resolves a safe operational identifier for the current queue worker process.
 *
 * @since 2.0.0
 */
final class WorkerIdentity
{
    /**
     * Return the watchdog-issued id or a host/PID fallback.
     *
     * @return string Stable identifier for the current PHP process.
     *
     * @since 2.0.0
     */
    public static function current(): string
    {
        $configured = trim((string) getenv('HAIRVISION_IMAGE_WORKER_ID'));

        if ($configured !== '') {
            return $configured;
        }

        return (gethostname() ?: 'unknown-host').':'.getmypid();
    }
}
