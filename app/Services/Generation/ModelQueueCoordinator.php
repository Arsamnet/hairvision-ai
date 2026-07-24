<?php

namespace App\Services\Generation;

use App\Jobs\ProcessGenerationAttempt;
use App\Models\AiModel;
use App\Models\GenerationAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coordinates the database-backed logical FIFO queue for every executable AI model.
 *
 * Database rows are the capacity authority. The Laravel database queue only
 * transports attempts that already own one of the model's reserved slots.
 *
 * @since 2.0.0
 */
class ModelQueueCoordinator
{
    /**
     * Reserve free model slots and enqueue the oldest available attempts.
     *
     * @param  int  $modelId  The model id value.
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    public function dispatchAvailableForModel(int $modelId): int
    {
        /** @var Collection<int, GenerationAttempt> $reserved */
        $reserved = DB::transaction(function () use ($modelId): Collection {
            $model = AiModel::query()->lockForUpdate()->find($modelId);

            if (! $model || $model->status !== 'active' || $model->paused_at || $model->archived_at) {
                return collect();
            }

            if ($model->cooldown_until?->isFuture()) {
                return collect();
            }

            $activeCount = GenerationAttempt::query()
                ->where('ai_model_id', $model->id)
                ->whereIn('status', ['reserved', 'dispatched', 'processing'])
                ->count();
            $freeSlots = max(0, max(1, (int) $model->concurrency_limit) - $activeCount);

            if ($freeSlots === 0) {
                return collect();
            }

            $attempts = GenerationAttempt::query()
                ->where('ai_model_id', $model->id)
                ->where('status', 'queued')
                ->where(function ($query): void {
                    $query->whereNull('available_at')->orWhere('available_at', '<=', now());
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($freeSlots)
                ->lockForUpdate()
                ->get();

            foreach ($attempts as $attempt) {
                $attempt->forceFill([
                    'status' => 'reserved',
                    'reserved_at' => now(),
                    'dispatched_at' => null,
                ])->save();
            }

            return $attempts;
        });

        foreach ($reserved as $attempt) {
            $this->enqueueReservedAttempt($attempt);
        }

        return $reserved->count();
    }

    /**
     * Dispatch every model that currently has logical queued work.
     *
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    public function dispatchAllAvailable(): int
    {
        $modelIds = GenerationAttempt::query()
            ->where('status', 'queued')
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->distinct()
            ->pluck('ai_model_id');

        return $modelIds->sum(fn (mixed $modelId): int => $this->dispatchAvailableForModel((int) $modelId));
    }

    /**
     * Recover attempts that were reserved but not handed to a worker.
     *
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    public function recoverStaleReservations(): int
    {
        $reservationCutoff = now()->subSeconds(max(60, (int) config('hairvision.generation.reservation_stale_seconds', 300)));
        $reviewCutoff = now()->subSeconds(max(1200, (int) config('hairvision.generation.processing_review_seconds', 1800)));
        $modelIds = DB::transaction(function () use ($reservationCutoff, $reviewCutoff): Collection {
            $safeToRetry = GenerationAttempt::query()
                ->whereNull('provider_call_started_at')
                ->where(function ($query) use ($reservationCutoff): void {
                    $query
                        ->where(fn ($query) => $query
                            ->where('status', 'reserved')
                            ->where('reserved_at', '<=', $reservationCutoff))
                        ->orWhere(fn ($query) => $query
                            ->where('status', 'dispatched')
                            ->where('dispatched_at', '<=', $reservationCutoff))
                        ->orWhere(fn ($query) => $query
                            ->where('status', 'processing')
                            ->where('updated_at', '<=', $reservationCutoff));
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $requiresReview = GenerationAttempt::query()
                ->where('status', 'processing')
                ->whereNotNull('provider_call_started_at')
                ->where('provider_call_started_at', '<=', $reviewCutoff)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $modelIds = $safeToRetry
                ->concat($requiresReview)
                ->pluck('ai_model_id')
                ->unique()
                ->values();

            foreach ($safeToRetry as $attempt) {
                $attempt->forceFill([
                    'status' => 'queued',
                    'reserved_at' => null,
                    'dispatched_at' => null,
                ])->save();
                $attempt->generationRequest?->forceFill([
                    'status' => 'queued',
                    'safe_error_code' => null,
                    'safe_error_message' => null,
                ])->save();
            }

            foreach ($requiresReview as $attempt) {
                $attempt->forceFill([
                    'status' => 'requires_review',
                    'failed_at' => now(),
                    'safe_failure_class' => 'provider_outcome_requires_review',
                    'safe_error_message' => __('ai_models.errors.provider_failed'),
                ])->save();
                $attempt->generationRequest?->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'safe_error_code' => 'provider_outcome_requires_review',
                    'safe_error_message' => __('ai_models.errors.provider_failed'),
                ])->save();
            }

            return $modelIds;
        });

        foreach ($modelIds as $modelId) {
            $this->dispatchAvailableForModel((int) $modelId);
        }

        return $modelIds->count();
    }

    /**
     * Atomically let at most one transported job claim an attempt.
     *
     * @param  GenerationAttempt  $attempt  The attempt value.
     * @return GenerationAttempt|null Resolved operation result.
     *
     * @since 2.0.0
     */
    public function claimForProcessing(GenerationAttempt $attempt): ?GenerationAttempt
    {
        return DB::transaction(function () use ($attempt): ?GenerationAttempt {
            $locked = GenerationAttempt::query()
                ->with('generationRequest')
                ->lockForUpdate()
                ->find($attempt->id);

            if (! $locked || ! in_array($locked->status, ['reserved', 'dispatched'], true)) {
                return null;
            }

            if ($locked->generationRequest?->status === 'cancelled') {
                $locked->forceFill(['status' => 'cancelled'])->save();

                return null;
            }

            $locked->forceFill([
                'status' => 'processing',
                'safe_failure_class' => null,
                'safe_error_message' => null,
            ])->save();

            $locked->generationRequest?->forceFill([
                'status' => 'processing',
                'safe_error_code' => null,
                'safe_error_message' => null,
            ])->save();

            return $locked->refresh();
        });
    }

    /**
     * Pause new dispatches for a provider-requested cooldown window.
     *
     * @param  int  $modelId  The model id value.
     * @param  int  $seconds  The seconds value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function coolDownModel(int $modelId, int $seconds): void
    {
        DB::transaction(function () use ($modelId, $seconds): void {
            $model = AiModel::query()->lockForUpdate()->find($modelId);

            if (! $model) {
                return;
            }

            $until = now()->addSeconds(max(1, $seconds));

            if (! $model->cooldown_until || $model->cooldown_until->lt($until)) {
                $model->forceFill(['cooldown_until' => $until])->save();
            }
        });
    }

    /**
     * Apply a bounded database-backed circuit cooldown after repeated provider failures.
     *
     * @param  int  $modelId  The model id value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function recordProviderFailure(int $modelId): void
    {
        DB::transaction(function () use ($modelId): void {
            $model = AiModel::query()->lockForUpdate()->find($modelId);

            if (! $model) {
                return;
            }

            $failures = $model->consecutive_failures + 1;
            $threshold = max(1, (int) config('hairvision.generation.provider_failure_threshold', 5));
            $attributes = ['consecutive_failures' => $failures];

            if ($failures >= $threshold) {
                $attributes['cooldown_until'] = now()->addSeconds(
                    max(60, (int) config('hairvision.generation.provider_failure_cooldown_seconds', 300))
                );
            }

            $model->forceFill($attributes)->save();
        });
    }

    /**
     * Close the transient circuit after a successful provider execution.
     *
     * @param  int  $modelId  The model id value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function recordProviderSuccess(int $modelId): void
    {
        AiModel::query()->whereKey($modelId)->update([
            'consecutive_failures' => 0,
            'cooldown_until' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Execute the enqueue reserved attempt operation.
     *
     * @param  GenerationAttempt  $attempt  The attempt value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function enqueueReservedAttempt(GenerationAttempt $attempt): void
    {
        try {
            dispatch(
                (new ProcessGenerationAttempt($attempt->refresh()))
                    ->onConnection((string) config('hairvision.generation.queue_connection', config('queue.default')))
                    ->onQueue((string) config('hairvision.generation.queue_name', 'ai-generation'))
            );

            GenerationAttempt::query()
                ->whereKey($attempt->id)
                ->where('status', 'reserved')
                ->update([
                    'status' => 'dispatched',
                    'dispatched_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            GenerationAttempt::query()
                ->whereKey($attempt->id)
                ->where('status', 'reserved')
                ->update([
                    'status' => 'queued',
                    'reserved_at' => null,
                    'updated_at' => now(),
                ]);

            Log::warning('Generation attempt could not be added to the database queue.', [
                'generation_attempt_id' => $attempt->id,
                'generation_attempt_uuid' => $attempt->uuid,
                'ai_model_id' => $attempt->ai_model_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
