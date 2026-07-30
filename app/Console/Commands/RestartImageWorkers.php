<?php

namespace App\Console\Commands;

use App\Services\Generation\WorkerProcessManager;
use Illuminate\Console\Command;

/**
 * Requests a safe image-worker recycle after deployment.
 *
 * @since 2.0.0
 */
class RestartImageWorkers extends Command
{
    protected $signature = 'hairvision:workers:restart';

    protected $description = 'Gracefully stop registered image workers for watchdog replacement.';

    /**
     * Signal workers and leave replacement to the watchdog.
     *
     * @param  WorkerProcessManager  $workers  Worker lifecycle manager.
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(WorkerProcessManager $workers): int
    {
        $count = $workers->requestRestart();
        $this->components->info($count.' image worker(s) were signalled for restart.');

        return self::SUCCESS;
    }
}
