<?php

namespace App\Console\Commands;

use App\Models\ImageWorkerProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Runs one heartbeat-enabled image worker for a bounded lifecycle.
 *
 * @since 2.0.0
 */
class RunImageWorker extends Command
{
    protected $signature = 'hairvision:worker:run
        {slot : Stable worker slot number}
        {--worker-id= : Watchdog-issued worker UUID}
        {--mode=detached : detached or window}
        {--connection= : Queue connection override}
        {--queue= : Comma-separated queue names override}
        {--max-time= : Override the configured worker lifetime}';

    protected $description = 'Run one registered HairVision image worker slot.';

    private bool $stopRequested = false;

    /**
     * Run queue jobs while maintaining the worker heartbeat.
     *
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(): int
    {
        $slot = max(1, (int) $this->argument('slot'));
        $workerId = trim((string) $this->option('worker-id')) ?: (string) Str::uuid();
        $mode = in_array($this->option('mode'), ['detached', 'window'], true)
            ? (string) $this->option('mode')
            : 'detached';
        $maxTime = max(1, (int) ($this->option('max-time') ?: config('hairvision.image_queue.worker_max_time', 3600)));
        $maxJobs = max(1, (int) config('hairvision.image_queue.worker_max_jobs', 250));
        $memoryLimit = max(64, (int) config('hairvision.image_queue.worker_memory', 256));
        $connection = trim((string) $this->option('connection'))
            ?: (string) config('hairvision.image_queue.connection', config('queue.default'));
        $queues = trim((string) $this->option('queue'))
            ?: (string) config('hairvision.image_queue.name', 'ai-generation').',default';
        $startedAt = microtime(true);
        $processed = 0;

        putenv('HAIRVISION_IMAGE_WORKER_ID='.$workerId);
        $_SERVER['HAIRVISION_IMAGE_WORKER_ID'] = $workerId;
        $_ENV['HAIRVISION_IMAGE_WORKER_ID'] = $workerId;
        $this->registerSignals();
        $worker = $this->registerWorker($slot, $workerId, $mode);

        try {
            while (! $this->stopRequested
                && microtime(true) - $startedAt < $maxTime
                && $processed < $maxJobs
                && memory_get_usage(true) / 1024 / 1024 < $memoryLimit) {
                $worker->refresh();

                if (! hash_equals($workerId, (string) $worker->worker_id)
                    || $worker->restart_requested_at) {
                    break;
                }

                $worker->forceFill(['status' => 'running', 'heartbeat_at' => now()])->save();
                $exitCode = Artisan::call('queue:work', [
                    'connection' => $connection,
                    '--queue' => $queues,
                    '--once' => true,
                    '--sleep' => 0,
                    '--timeout' => (int) config('hairvision.image_queue.worker_timeout', 1020),
                    '--tries' => (int) config('hairvision.image_queue.worker_tries', 3),
                    '--memory' => $memoryLimit,
                    '--no-interaction' => true,
                ]);
                $output = Artisan::output();

                if ($exitCode !== self::SUCCESS) {
                    $this->components->warn('A queue worker iteration exited with code '.$exitCode.'.');
                }

                if (str_contains($output, 'DONE') || str_contains($output, 'FAIL')) {
                    $processed++;
                }

                $worker->forceFill([
                    'status' => 'running',
                    'heartbeat_at' => now(),
                    'metadata' => ['processed_jobs' => $processed],
                ])->save();

                if ($mode === 'window' || trim($output) === '') {
                    usleep(1_000_000);
                }

                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
            }
        } finally {
            ImageWorkerProcess::query()
                ->whereKey($worker->id)
                ->where('worker_id', $workerId)
                ->update([
                    'status' => 'stopped',
                    'heartbeat_at' => now(),
                    'stopped_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return self::SUCCESS;
    }

    /**
     * Persist this worker as the sole owner of its slot.
     *
     * @param  int  $slot  Worker slot number.
     * @param  string  $workerId  Worker UUID.
     * @param  string  $mode  detached or window mode.
     * @return ImageWorkerProcess Registered worker row.
     *
     * @since 2.0.0
     */
    private function registerWorker(int $slot, string $workerId, string $mode): ImageWorkerProcess
    {
        return ImageWorkerProcess::query()->updateOrCreate(
            ['slot' => $slot],
            [
                'worker_id' => $workerId,
                'mode' => $mode,
                'pid' => getmypid(),
                'host' => gethostname() ?: null,
                'status' => 'running',
                'started_at' => now(),
                'heartbeat_at' => now(),
                'restart_requested_at' => null,
                'stopped_at' => null,
                'metadata' => ['processed_jobs' => 0],
            ],
        );
    }

    /**
     * Register graceful stop handlers when PCNTL is available.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function registerSignals(): void
    {
        if (! function_exists('pcntl_async_signals') || ! function_exists('pcntl_signal')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function (): void {
            $this->stopRequested = true;
        });
        pcntl_signal(SIGINT, function (): void {
            $this->stopRequested = true;
        });
    }
}
