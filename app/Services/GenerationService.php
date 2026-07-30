<?php

namespace App\Services;

use App\Jobs\ProcessGenerationJob;
use App\Models\AiModelAccess;
use App\Models\AiProvider;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Upload;
use App\Services\AI\AiModelManager;
use App\Services\Billing\ServiceEntitlementService;
use App\Services\Generation\GenerationStageCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates generation groups and dispatches staged jobs.
 *
 * @since 1.0.0
 */
class GenerationService
{
    /**
     * Create a new generation service instance.
     *
     * @param  AiModelManager  $aiModels  The ai models value.
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     * @param  GenerationStageCatalog  $stages  The stages value.
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly AiModelManager $aiModels,
        private readonly ServiceEntitlementService $entitlements,
        private readonly GenerationStageCatalog $stages,
    ) {}

    /**
     * Create or reuse a generation group for an upload.
     *
     * @param  Upload  $upload  The upload value.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  Request|null  $request  Incoming HTTP request.
     * @return GenerationGroup Resolved operation result.
     *
     * @since 1.0.0
     */
    public function createForUpload(
        Upload $upload,
        string $usageCategoryKey = 'hair_transplant',
        ?Request $request = null
    ): GenerationGroup {
        $scope = 'generation.service.create_for_upload';

        $group = DB::transaction(function () use ($upload, $usageCategoryKey): GenerationGroup {
            $lockedUpload = Upload::query()->lockForUpdate()->findOrFail($upload->id);
            $existing = $lockedUpload->generationGroup()->with('jobs')->first();

            if ($existing) {
                return $existing;
            }

            $stageKeys = $this->stages->keys($usageCategoryKey);
            $this->entitlements->assertCanUse($lockedUpload->tenant, $usageCategoryKey, count($stageKeys));
            $modelAccess = $this->aiModels->resolveForClinic($lockedUpload->tenant, $usageCategoryKey);
            $queuedAt = now();
            $group = GenerationGroup::create([
                'tenant_id' => $lockedUpload->tenant_id,
                'lead_id' => $lockedUpload->lead_id,
                'upload_id' => $lockedUpload->id,
                'status' => 'queued',
                'queued_at' => $queuedAt,
            ]);
            $provider = $this->resolveProvider($modelAccess);

            foreach ($stageKeys as $stageKey) {
                GenerationJob::create([
                    'generation_group_id' => $group->id,
                    'tenant_id' => $lockedUpload->tenant_id,
                    'lead_id' => $lockedUpload->lead_id,
                    'upload_id' => $lockedUpload->id,
                    'provider_id' => $provider->id,
                    'ai_model_access_id' => $modelAccess->id,
                    'period' => $stageKey,
                    'status' => 'queued',
                    'queued_at' => $queuedAt,
                    'model_name' => $modelAccess->model_key,
                    'prompt' => $this->stages->promptFor($usageCategoryKey, $stageKey),
                ]);
            }

            return $group->load('jobs');
        });

        $this->dispatchNextStage($group, $request);

        $group->refresh();

        return $group;
    }

    /**
     * Dispatch the next pending stage when possible.
     *
     * @param  GenerationGroup  $group  The group value.
     * @param  Request|null  $request  Incoming HTTP request.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function dispatchNextStage(GenerationGroup $group, ?Request $request = null): void
    {
        $scope = 'generation.service.dispatch_next_stage';

        $group->load('jobs.aiModelAccess');

        if (in_array($group->status, ['completed', 'cancelled'], true)) {
            return;
        }

        $jobIds = DB::transaction(function () use ($group): array {
            $locked = GenerationGroup::query()->lockForUpdate()->findOrFail($group->id);
            $jobs = $locked->jobs()
                ->whereIn('status', ['pending', 'queued', 'retrying'])
                ->whereNull('dispatched_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($jobs as $job) {
                $job->forceFill([
                    'status' => 'queued',
                    'queued_at' => $job->queued_at ?? now(),
                    'dispatched_at' => now(),
                ])->save();
            }

            $locked->forceFill([
                'status' => 'queued',
                'queued_at' => $locked->queued_at ?? now(),
            ])->save();

            return $jobs->pluck('id')->all();
        });

        foreach ($jobIds as $jobId) {
            $job = GenerationJob::query()->findOrFail($jobId);

            try {
                dispatch((new ProcessGenerationJob($job))
                    ->onConnection((string) config('hairvision.image_queue.connection', config('queue.default')))
                    ->onQueue((string) config('hairvision.image_queue.name', 'ai-generation')))
                    ->afterCommit();
            } catch (Throwable $exception) {
                $job->forceFill(['dispatched_at' => null])->save();

                throw $exception;
            }
        }

    }

    /**
     * Resolve the provider record used by generation jobs.
     *
     * @param  AiModelAccess  $modelAccess  The model access value.
     * @return AiProvider Resolved operation result.
     *
     * @since 1.0.0
     */
    private function resolveProvider(AiModelAccess $modelAccess): AiProvider
    {
        $scope = 'generation.service.resolve_provider';

        $config = config('ai-models.models.'.$modelAccess->model_key, []);

        $provider = AiProvider::query()
            ->firstOrCreate(
                ['code' => $modelAccess->model_key],
                [
                    'name' => is_string($config['display_name_key'] ?? null)
                        ? __($config['display_name_key'])
                        : $modelAccess->model_key,
                    'type' => ($config['is_demo'] ?? false) ? 'local' : 'external',
                    'base_url' => $config['endpoint'] ?? null,
                    'auth_type' => empty($config['required_credentials'] ?? []) ? null : 'bearer',
                    'is_active' => true,
                    'supports_async' => false,
                    'default_model' => $config['default_model'] ?? null,
                ]
            );

        return $provider;
    }

    /**
     * Write a debug step when a request is available.
     *
     * @param  array<string, mixed>  $context
     */
}
