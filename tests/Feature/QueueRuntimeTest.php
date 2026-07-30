<?php

namespace Tests\Feature;

use App\Jobs\ProcessGenerationAttempt;
use App\Jobs\ProcessGenerationJob;
use App\Models\GenerationAttempt;
use App\Models\GenerationJob;
use App\Services\Generation\QueueWindowBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Console\Scheduling\Schedule;
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

        $this->assertSame(1010, $generationJob->timeout);
        $this->assertSame(1010, $attemptJob->timeout);
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

    public function test_queue_window_budget_finishes_before_the_next_cron_minute(): void
    {
        $budget = app(QueueWindowBudget::class);

        $this->assertSame(57, $budget->resolve(58, 0));
        $this->assertSame(52, $budget->resolve(58, 5));
        $this->assertSame(1, $budget->resolve(58, 57));
        $this->assertSame(10, $budget->resolve(10, 5));
    }

    public function test_cpanel_scheduler_compiles_queue_connection_as_a_positional_argument(): void
    {
        config([
            'hairvision.queue_worker.cpanel_cron_enabled' => true,
            'hairvision.image_queue.worker_mode' => 'window',
            'hairvision.image_queue.worker_count' => 3,
            'hairvision.queue_worker.cpanel_connection' => 'database',
            'hairvision.queue_worker.queues' => 'default',
            'hairvision.queue_worker.processes' => 3,
            'hairvision.queue_worker.window_seconds' => 58,
            'hairvision.queue_worker.sleep_seconds' => 1,
            'hairvision.queue_worker.timeout_seconds' => 1020,
            'hairvision.queue_worker.overlap_expiration_minutes' => 30,
        ]);

        $schedule = $this->app->make(Schedule::class);
        $eventCount = count($schedule->events());

        require base_path('routes/console.php');

        $events = collect(array_slice($schedule->events(), $eventCount))
            ->filter(fn ($scheduledEvent): bool => is_string($scheduledEvent->command)
                && str_contains(
                    $scheduledEvent->command,
                    'hairvision:queue-window'
                ))
            ->values();
        $event = $events->first();

        $this->assertNotNull($event);
        $this->assertCount(3, $events);
        $this->assertTrue($events->every(fn ($scheduledEvent): bool => $scheduledEvent->runInBackground));
        $this->assertStringContainsString("hairvision:queue-window 'database'", $event->command);
        $this->assertStringNotContainsString("connection='database'", $event->command);
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
