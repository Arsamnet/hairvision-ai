<?php

namespace App\Console\Commands;

use App\Services\Generation\WorkerProcessManager;
use Illuminate\Console\Command;

/**
 * Scheduler watchdog that restores missing image-worker processes.
 *
 * @since 2.0.0
 */
class EnsureImageWorkers extends Command
{
    protected $signature = 'hairvision:workers:ensure';

    protected $description = 'Ensure the configured HairVision image worker pool is running.';

    /**
     * Reconcile worker slots and report the resulting pool state.
     *
     * @param  WorkerProcessManager  $workers  Worker lifecycle manager.
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(WorkerProcessManager $workers): int
    {
        $report = $workers->ensure();

        $this->components->info(sprintf(
            'mode=%s expected=%d active=%d started=%d stale=%d',
            $report['mode'],
            $report['expected'],
            $report['active'],
            $report['started'],
            $report['stale'],
        ));

        foreach ($report['errors'] as $error) {
            $this->components->error($error);
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
