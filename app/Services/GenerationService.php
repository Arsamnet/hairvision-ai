<?php

namespace App\Services;

use App\Jobs\ProcessGenerationJob;
use App\Models\AiModelAccess;
use App\Models\AiProvider;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\TenantAiSetting;
use App\Models\Upload;
use App\Services\AI\AiModelManager;
use App\Services\Billing\ServiceEntitlementService;
use App\Services\Generation\GenerationStageCatalog;
use App\Support\DebugStepLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Creates generation groups and dispatches staged jobs.
 *
 * @since 1.0.0
 */
class GenerationService
{
    public function __construct(
        private readonly AiModelManager $aiModels,
        private readonly ServiceEntitlementService $entitlements,
        private readonly GenerationStageCatalog $stages,
    ) {}

    /**
     * Create or reuse a generation group for an upload.
     */
    public function createForUpload(Upload $upload, ?Request $request = null): GenerationGroup
    {
        $scope = 'generation.service.create_for_upload';

        $this->debugStep($request, $scope, 'Request received', [
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'upload_uuid' => $upload->uuid,
        ]);

        $stageKeys = $this->stages->keys();

        $this->debugStep($request, $scope, 'Generation stages resolved', [
            'tenant_id' => $upload->tenant_id,
            'upload_id' => $upload->id,
            'stages_count' => count($stageKeys),
            'stage_keys' => $stageKeys,
        ]);

        $existing = $upload->generationGroup()->with('jobs')->first();

        if ($existing) {
            $this->debugStep($request, $scope, 'Existing generation group found', [
                'generation_group_id' => $existing->id,
                'generation_group_uuid' => $existing->uuid,
                'status' => $existing->status,
                'jobs_count' => $existing->jobs->count(),
            ]);

            $this->dispatchNextStage($existing, $request);

            $existing->refresh();

            $this->debugStep($request, $scope, 'Existing generation group refreshed', [
                'generation_group_id' => $existing->id,
                'generation_group_uuid' => $existing->uuid,
                'status' => $existing->status,
            ]);

            return $existing;
        }

        $this->debugStep($request, $scope, 'Checking service entitlement', [
            'tenant_id' => $upload->tenant_id,
            'service_key' => 'hair_transplant',
            'required_units' => count($stageKeys),
        ]);

        $this->entitlements->assertCanUse($upload->tenant, 'hair_transplant', count($stageKeys));

        $this->debugStep($request, $scope, 'Service entitlement granted', [
            'tenant_id' => $upload->tenant_id,
            'service_key' => 'hair_transplant',
        ]);

        $modelAccess = $this->aiModels->resolveForClinic($upload->tenant, 'hair_transplant');

        $this->debugStep($request, $scope, 'AI model access resolved', [
            'tenant_id' => $upload->tenant_id,
            'ai_model_access_id' => $modelAccess->id,
            'model_key' => $modelAccess->model_key,
        ]);

        $group = GenerationGroup::create([
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        $this->debugStep($request, $scope, 'Generation group created', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'tenant_id' => $group->tenant_id,
            'lead_id' => $group->lead_id,
            'status' => $group->status,
        ]);

        $provider = $this->resolveProvider($upload->tenant_id, $modelAccess, $request);

        $this->debugStep($request, $scope, 'AI provider resolved', [
            'provider_id' => $provider->id,
            'provider_code' => $provider->code,
            'provider_type' => $provider->type,
        ]);

        foreach ($stageKeys as $stageKey) {
            $prompt = $this->stages->promptFor($modelAccess, $stageKey);

            $job = GenerationJob::create([
                'generation_group_id' => $group->id,
                'tenant_id' => $upload->tenant_id,
                'lead_id' => $upload->lead_id,
                'upload_id' => $upload->id,
                'provider_id' => $provider->id,
                'ai_model_access_id' => $modelAccess->id,
                'period' => $stageKey,
                'status' => 'pending',
                'model_name' => $modelAccess->model_key,
                'prompt' => $prompt,
            ]);

            $this->debugStep($request, $scope, 'Generation job created', [
                'generation_group_id' => $group->id,
                'generation_job_id' => $job->id,
                'generation_job_uuid' => $job->uuid,
                'period' => $stageKey,
                'status' => $job->status,
                'model_name' => $job->model_name,
                'has_prompt' => $prompt !== '',
                'prompt_length' => strlen($prompt),
            ]);
        }

        $this->dispatchNextStage($group, $request);

        $group->refresh();

        $this->debugStep($request, $scope, 'Generation group refreshed after dispatch', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'status' => $group->status,
        ]);

        return $group;
    }

