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

/**
 * Creates generation groups and dispatches staged jobs.
 *
 * The service remains provider-aware only at the provider-code level needed for
 * job metadata. Actual provider execution stays inside queued jobs.
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
     * Create one generation group and three staged jobs for an upload.
     *
     * Side effects: writes generation rows and dispatches the next pending
     * stage for queued processing.
     */
    public function createForUpload(Upload $upload): GenerationGroup
    {
        $stageKeys = $this->stages->keys();

        $existing = $upload->generationGroup()->with('jobs')->first();

        if ($existing) {
            $this->dispatchNextStage($existing);

            return $existing->refresh();
        }

        $this->entitlements->assertCanUse($upload->tenant, 'hair_transplant', count($stageKeys));

        $modelAccess = $this->aiModels->resolveForClinic($upload->tenant, 'hair_transplant');

        $group = GenerationGroup::create([
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        $provider = $this->resolveProvider($upload->tenant_id, $modelAccess);

        foreach ($stageKeys as $stageKey) {
            GenerationJob::create([
                'generation_group_id' => $group->id,
                'tenant_id' => $upload->tenant_id,
                'lead_id' => $upload->lead_id,
                'upload_id' => $upload->id,
                'provider_id' => $provider->id,
                'ai_model_access_id' => $modelAccess->id,
                'period' => $stageKey,
                'status' => 'pending',
                'model_name' => $modelAccess->model_key,
                'prompt' => $this->stages->promptFor($modelAccess, $stageKey),
            ]);
        }

        $this->dispatchNextStage($group);

        return $group->refresh();
    }

    public function dispatchNextStage(GenerationGroup $group): void
    {
        $group->load('jobs');

        if ($group->status === 'completed' || $group->jobs->contains('status', 'failed')) {
            return;
        }

        if ($group->jobs->contains('status', 'processing')) {
            $group->update(['status' => 'processing']);

            return;
        }

        $nextJob = $group->jobs
            ->where('status', 'pending')
            ->sortBy(fn (GenerationJob $job): int => $this->stages->order($job->period))
            ->first();

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
     * Resolve the active provider record for a tenant.
     *
     * @param  int  $tenantId  Tenant id used for tenant default provider lookup.
     * @return AiProvider Provider database record attached to created jobs.
     */
    private function resolveProvider(int $tenantId, ?AiModelAccess $modelAccess = null): AiProvider
    {
        if ($modelAccess) {
            $config = config('ai-models.models.'.$modelAccess->model_key, []);

            return AiProvider::query()->firstOrCreate(
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

        return AiProvider::query()->where('code', '=', $default)->firstOrFail();
    }
}
