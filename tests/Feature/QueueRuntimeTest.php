<?php

namespace Tests\Feature;

use App\Jobs\ProcessGenerationAttempt;
use App\Jobs\ProcessGenerationJob;
use App\Models\GenerationAttempt;
use App\Models\GenerationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class QueueRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_job_timeouts_are_below_worker_retry_windows(): void
    {
        $generationJob = new ProcessGenerationJob(new GenerationJob);
        $attemptJob = new ProcessGenerationAttempt(new GenerationAttempt);
        $workerTimeout = (int) config('hairvision.queue_worker.timeout_seconds');

        $this->assertSame(960, $generationJob->timeout);
        $this->assertSame(960, $attemptJob->timeout);
        $this->assertGreaterThanOrEqual($generationJob->timeout, $workerTimeout);
        $this->assertGreaterThan($workerTimeout, (int) config('queue.connections.redis.retry_after'));
        $this->assertGreaterThan($workerTimeout, (int) config('queue.connections.database.retry_after'));
        $this->assertGreaterThanOrEqual($workerTimeout, (int) config('horizon.defaults.supervisor-1.timeout'));
    }

    public function test_cpanel_queue_window_processes_database_jobs_within_one_second(): void
    {
        config([
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.retry_after' => 1200,
        ]);

        Cache::forget(CpanelQueueProbeJob::CACHE_KEY);

        dispatch((new CpanelQueueProbeJob)->onConnection('database'));

        $this->artisan('hairvision:queue-window', [
            'connection' => 'database',
            '--queue' => 'default',
            '--duration' => 1,
            '--interval' => 1,
            '--timeout' => 1020,
        ])->assertSuccessful();

        $this->assertTrue(Cache::get(CpanelQueueProbeJob::CACHE_KEY, false));
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_cpanel_queue_window_rejects_an_unsafe_retry_window(): void
    {
        config([
            'queue.connections.database.connection' => config('database.default'),
            'queue.connections.database.retry_after' => 300,
        ]);

        $this->artisan('hairvision:queue-window', [
            'connection' => 'database',
            '--duration' => 1,
            '--interval' => 1,
            '--timeout' => 1020,
        ])
            ->expectsOutput('Queue retry_after (300) must be greater than worker timeout (1020).')
            ->assertExitCode(Command::FAILURE);
    }
}

class CpanelQueueProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const CACHE_KEY = 'tests.cpanel_queue_probe_processed';

    public int $tries = 1;

    public function handle(): void
    {
        Cache::put(self::CACHE_KEY, true, 60);
    }
}