    /**
     * Dispatch the next pending stage when possible.
     */
    public function dispatchNextStage(GenerationGroup $group, ?Request $request = null): void
    {
        $scope = 'generation.service.dispatch_next_stage';

        $this->debugStep($request, $scope, 'Request received', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'status' => $group->status,
        ]);

        $group->load('jobs.aiModelAccess');

        $this->debugStep($request, $scope, 'Generation jobs loaded', [
            'generation_group_id' => $group->id,
            'jobs_count' => $group->jobs->count(),
            'pending_count' => $group->jobs->where('status', 'pending')->count(),
            'processing_count' => $group->jobs->where('status', 'processing')->count(),
            'completed_count' => $group->jobs->where('status', 'completed')->count(),
            'failed_count' => $group->jobs->where('status', 'failed')->count(),
        ]);

        if ($group->status === 'completed') {
            $this->debugStep($request, $scope, 'Group already completed', [
                'generation_group_id' => $group->id,
            ]);

            return;
        }

        if ($group->jobs->contains('status', 'failed')) {
            $this->debugStep($request, $scope, 'Dispatch skipped because a job failed', [
                'generation_group_id' => $group->id,
                'failed_count' => $group->jobs->where('status', 'failed')->count(),
            ]);

            return;
        }

        if ($group->jobs->contains('status', 'processing')) {
            $group->update(['status' => 'processing']);

            $this->debugStep($request, $scope, 'Group marked as processing because a job is active', [
                'generation_group_id' => $group->id,
            ]);

            return;
        }

        $pendingJobs = $group->jobs
            ->where('status', 'pending')
            ->sortBy(fn (GenerationJob $job): int => $this->stages->order($job->period));

        if ($this->usesConcurrentStageDispatch($group) && $pendingJobs->isNotEmpty()) {
            $this->dispatchPendingStages($group, $pendingJobs, $request);

            return;
        }

        $nextJob = $pendingJobs->first();

        if (! $nextJob) {
            $this->debugStep($request, $scope, 'No pending job found', [
                'generation_group_id' => $group->id,
                'all_completed' => $group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed'),
            ]);

            if ($group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed')) {
                $group->update(['status' => 'completed']);

                $this->debugStep($request, $scope, 'Group marked as completed', [
                    'generation_group_id' => $group->id,
                    'generation_group_uuid' => $group->uuid,
                ]);
            }

            return;
        }

        $this->debugStep($request, $scope, 'Next job selected', [
            'generation_group_id' => $group->id,
            'generation_job_id' => $nextJob->id,
            'generation_job_uuid' => $nextJob->uuid,
            'period' => $nextJob->period,
            'order' => $this->stages->order($nextJob->period),
        ]);

        $group->update(['status' => 'processing']);
        $nextJob->update(['status' => 'processing']);

        $this->debugStep($request, $scope, 'Group and job marked as processing', [
            'generation_group_id' => $group->id,
            'generation_job_id' => $nextJob->id,
        ]);

        dispatch(new ProcessGenerationJob($nextJob->refresh()));

