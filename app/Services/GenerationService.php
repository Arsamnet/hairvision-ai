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
use Illuminate\Http\Request;

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

        $stageKeys = $this->stages->keys();

        $existing = $upload->generationGroup()->with('jobs')->first();

        if ($existing) {
            $this->dispatchNextStage($existing, $request);

            $existing->refresh();

            return $existing;
        }

        $this->entitlements->assertCanUse($upload->tenant, 'hair_transplant', count($stageKeys));

        $modelAccess = $this->aiModels->resolveForClinic($upload->tenant, 'hair_transplant');

        $group = GenerationGroup::create([
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        $provider = $this->resolveProvider($upload->tenant_id, $modelAccess, $request);

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

        }

        $this->dispatchNextStage($group, $request);

        $group->refresh();

        return $group;
    }

    /**
     * Dispatch the next pending stage when possible.
     */
    public function dispatchNextStage(GenerationGroup $group, ?Request $request = null): void
    {
        $scope = 'generation.service.dispatch_next_stage';

        $group->load('jobs.aiModelAccess');

        if ($group->status === 'completed') {
            return;
        }

        if ($group->jobs->contains('status', 'failed')) {
            return;
        }

        if ($group->jobs->contains('status', 'processing')) {
            $group->update(['status' => 'processing']);

            return;
        }

        $pendingJobs = $group->jobs
            ->where('status', 'pending')
            ->sortBy(fn (GenerationJob $job): int => $this->stages->order($job->period));

        $nextJob = $pendingJobs->first();

        if (! $nextJob) {
            if ($group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed')) {
                $group->update(['status' => 'completed']);

            }

            return;
        }

        $group->update(['status' => 'processing']);
        $nextJob->update(['status' => 'processing']);

        dispatch(new ProcessGenerationJob($nextJob->refresh()));

    }
    /**
     * Resolve the provider record used by generation jobs.
     */
    private function resolveProvider(int $tenantId, ?AiModelAccess $modelAccess = null, ?Request $request = null): AiProvider
    {
        $scope = 'generation.service.resolve_provider';

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

            return $provider;
        }

        $setting = TenantAiSetting::query()
            ->where('tenant_id', '=', $tenantId)
            ->where('is_default', '=', true)
            ->with('provider')
            ->first();

        if ($setting?->provider) {
            return $setting->provider;
        }

        $default = config('hairvision.default_ai_provider');

        $provider = AiProvider::query()
            ->where('code', '=', $default)
            ->firstOrFail();

        return $provider;
    }

    /**
     * Write a debug step when a request is available.
     *
     * @param  array<string, mixed>  $context
     */
}
