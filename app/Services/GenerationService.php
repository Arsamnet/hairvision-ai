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

/**
 * Creates generation groups and dispatches period jobs.
 *
 * The service remains provider-aware only at the provider-code level needed for
 * local debug execution. Actual provider execution stays inside queued jobs.
 *
 * @since 1.0.0
 */
class GenerationService
{
    public function __construct(
        private readonly AiModelManager $aiModels,
        private readonly ServiceEntitlementService $entitlements,
    ) {}

    /**
     * Create one generation group and three period jobs for an upload.
     *
     * Side effects: writes generation rows and dispatches or synchronously runs
     * processing jobs when debug sample mode is enabled.
     */
    public function createForUpload(Upload $upload): GenerationGroup
    {
        $periods = ['3_months', '6_months', '12_months'];
        $this->entitlements->assertCanUse($upload->tenant, 'hair_transplant', count($periods));

        $modelAccess = $this->aiModels->resolveForClinic($upload->tenant, 'hair_transplant');

        $group = GenerationGroup::create([
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        $provider = $this->resolveProvider($upload->tenant_id, $modelAccess);

        foreach ($periods as $period) {
            $job = GenerationJob::create([
                'generation_group_id' => $group->id,
                'tenant_id' => $upload->tenant_id,
                'lead_id' => $upload->lead_id,
                'upload_id' => $upload->id,
                'provider_id' => $provider->id,
                'ai_model_access_id' => $modelAccess->id,
                'period' => $period,
                'status' => 'pending',
                'model_name' => $modelAccess->model_key,
                'prompt' => $modelAccess->prompt ?: 'Sample hair growth preview for '.$period,
            ]);

            if (config('hairvision.debug_fast_samples') && $provider->code === 'sample_image') {
                (new ProcessGenerationJob($job))->handle();
            } else {
                dispatch(new ProcessGenerationJob($job));
            }
        }

        return $group;
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
            return AiProvider::query()->where('code', '=', $modelAccess->model_key)->firstOrFail();
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
