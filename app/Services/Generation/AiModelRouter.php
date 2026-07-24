<?php

namespace App\Services\Generation;

use App\Models\AiModel;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationAttempt;
use App\Models\Tenant;
use App\Services\Visitor\PublicApplicationAvailabilityService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Selects the least-loaded eligible model for a category output.
 *
 * @since 2.0.0
 */
class AiModelRouter
{
    /**
     * Create a new ai model router instance.
     *
     * @param  PublicApplicationAvailabilityService  $availability  The availability value.
     *
     * @since 2.0.0
     */
    public function __construct(private readonly PublicApplicationAvailabilityService $availability) {}

    /**
     * Execute the select for operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  AiModelCategory  $category  The category value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @return AiModel Resolved operation result.
     *
     * @since 2.0.0
     */
    public function selectFor(Tenant $tenant, AiModelCategory $category, AiModelCategoryOutput $output): AiModel
    {
        return DB::transaction(function () use ($tenant, $category, $output): AiModel {
            $models = $this->availability
                ->eligibleModelsForOutput($tenant, $category, $output)
                ->withCount([
                    'attempts as non_terminal_attempts_count' => fn ($query) => $query->whereIn('status', GenerationAttempt::NON_TERMINAL_STATUSES),
                    'attempts as active_attempts_count' => fn ($query) => $query->whereIn('status', ['reserved', 'dispatched', 'processing']),
                ])
                ->orderBy('priority')
                ->orderByRaw('last_assigned_at is null desc')
                ->orderBy('last_assigned_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($models->isEmpty()) {
                throw new HttpException(503, __('ai_models.errors.no_eligible_model'));
            }

            $withCapacity = $models
                ->filter(fn (AiModel $model): bool => ! $model->cooldown_until?->isFuture()
                    && (int) $model->active_attempts_count < max(1, (int) $model->concurrency_limit))
                ->sortBy([
                    ['priority', 'asc'],
                    ['non_terminal_attempts_count', 'asc'],
                    ['last_assigned_at', 'asc'],
                    ['id', 'asc'],
                ]);

            $selected = ($withCapacity->first() ?? $models->sortBy([
                [fn (AiModel $model): int => $model->cooldown_until?->isFuture() ? 1 : 0, 'asc'],
                ['non_terminal_attempts_count', 'asc'],
                ['priority', 'asc'],
                ['last_assigned_at', 'asc'],
                ['id', 'asc'],
            ])->first());

            if (! $selected instanceof AiModel) {
                throw new HttpException(503, __('ai_models.errors.no_eligible_model'));
            }

            $selected->forceFill(['last_assigned_at' => now()])->save();

            return $selected->refresh();
        });
    }
}
