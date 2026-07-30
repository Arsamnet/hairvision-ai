<?php

namespace App\Services\Generation;

use App\Models\ImageWorkerProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reconciles configured image-worker slots with detached PHP processes.
 *
 * @since 2.0.0
 */
class WorkerProcessManager
{
    /**
     * Ensure each configured detached worker slot has exactly one live process.
     *
     * @return array{mode: string, expected: int, active: int, started: int, stale: int, errors: array<int, string>}
     *
     * @since 2.0.0
     */
    public function ensure(): array
    {
        return Cache::lock('hairvision:image-workers-ensure', 55)->block(5, function (): array {
            $mode = (string) config('hairvision.image_queue.worker_mode', 'detached');
            $expected = max(1, (int) config('hairvision.image_queue.worker_count', 1));
            $report = [
                'mode' => $mode,
                'expected' => $expected,
                'active' => 0,
                'started' => 0,
                'stale' => 0,
                'errors' => [],
            ];

            if ($mode === 'window') {
                $report['active'] = ImageWorkerProcess::query()
                    ->where('mode', 'window')
                    ->where('status', 'running')
                    ->where('heartbeat_at', '>', now()->subSeconds(90))
                    ->count();

                return $report;
            }

            if (! $this->canLaunchDetachedProcesses()) {
                $report['errors'][] = 'Detached PHP processes are unavailable; set IMAGE_WORKER_MODE=window.';

                return $report;
            }

            for ($slot = 1; $slot <= $expected; $slot++) {
                $worker = ImageWorkerProcess::query()->where('slot', $slot)->first();

                if ($worker && $this->isHealthy($worker)) {
                    $report['active']++;
                    continue;
                }

                if ($worker) {
                    $report['stale']++;
                    $this->stopOwnedProcess($worker);
                    $worker->forceFill([
                        'status' => 'stopped',
                        'stopped_at' => now(),
                    ])->save();
                }

                try {
                    $this->startSlot($slot);
                    $report['started']++;
                    $report['active']++;
                } catch (RuntimeException $exception) {
                    $report['errors'][] = $exception->getMessage();
                }
            }

            ImageWorkerProcess::query()
                ->where('slot', '>', $expected)
                ->whereIn('status', ['starting', 'running'])
                ->get()
                ->each(function (ImageWorkerProcess $worker): void {
                    $worker->forceFill(['restart_requested_at' => now(), 'status' => 'stopping'])->save();
                    $this->stopOwnedProcess($worker);
                });

            return $report;
        });
    }

    /**
     * Mark every registered worker for a deployment-safe restart.
     *
     * @return int Number of worker rows signalled.
     *
     * @since 2.0.0
     */
    public function requestRestart(): int
    {
        return Cache::lock('hairvision:image-workers-ensure', 55)->block(5, function (): int {
            $workers = ImageWorkerProcess::query()
                ->whereIn('status', ['starting', 'running'])
                ->get();

            foreach ($workers as $worker) {
                $worker->forceFill([
                    'restart_requested_at' => now(),
                    'status' => 'stopping',
                ])->save();
                $this->stopOwnedProcess($worker);
            }

            return $workers->count();
        });
    }

    /**
     * Determine whether the host exposes the primitives needed for detachment.
     *
     * @return bool Whether detached launching can be attempted.
     *
     * @since 2.0.0
     */
    public function canLaunchDetachedProcesses(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && function_exists('proc_open')
            && is_file(base_path('artisan'))
            && is_executable(PHP_BINARY);
    }

    /**
     * Check whether one registry row still represents a healthy owned process.
     *
     * @param  ImageWorkerProcess  $worker  Registry row being checked.
     * @return bool Whether the row is live and recently heartbeating.
     *
     * @since 2.0.0
     */
    protected function isHealthy(ImageWorkerProcess $worker): bool
    {
        $staleSeconds = max(120, (int) config('hairvision.image_queue.worker_stale_seconds', 1080));

        return in_array($worker->status, ['starting', 'running'], true)
            && $worker->heartbeat_at?->gt(now()->subSeconds($staleSeconds))
            && $this->isOwnedProcessAlive($worker);
    }

