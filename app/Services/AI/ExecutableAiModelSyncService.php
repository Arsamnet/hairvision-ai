<?php

namespace App\Services\AI;

use App\Models\AiModel;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\User;
use App\Services\Generation\GenerationStageCatalog;
use Illuminate\Support\Collection;

/**
 * Keeps legacy clinic model access records executable by the v2 output flow.
 *
 * @since 2.0.0
 */
class ExecutableAiModelSyncService
{
    /**
     * Create a new executable ai model sync service instance.
     *
     * @param  GenerationStageCatalog  $stageCatalog  The stage catalog value.
     *
     * @since 2.0.0
     */
    public function __construct(private readonly GenerationStageCatalog $stageCatalog) {}

    /**
     * Synchronize sync.
     *
     * @param  AiModelAccess  $access  The access value.
     * @param  User|null  $actor  Authenticated user performing the operation.
     * @return AiModel|null Resolved operation result.
     *
     * @since 2.0.0
     */
    public function sync(AiModelAccess $access, ?User $actor = null): ?AiModel
    {
        $category = AiModelCategory::query()
            ->where('code', $access->usage_category_key)
            ->with(['outputs' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->first();

        if (! $category || ! $this->typeExists($access->model_key)) {
            return null;
        }

        $model = AiModel::query()->firstOrNew([
            'legacy_ai_model_access_id' => $access->id,
        ]);

        $isActive = (bool) $access->is_active;

        $model->fill([
            'ai_model_category_id' => $category->id,
            'type' => $access->model_key,
            'name' => $access->name,
            'status' => $isActive ? 'active' : 'paused',
            'priority' => $access->priority ?? 100,
            'concurrency_limit' => $this->concurrencyLimitFor($access),
            'settings' => $access->settings ?? [],
            'credentials' => [],
            'health_status' => $model->exists ? $model->health_status : 'unknown',
            'paused_at' => $isActive ? null : ($model->paused_at ?? now()),
            'archived_at' => null,
            'updated_by' => $actor?->id ?? $access->updated_by,
        ]);

        if (! $model->exists) {
            $model->created_by = $actor?->id ?? $access->created_by;
        }

        $model->save();

        $this->syncOutputConfigs($model, $access, $category->outputs);

        return $model->refresh();
    }

    /**
     * Synchronize for category.
     *
     * @param  AiModelCategory  $category  The category value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function syncForCategory(AiModelCategory $category): void
    {
        AiModelAccess::query()
            ->where('usage_category_key', $category->code)
            ->each(fn (AiModelAccess $access): ?AiModel => $this->sync($access));
    }

    /**
     * Execute the type exists operation.
     *
     * @param  string  $type  The type value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    private function typeExists(string $type): bool
    {
        return array_key_exists($type, config('ai-models.types', []));
    }

    /**
     * Execute the concurrency limit for operation.
     *
     * @param  AiModelAccess  $access  The access value.
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    private function concurrencyLimitFor(AiModelAccess $access): int
    {
        return max(1, (int) data_get(
            $access->settings,
            'concurrency_limit',
            data_get(config('ai-models.types.'.$access->model_key), 'defaults.concurrency_limit', 1)
        ));
    }

    /**
     * Synchronize output configs.
     *
     * @param  Collection<int, AiModelCategoryOutput>  $outputs
     * @param  AiModel  $model  The model value.
     * @param  AiModelAccess  $access  The access value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function syncOutputConfigs(AiModel $model, AiModelAccess $access, Collection $outputs): void
    {
        $outputs->each(function (AiModelCategoryOutput $output) use ($model, $access): void {
            $model->outputConfigs()->updateOrCreate(
                [
                    'ai_model_category_output_id' => $output->id,
                ],
                [
                    'is_active' => (bool) $output->is_active,
                    'configuration' => $this->configurationFor($access, $output),
                ]
            );
        });
    }

    /**
     * Execute the configuration for operation.
     *
     * @param  AiModelAccess  $access  The access value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    private function configurationFor(AiModelAccess $access, AiModelCategoryOutput $output): array
    {
        return match ($access->model_key) {
            'sample_image' => [
                'sample_path' => $this->samplePathFor($access, $output),
            ],
            'gapgpt', 'openai' => [
                'prompt' => $output->prompt,
            ],
            default => [],
        };
    }

    /**
     * Execute the sample path for operation.
     *
     * @param  AiModelAccess  $access  The access value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @return string Resolved string value.
     *
     * @since 2.0.0
     */
    private function samplePathFor(AiModelAccess $access, AiModelCategoryOutput $output): string
    {
        return data_get($access->settings, 'demo_images.'.$output->code)
            ?? data_get($access->settings, 'sample_path')
            ?? $this->stageCatalog->sampleImagePath($output->code)
            ?? config('hairvision.sample_images.'.$output->code)
            ?? 'sample-results/'.$output->code.'.jpg';
    }
}
