<?php

namespace App\Jobs;

use App\Models\ImageLoadTestSample;
use App\Services\Generation\ProviderConcurrencyLimiter;
use App\Support\WorkerIdentity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Simulates one bounded provider call for the opt-in queue load test.
 *
 * @since 2.0.0
 */
class RunImageLoadProbe implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    /**
     * Create one load probe with an identifier-only queue payload.
     *
     * @param  int  $sampleId  Database sample id.
     * @param  int  $minimumDelayMs  Minimum simulated provider latency.
     * @param  int  $maximumDelayMs  Maximum simulated provider latency.
     *
     * @since 2.0.0
     */
    public function __construct(
        public int $sampleId,
        public int $minimumDelayMs,
        public int $maximumDelayMs,
    ) {
        $this->afterCommit();
    }

    /**
     * Acquire provider capacity, sleep, and record queue timing metrics.
     *
     * @param  ProviderConcurrencyLimiter  $capacity  Global provider semaphore.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function handle(ProviderConcurrencyLimiter $capacity): void
    {
        $sample = ImageLoadTestSample::query()->find($this->sampleId);

        if (! $sample || $sample->status === 'completed') {
            return;
        }

        $lease = $capacity->acquire('load_probe', $sample->id, WorkerIdentity::current());

        if (! $lease) {
            $this->release($capacity->retryDelaySeconds());

            return;
        }

        try {
            $startedAt = now();
            $sample->forceFill([
                'status' => 'processing',
                'started_at' => $startedAt,
                'queue_wait_ms' => max(0, $sample->queued_at->diffInMilliseconds($startedAt)),
                'worker_pid' => getmypid(),
            ])->save();
            $delay = random_int(max(0, $this->minimumDelayMs), max($this->minimumDelayMs, $this->maximumDelayMs));

            if ($delay > 0) {
                usleep($delay * 1000);
            }

            $sample->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'provider_latency_ms' => max(0, $startedAt->diffInMilliseconds(now())),
                'memory_bytes' => memory_get_peak_usage(true),
            ])->save();
        } finally {
            $capacity->release($lease);
        }
    }

    /**
     * Persist a probe failure if the queue worker terminates it.
     *
     * @param  Throwable  $exception  Queue failure.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function failed(Throwable $exception): void
    {
        ImageLoadTestSample::query()->whereKey($this->sampleId)->update([
            'status' => 'failed',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
