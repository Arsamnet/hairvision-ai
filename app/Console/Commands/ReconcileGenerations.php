<?php

namespace App\Console\Commands;

use App\Jobs\ProcessGenerationJob;
use App\Models\GenerationAttempt;
use App\Models\GenerationJob;
use App\Services\Generation\GeneratedAssetIntegrityService;
use App\Services\Generation\GenerationGroupStatusService;
use App\Services\Generation\ModelQueueCoordinator;
use Illuminate\Console\Command;

/**
 * Recovers generation records left non-terminal by crashed workers.
 *
 * @since 2.0.0
 */
class ReconcileGenerations extends Command
{
    protected $signature = 'hairvision:generations:reconcile';

    protected $description = 'Recover safe stuck image jobs and flag ambiguous provider outcomes.';

    /**
     * Reconcile legacy and independent generation records.
     *
     * @param  ModelQueueCoordinator  $queue  Independent model queue coordinator.
     * @param  GeneratedAssetIntegrityService  $integrity  Private asset validator.
     * @param  GenerationGroupStatusService  $groups  Legacy group status derivation.
     * @return int Console exit code.
     *
     * @since 2.0.0
     */
    public function handle(
        ModelQueueCoordinator $queue,
        GeneratedAssetIntegrityService $integrity,
        GenerationGroupStatusService $groups,
    ): int {
        $recoveredAttempts = $queue->recoverStaleReservations();
        $dispatchedAttempts = $queue->dispatchAllAvailable();
        $cutoff = now()->subSeconds(max(60, (int) config('hairvision.image_queue.worker_timeout', 1020)));
        $legacyRecovered = 0;
        $legacyFailed = 0;

        GenerationJob::query()
            ->where('status', 'processing')
            ->where(fn ($query) => $query
                ->where('started_at', '<=', $cutoff)
                ->orWhere('updated_at', '<=', $cutoff))
            ->with(['asset', 'group'])
            ->chunkById(100, function ($jobs) use ($integrity, $groups, &$legacyRecovered, &$legacyFailed): void {
                foreach ($jobs as $job) {
                    if ($job->asset && $integrity->isValid($job->asset)) {
                        $job->forceFill([
                            'status' => 'completed',
                            'completed_at' => $job->completed_at ?? now(),
                            'last_error' => null,
                            'error_code' => null,
                        ])->save();
                        $legacyRecovered++;
                    } elseif ($job->provider_call_started_at) {
                        $job->forceFill([
                            'status' => 'failed',
                            'completed_at' => now(),
                            'error_code' => 'provider_outcome_requires_review',
                            'last_error' => __('ai_models.errors.provider_failed'),
                        ])->save();
                        $legacyFailed++;
                    } else {
                        $job->forceFill([
                            'status' => 'queued',
                            'queued_at' => now(),
                            'started_at' => null,
                            'worker_id' => null,
                        ])->save();
                        dispatch((new ProcessGenerationJob($job->refresh()))
                            ->onConnection((string) config('hairvision.image_queue.connection', config('queue.default')))
                            ->onQueue((string) config('hairvision.image_queue.name', 'ai-generation')));
                        $legacyRecovered++;
                    }

                    if ($job->group) {
                        $groups->refresh($job->group);
                    }
                }
            });

        $stuckAttempts = GenerationAttempt::query()
            ->whereIn('status', ['processing', 'requires_review'])
            ->where('updated_at', '<=', $cutoff)
            ->count();

        $this->components->info(sprintf(
            'attempt_models_recovered=%d attempts_dispatched=%d legacy_recovered=%d legacy_failed=%d remaining_stuck=%d',
            $recoveredAttempts,
            $dispatchedAttempts,
            $legacyRecovered,
            $legacyFailed,
            $stuckAttempts,
        ));

        return self::SUCCESS;
    }
}
