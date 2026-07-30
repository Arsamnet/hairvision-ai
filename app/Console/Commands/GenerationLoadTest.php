<?php

namespace App\Console\Commands;

use App\Jobs\RunImageLoadProbe;
use App\Models\ImageLoadTestSample;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Runs an opt-in database-queue load test with bounded fake provider latency.
 *
 * @since 2.0.0
 */
class GenerationLoadTest extends Command
{
    protected $signature = 'hairvision:generation-load-test
        {--batches=1,5,10,20 : Concurrent generation counts}
        {--min-delay-ms=20000 : Minimum fake provider latency}
        {--max-delay-ms=30000 : Maximum fake provider latency}
        {--workers= : Temporary queue worker count}';

    protected $description = 'Measure queue wait, P95, throughput, errors, memory, and processes with a fake provider.';

    /**
     * Dispatch load probes, run temporary workers, and persist a JSON report.
     *
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(): int
    {
        $batches = collect(explode(',', (string) $this->option('batches')))
            ->map(fn (string $value): int => (int) trim($value))
            ->filter(fn (int $value): bool => $value > 0)
            ->unique()
            ->values();

        if ($batches->isEmpty()) {
            $this->components->error('At least one positive batch size is required.');

            return self::INVALID;
        }

        $minimumDelay = max(0, (int) $this->option('min-delay-ms'));
        $maximumDelay = max($minimumDelay, (int) $this->option('max-delay-ms'));
        $workers = max(1, (int) ($this->option('workers') ?: config('hairvision.image_queue.worker_count', 1)));
        $connection = (string) config('hairvision.image_queue.connection', config('queue.default'));
        $runId = (string) Str::uuid();
        $report = [
            'run_id' => $runId,
            'connection' => $connection,
            'queue' => 'image-load-test',
            'workers' => $workers,
            'provider_concurrency' => (int) config('hairvision.image_queue.provider_max_concurrency', 1),
            'delay_ms' => [$minimumDelay, $maximumDelay],
            'batches' => [],
        ];

        config([
            'hairvision.image_queue.provider_capacity_delay_min_seconds' => 1,
            'hairvision.image_queue.provider_capacity_delay_max_seconds' => 2,
        ]);

        foreach ($batches as $batchSize) {
            $this->components->info('Running '.$batchSize.' concurrent generation probe(s)...');
            $queuedAt = now();

            for ($sequence = 1; $sequence <= $batchSize; $sequence++) {
                $sample = ImageLoadTestSample::query()->create([
                    'run_id' => $runId,
                    'batch_size' => $batchSize,
                    'sequence' => $sequence,
                    'status' => 'queued',
                    'queued_at' => $queuedAt,
                ]);
                dispatch((new RunImageLoadProbe($sample->id, $minimumDelay, $maximumDelay))
                    ->onConnection($connection)
                    ->onQueue('image-load-test'));
            }

            $processes = $this->startWorkers($workers, $connection, $batchSize, $maximumDelay);
            $timeoutSeconds = max(60, (int) ceil($batchSize / max(1, min(
                $workers,
                (int) config('hairvision.image_queue.provider_max_concurrency', 1),
            ))) * max(1, $maximumDelay / 1000) + 60);
            $deadline = microtime(true) + $timeoutSeconds;

            while (microtime(true) < $deadline) {
                $remaining = ImageLoadTestSample::query()
                    ->where('run_id', $runId)
                    ->where('batch_size', $batchSize)
                    ->whereNotIn('status', ['completed', 'failed'])
                    ->count();

                if ($remaining === 0) {
                    break;
                }

                usleep(250_000);
            }

            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(3, 15);
                }
            }

            $report['batches'][] = $this->batchMetrics($runId, $batchSize);
        }

        $path = 'load-tests/image-queue-'.now()->format('Ymd-His').'.json';
        Storage::disk('local')->put($path, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->components->info('Private report: storage/app/private/'.$path);

        return collect($report['batches'])->sum('failed') === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Start temporary Laravel workers for the isolated load-test queue.
     *
     * @param  int  $count  Number of worker processes.
     * @param  string  $connection  Queue connection name.
     * @param  int  $batchSize  Current generation count.
     * @param  int  $maximumDelay  Maximum provider delay in milliseconds.
     * @return array<int, Process> Started worker processes.
     *
     * @since 2.0.0
     */
    private function startWorkers(int $count, string $connection, int $batchSize, int $maximumDelay): array
    {
        $processes = [];
        $maxTime = max(60, (int) ceil($batchSize * max(1, $maximumDelay / 1000)) + 60);

        for ($worker = 1; $worker <= $count; $worker++) {
            $process = new Process([
                PHP_BINARY,
                base_path('artisan'),
                'queue:work',
                $connection,
                '--queue=image-load-test',
                '--sleep=1',
                '--max-time='.$maxTime,
                '--timeout='.(int) config('hairvision.image_queue.worker_timeout', 1020),
                '--tries=0',
                '--memory='.(int) config('hairvision.image_queue.worker_memory', 256),
                '--no-interaction',
            ], base_path(), ['HAIRVISION_IMAGE_WORKER_ID' => 'load-test-'.$worker]);
            $process->setTimeout(null);
            $process->disableOutput();
            $process->start();
            $processes[] = $process;
        }

        return $processes;
    }

    /**
     * Aggregate one batch into user-facing load metrics.
     *
     * @param  string  $runId  Load test UUID.
     * @param  int  $batchSize  Concurrent generation count.
     * @return array<string, int|float> Aggregated batch metrics.
     *
     * @since 2.0.0
     */
    private function batchMetrics(string $runId, int $batchSize): array
    {
        $samples = ImageLoadTestSample::query()
            ->where('run_id', $runId)
            ->where('batch_size', $batchSize)
            ->get();
        $waits = $samples->pluck('queue_wait_ms')->filter(fn (mixed $value): bool => is_numeric($value))->sort()->values();
        $p95Index = $waits->isEmpty() ? 0 : (int) ceil($waits->count() * 0.95) - 1;
        $first = $samples->min('queued_at');
        $last = $samples->max('completed_at');
        $duration = $first && $last ? max(0.001, $first->diffInMilliseconds($last) / 1000) : 0.001;
        $failed = $samples->where('status', 'failed')->count();

        return [
            'generations' => $batchSize,
            'completed' => $samples->where('status', 'completed')->count(),
            'failed' => $failed,
            'error_rate_percent' => round($failed / max(1, $samples->count()) * 100, 2),
            'duration_seconds' => round($duration, 3),
            'throughput_per_second' => round($samples->where('status', 'completed')->count() / $duration, 3),
            'average_queue_wait_ms' => round((float) $samples->avg('queue_wait_ms'), 2),
            'p95_queue_wait_ms' => (int) ($waits->get(max(0, $p95Index)) ?? 0),
            'average_provider_latency_ms' => round((float) $samples->avg('provider_latency_ms'), 2),
            'peak_memory_bytes' => (int) $samples->max('memory_bytes'),
            'process_count' => $samples->pluck('worker_pid')->filter()->unique()->count(),
        ];
    }
}
