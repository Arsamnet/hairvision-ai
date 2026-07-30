<?php

namespace App\Console\Commands;

use App\Models\GenerationAttempt;
use App\Models\GenerationJob;
use App\Models\ImageWorkerProcess;
use App\Services\Generation\ProviderConcurrencyLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Reports the operational health of the image generation queue.
 *
 * @since 2.0.0
 */
class QueueHealth extends Command
{
    protected $signature = 'hairvision:queue-health {--json : Emit machine-readable JSON}';

    protected $description = 'Report HairVision image queue, workers, capacity, timing, and storage health.';

    /**
     * Collect and render queue health metrics.
     *
     * @param  ProviderConcurrencyLimiter  $providerCapacity  Global provider semaphore.
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(ProviderConcurrencyLimiter $providerCapacity): int
    {
        $connection = (string) config('hairvision.image_queue.connection', config('queue.default'));
        $queueName = (string) config('hairvision.image_queue.name', 'ai-generation');
        $expectedWorkers = max(1, (int) config('hairvision.image_queue.worker_count', 1));
        $staleCutoff = now()->subSeconds(max(120, (int) config('hairvision.image_queue.worker_stale_seconds', 1080)));
        $activeWorkers = ImageWorkerProcess::query()
            ->whereIn('status', ['starting', 'running'])
            ->where('heartbeat_at', '>', $staleCutoff)
            ->count();
        $staleWorkers = ImageWorkerProcess::query()
            ->whereIn('status', ['starting', 'running', 'stopping'])
            ->where(fn ($query) => $query->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<=', $staleCutoff))
            ->count();
        $oldestQueuedAt = GenerationAttempt::query()->where('status', 'queued')->min('created_at')
            ?? GenerationJob::query()->whereIn('status', ['pending', 'queued'])->min('created_at');
        $workerTimeout = max(1, (int) config('hairvision.image_queue.worker_timeout', 1020));
        $providerTimeout = max(1, (int) config('hairvision.image_queue.provider_request_timeout', 900));
        $retryAfter = (int) config('queue.connections.'.$connection.'.retry_after', 0);
        $storageHealthy = $this->storageHealthy();
        $cacheLockHealthy = $this->cacheLockHealthy();
        $processCapabilities = [
            'proc_open' => function_exists('proc_open'),
            'exec' => function_exists('exec'),
            'shell_exec' => function_exists('shell_exec'),
            'pcntl' => function_exists('pcntl_signal'),
        ];
        $workerPoolRequired = (bool) config('hairvision.queue_worker.cpanel_cron_enabled', false);
        $workerPoolHealthy = ! $workerPoolRequired || $activeWorkers >= $expectedWorkers;
        $metrics = [
            'queue_connection' => $connection,
            'queue_name' => $queueName,
            'transport_pending' => DB::table('jobs')->where('queue', $queueName)->count(),
            'generation_pending' => GenerationAttempt::query()->whereIn('status', ['queued', 'reserved', 'dispatched'])->count()
                + GenerationJob::query()->whereIn('status', ['pending', 'queued', 'retrying'])->count(),
            'generation_processing' => GenerationAttempt::query()->where('status', 'processing')->count()
                + GenerationJob::query()->where('status', 'processing')->count(),
            'oldest_job_age_seconds' => $oldestQueuedAt ? max(0, now()->diffInSeconds($oldestQueuedAt)) : 0,
            'workers_expected' => $expectedWorkers,
            'workers_active' => $activeWorkers,
            'workers_stale' => $staleWorkers,
            'worker_pool_healthy' => $workerPoolHealthy,
            'provider_capacity_active' => $providerCapacity->activeCount(),
            'provider_capacity_limit' => $providerCapacity->limit(),
            'average_queue_wait_ms' => $this->averageGenerationMetric('queue_wait_ms'),
            'average_provider_duration_ms' => $this->averageGenerationMetric('provider_latency_ms'),
            'failed_jobs_recent' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'stuck_generations' => GenerationAttempt::query()->where('status', 'processing')->where('updated_at', '<=', now()->subSeconds($workerTimeout))->count()
                + GenerationJob::query()->where('status', 'processing')->where('updated_at', '<=', now()->subSeconds($workerTimeout))->count(),
            'storage_healthy' => $storageHealthy,
            'cache_lock_healthy' => $cacheLockHealthy,
            'process_capabilities' => $processCapabilities,
            'timeout_contract_valid' => $retryAfter > $workerTimeout && $workerTimeout > $providerTimeout,
            'retry_after_seconds' => $retryAfter,
            'worker_timeout_seconds' => $workerTimeout,
            'provider_timeout_seconds' => $providerTimeout,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Metric', 'Value'], collect($metrics)->map(fn ($value, $key): array => [
                $key,
                is_array($value) ? json_encode($value) : var_export($value, true),
            ])->values()->all());
        }

        return $storageHealthy && $cacheLockHealthy && $workerPoolHealthy && $metrics['timeout_contract_valid']
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * Calculate a weighted timing average across both generation architectures.
     *
     * @param  string  $column  Allowlisted numeric timing column.
     * @return float Rounded average in milliseconds.
     *
     * @since 2.0.0
     */
    private function averageGenerationMetric(string $column): float
    {
        if (! in_array($column, ['queue_wait_ms', 'provider_latency_ms'], true)) {
            return 0.0;
        }

        $attemptCount = GenerationAttempt::query()->whereNotNull($column)->count();
        $legacyCount = GenerationJob::query()->whereNotNull($column)->count();
        $count = $attemptCount + $legacyCount;

        if ($count === 0) {
            return 0.0;
        }

        $sum = (float) GenerationAttempt::query()->whereNotNull($column)->sum($column)
            + (float) GenerationJob::query()->whereNotNull($column)->sum($column);

        return round($sum / $count, 2);
    }

    /**
     * Verify that the private local disk can write and remove a probe file.
     *
     * @return bool Whether private storage is writable.
     *
     * @since 2.0.0
     */
    private function storageHealthy(): bool
    {
        $path = 'health/image-queue-'.getmypid().'.tmp';

        try {
            $disk = Storage::disk('local');
            $written = $disk->put($path, 'ok');
            $exists = $disk->exists($path);
            $disk->delete($path);

            return $written && $exists;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Verify that the configured cache store supports atomic locks.
     *
     * @return bool Whether a lock can be acquired and released.
     *
     * @since 2.0.0
     */
    private function cacheLockHealthy(): bool
    {
        try {
            $lock = Cache::lock('hairvision:image-queue-health-probe', 5);

            if (! $lock->get()) {
                return false;
            }

            $lock->release();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
