<?php

namespace Tests\Feature;

use App\Data\Ai\AiModelRequestData;
use App\Jobs\ProcessGenerationAttempt;
use App\Jobs\ProcessGenerationJob;
use App\Models\AiModelAccess;
use App\Models\GenerationAttempt;
use App\Models\GenerationJob;
use App\Models\ImageWorkerProcess;
use App\Models\Tenant;
use App\Services\AI\AiModelTypeRegistry;
use App\Services\AI\Providers\FakeImageProvider;
use App\Services\Generation\ProviderConcurrencyLimiter;
use App\Services\Generation\WorkerProcessManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageQueueArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_provider_capacity_is_independent_from_worker_count_and_recovers_expired_leases(): void
    {
        config([
            'hairvision.image_queue.worker_count' => 8,
            'hairvision.image_queue.provider_max_concurrency' => 2,
        ]);
        $capacity = app(ProviderConcurrencyLimiter::class);

        $first = $capacity->acquire('test', 1, 'worker-1');
        $duplicate = $capacity->acquire('test', 1, 'worker-duplicate');
        $second = $capacity->acquire('test', 2, 'worker-2');

        $this->assertNotNull($first);
        $this->assertNull($duplicate);
        $this->assertNotNull($second);
        $this->assertNull($capacity->acquire('test', 3, 'worker-3'));
        $this->assertSame(2, $capacity->activeCount());

        $first->forceFill(['expires_at' => now()->subSecond()])->save();
        $replacement = $capacity->acquire('test', 3, 'worker-3');

        $this->assertNotNull($replacement);
        $this->assertSame(2, $capacity->activeCount());
        $capacity->release($second);
        $capacity->release($replacement);
        $this->assertSame(0, $capacity->activeCount());
    }

    public function test_watchdog_preserves_healthy_slots_and_replaces_only_a_dead_slot(): void
    {
        config([
            'hairvision.image_queue.worker_mode' => 'detached',
            'hairvision.image_queue.worker_count' => 2,
        ]);
        $originalId = (string) Str::uuid();
        ImageWorkerProcess::query()->create([
            'slot' => 1,
            'worker_id' => $originalId,
            'mode' => 'detached',
            'pid' => 1001,
            'status' => 'starting',
            'started_at' => now(),
            'heartbeat_at' => now(),
            'metadata' => ['healthy' => true],
        ]);

        $manager = new class extends WorkerProcessManager
        {
            public int $started = 0;

            public int $stopped = 0;

            public function canLaunchDetachedProcesses(): bool
            {
                return true;
            }

            protected function isHealthy(ImageWorkerProcess $worker): bool
            {
                return in_array($worker->status, ['starting', 'running'], true)
                    && ($worker->metadata['healthy'] ?? false) === true;
            }

            protected function startSlot(int $slot): ImageWorkerProcess
            {
                $this->started++;

                return ImageWorkerProcess::query()->updateOrCreate(
                    ['slot' => $slot],
                    [
                        'worker_id' => (string) Str::uuid(),
                        'mode' => 'detached',
                        'pid' => 2000 + $slot,
                        'status' => 'running',
                        'started_at' => now(),
                        'heartbeat_at' => now(),
                        'stopped_at' => null,
                        'metadata' => ['healthy' => true],
                    ],
                );
            }

            protected function stopOwnedProcess(ImageWorkerProcess $worker): void
            {
                $this->stopped++;
            }
        };

        $firstReport = $manager->ensure();
        $secondReport = $manager->ensure();

        $this->assertSame(1, $firstReport['started']);
        $this->assertSame(0, $secondReport['started']);
        $this->assertSame(2, $secondReport['active']);
        $this->assertSame(1, $manager->started);
        $this->assertDatabaseCount('image_worker_processes', 2);

        ImageWorkerProcess::query()->where('slot', 1)->firstOrFail()
            ->forceFill(['metadata' => ['healthy' => false]])
            ->save();
        $replacementReport = $manager->ensure();

        $this->assertSame(1, $replacementReport['stale']);
        $this->assertSame(1, $replacementReport['started']);
        $this->assertSame(2, $manager->started);
        $this->assertSame(1, $manager->stopped);
        $this->assertNotSame($originalId, ImageWorkerProcess::query()->where('slot', 1)->value('worker_id'));
    }

    public function test_replaced_worker_stops_when_it_no_longer_owns_its_slot(): void
    {
        config([
            'queue.default' => 'database',
            'hairvision.image_queue.connection' => 'database',
            'hairvision.image_queue.name' => 'default',
            'hairvision.image_queue.worker_max_jobs' => 250,
            'hairvision.image_queue.worker_memory' => 1024,
        ]);
        $originalId = (string) Str::uuid();
        $replacementId = (string) Str::uuid();

        dispatch((new ReplaceWorkerSlotOwnership($replacementId))->onQueue('default'));
        dispatch((new MarkUnexpectedSecondQueueJob)->onQueue('default'));

        $this->artisan('hairvision:worker:run', [
            'slot' => 1,
            '--worker-id' => $originalId,
            '--mode' => 'detached',
            '--max-time' => 5,
        ])->assertSuccessful();

        $worker = ImageWorkerProcess::query()->where('slot', 1)->firstOrFail();

        $this->assertSame($replacementId, $worker->worker_id);
        $this->assertSame('starting', $worker->status);
        $this->assertNull($worker->stopped_at);
        $this->assertFalse(Cache::has('tests:unexpected-second-worker-job'));
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_generation_jobs_are_after_commit_identifier_only_payloads(): void
    {
        $legacyModel = new GenerationJob;
        $legacyModel->setAttribute('id', 41);
        $legacyModel->exists = true;
        $attemptModel = new GenerationAttempt;
        $attemptModel->setAttribute('id', 42);
        $attemptModel->exists = true;

        $legacy = new ProcessGenerationJob($legacyModel);
        $independent = new ProcessGenerationAttempt($attemptModel);
        $serialized = serialize([$legacy, $independent]);

        $this->assertTrue($legacy->afterCommit);
        $this->assertTrue($independent->afterCommit);
        $this->assertStringNotContainsString('binary_content', $serialized);
        $this->assertStringNotContainsString('original_path', $serialized);
        $this->assertLessThan(1500, strlen($serialized));
    }

    public function test_fake_provider_is_deterministic_test_only_and_supports_failure_scenarios(): void
    {
        $registry = app(AiModelTypeRegistry::class);
        $this->assertArrayNotHasKey('fake_image', $registry->all());

        $request = new AiModelRequestData(
            clinic: new Tenant,
            access: new AiModelAccess,
            usageCategoryKey: 'hair_transplant',
            modelConfig: [],
            credentials: [],
            settings: [],
            prompt: null,
            payload: [
                'fake_delay_min_ms' => 0,
                'fake_delay_max_ms' => 0,
                'fake_scenario' => 'rate_limited',
            ],
        );

        $response = app(FakeImageProvider::class)->run($request);

        $this->assertFalse($response->successful);
        $this->assertSame('provider_rate_limited', $response->errorCode);
        $this->assertTrue($response->retryable);
        $this->assertSame(2, $response->retryAfterSeconds);
    }

    public function test_frontend_has_one_resumable_poll_loop_without_a_global_timeout(): void
    {
        $script = (string) file_get_contents(resource_path('js/landing.js'));

        $this->assertSame(1, substr_count($script, 'STATE.pollPromise = runPollingLoop()'));
        $this->assertStringContainsString('if (STATE.pollPromise) return STATE.pollPromise;', $script);
        $this->assertStringContainsString("window.addEventListener('focus'", $script);
        $this->assertStringContainsString("window.addEventListener('online'", $script);
        $this->assertStringContainsString("document.addEventListener('visibilitychange'", $script);
        $this->assertStringContainsString('Promise.allSettled(targets.map', $script);
        $this->assertStringContainsString('function latestGenerationsPerOutput(generations)', $script);
        $this->assertStringContainsString('return latestGenerationsPerOutput(Array.from(map.values()));', $script);
        $this->assertStringContainsString('STATE.generations = latestGenerationsPerOutput(items);', $script);
        $this->assertStringContainsString("if (!failed) UI.clearError('generationError');", $script);
        $this->assertStringContainsString("STATE.generations.every((generation) => ['completed', 'failed', 'cancelled'].includes(generation.status))", $script);
        $this->assertStringContainsString('sessionStorage.setItem(STORAGE_KEYS.resumeTokens', $script);
        $this->assertStringContainsString('const submissionScope = `${sourceImageId}:${categoryId}:${outputId}`;', $script);
        $this->assertStringContainsString('const idempotencyKey = STATE.generationSubmissionKeys.get(submissionScope) || newIdempotencyKey();', $script);
        $this->assertStringContainsString('await API.createGeneration(sourceImageId, categoryId, outputId, idempotencyKey)', $script);
        $this->assertStringContainsString('result.success !== false || Number(result.status) < 500', $script);
        $this->assertStringContainsString('async function recoverAmbiguousGenerationSubmission(', $script);
        $this->assertStringContainsString('const latestMatch = (result.data || []).find', $script);
        $this->assertStringNotContainsString("UI.showError('generationError', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');", $script);
        $this->assertStringNotContainsString('maxWaitMs', $script);
    }

    public function test_local_cron_installer_uses_the_scheduler_watchdog_not_once_per_minute_workers(): void
    {
        $script = (string) file_get_contents(base_path('scripts/hairvision-cron'));

        $this->assertStringContainsString('artisan\' schedule:run', $script);
        $this->assertStringContainsString('HAIRVISION_CPANEL_QUEUE_CRON_ENABLED=true', $script);
        $this->assertStringContainsString('IMAGE_WORKER_MODE=detached', $script);
        $this->assertStringNotContainsString('queue:work --once', $script);
    }
}

/**
 * Reassign an image-worker slot while its former owner is running.
 *
 * @since 2.0.0
 */
class ReplaceWorkerSlotOwnership implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $replacementId) {}

    public function handle(): void
    {
        ImageWorkerProcess::query()->where('slot', 1)->update([
            'worker_id' => $this->replacementId,
            'pid' => 99999,
            'status' => 'starting',
        ]);
    }
}

/**
 * Mark an invalid second dequeue by a worker that has lost slot ownership.
 *
 * @since 2.0.0
 */
class MarkUnexpectedSecondQueueJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::forever('tests:unexpected-second-worker-job', true);
    }
}