        $this->debugStep($request, $scope, 'Processing job dispatched', [
            'generation_group_id' => $group->id,
            'generation_job_id' => $nextJob->id,
            'generation_job_uuid' => $nextJob->uuid,
            'period' => $nextJob->period,
        ]);
    }

    /**
     * Dispatch every pending stage together for providers that benefit from parallel requests.
     *
     * @param  Collection<int, GenerationJob>  $pendingJobs
     */
    private function dispatchPendingStages(GenerationGroup $group, Collection $pendingJobs, ?Request $request = null): void
    {
        $scope = 'generation.service.dispatch_pending_stages';

        $this->debugStep($request, $scope, 'Concurrent stage dispatch selected', [
            'generation_group_id' => $group->id,
            'generation_group_uuid' => $group->uuid,
            'jobs_count' => $pendingJobs->count(),
            'periods' => $pendingJobs->pluck('period')->values()->all(),
        ]);

        $group->update(['status' => 'processing']);

        $pendingJobs->each(function (GenerationJob $job): void {
            $job->update(['status' => 'processing']);
        });

        $pendingJobs->each(function (GenerationJob $job) use ($request, $scope, $group): void {
            dispatch(new ProcessGenerationJob($job->refresh()));

            $this->debugStep($request, $scope, 'Processing job dispatched', [
                'generation_group_id' => $group->id,
                'generation_job_id' => $job->id,
                'generation_job_uuid' => $job->uuid,
                'period' => $job->period,
            ]);
        });
    }

    /**
     * Determine whether all pending stages should be sent to the queue together.
     */
    private function usesConcurrentStageDispatch(GenerationGroup $group): bool
    {
        return $group->jobs->contains(function (GenerationJob $job): bool {
            return $job->aiModelAccess?->model_key === 'gapgpt'
                || $job->model_name === 'gapgpt';
        });
    }

    /**
     * Resolve the provider record used by generation jobs.
     */
    private function resolveProvider(int $tenantId, ?AiModelAccess $modelAccess = null, ?Request $request = null): AiProvider
    {
        $scope = 'generation.service.resolve_provider';

        $this->debugStep($request, $scope, 'Request received', [
            'tenant_id' => $tenantId,
            'has_model_access' => $modelAccess !== null,
            'model_key' => $modelAccess?->model_key,
        ]);

        if ($modelAccess) {
            $config = config('ai-models.models.'.$modelAccess->model_key, []);

            $provider = AiProvider::query()->firstOrCreate(
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

            $this->debugStep($request, $scope, 'Provider resolved from model access', [
                'tenant_id' => $tenantId,
                'provider_id' => $provider->id,
                'provider_code' => $provider->code,
                'provider_type' => $provider->type,
                'is_active' => $provider->is_active,
            ]);

            return $provider;
        }

        $setting = TenantAiSetting::query()
            ->where('tenant_id', '=', $tenantId)
            ->where('is_default', '=', true)
            ->with('provider')
            ->first();

        $this->debugStep($request, $scope, 'Tenant AI setting lookup completed', [
            'tenant_id' => $tenantId,
            'setting_found' => $setting !== null,
            'provider_id' => $setting?->provider?->id,
            'provider_code' => $setting?->provider?->code,
        ]);

        if ($setting?->provider) {
            return $setting->provider;
        }

        $default = config('hairvision.default_ai_provider');

        $this->debugStep($request, $scope, 'Resolving default provider', [
            'tenant_id' => $tenantId,
            'default_provider_code' => $default,
        ]);

        $provider = AiProvider::query()
            ->where('code', '=', $default)
            ->firstOrFail();

        $this->debugStep($request, $scope, 'Default provider resolved', [
            'tenant_id' => $tenantId,
            'provider_id' => $provider->id,
            'provider_code' => $provider->code,
            'provider_type' => $provider->type,
        ]);

        return $provider;
    }

    /**
     * Write a debug step when a request is available.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugStep(?Request $request, string $scope, string $message, array $context = []): void
    {
        if (! $request) {
            return;
        }

        DebugStepLogger::step($request, $scope, $message, $context);
    }
}