    /**
     * Start one detached worker and persist its PID before returning.
     *
     * @param  int  $slot  Configured worker slot.
     * @return ImageWorkerProcess Newly registered worker row.
     *
     * @throws RuntimeException
     *
     * @since 2.0.0
     */
    protected function startSlot(int $slot): ImageWorkerProcess
    {
        $workerId = (string) Str::uuid();
        $logPath = storage_path('logs/image-worker-'.$slot.'.log');
        $worker = ImageWorkerProcess::query()->updateOrCreate(
            ['slot' => $slot],
            [
                'worker_id' => $workerId,
                'mode' => 'detached',
                'pid' => null,
                'host' => gethostname() ?: null,
                'status' => 'starting',
                'started_at' => now(),
                'heartbeat_at' => now(),
                'restart_requested_at' => null,
                'stopped_at' => null,
                'metadata' => ['log' => basename($logPath)],
            ],
        );
        $command = implode(' ', array_map('escapeshellarg', [
            PHP_BINARY,
            base_path('artisan'),
            'hairvision:worker:run',
            (string) $slot,
            '--worker-id='.$workerId,
            '--mode=detached',
        ]));
        $shellCommand = 'nohup '.$command.' >> '.escapeshellarg($logPath).' 2>&1 < /dev/null & echo $!';
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['/bin/sh', '-c', $shellCommand], $descriptors, $pipes, base_path());

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start image worker slot '.$slot.'.');
        }

        $pidOutput = trim((string) stream_get_contents($pipes[1]));
        $errorOutput = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $pid = ctype_digit($pidOutput) ? (int) $pidOutput : 0;

        if ($exitCode !== 0 || $pid <= 0) {
            $worker->forceFill([
                'status' => 'failed',
                'stopped_at' => now(),
                'metadata' => ['log' => basename($logPath), 'launch_failed' => true],
            ])->save();

            throw new RuntimeException('Could not start image worker slot '.$slot.($errorOutput !== '' ? ': '.$errorOutput : '.'));
        }

        ImageWorkerProcess::query()
            ->whereKey($worker->id)
            ->where('worker_id', $workerId)
            ->update(['pid' => $pid, 'updated_at' => now()]);

        return $worker->refresh();
    }

    /**
     * Determine whether a PID is alive and still belongs to its registered command.
     *
     * @param  ImageWorkerProcess  $worker  Registry row being checked.
     * @return bool Whether the PID belongs to this HairVision worker.
     *
     * @since 2.0.0
     */
    protected function isOwnedProcessAlive(ImageWorkerProcess $worker): bool
    {
        $pid = (int) $worker->pid;

        if ($pid <= 1) {
            return false;
        }

        if (function_exists('posix_kill') && ! @posix_kill($pid, 0)) {
            return false;
        }

        $output = [];
        $exitCode = 1;

        if (function_exists('exec')) {
            @exec('ps -p '.escapeshellarg((string) $pid).' -o command=', $output, $exitCode);
        }

        if ($exitCode !== 0 || $output === []) {
            return function_exists('posix_kill') && @posix_kill($pid, 0);
        }

        $command = implode(' ', $output);

        return str_contains($command, 'hairvision:worker:run')
            && str_contains($command, (string) $worker->worker_id);
    }

    /**
     * Gracefully signal a process only after its command ownership is verified.
     *
     * @param  ImageWorkerProcess  $worker  Registry row being stopped.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    protected function stopOwnedProcess(ImageWorkerProcess $worker): void
    {
        if (! $this->isOwnedProcessAlive($worker)) {
            return;
        }

        if (function_exists('posix_kill')) {
            @posix_kill((int) $worker->pid, 15);
        }
    }
}
