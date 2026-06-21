<?php

namespace App\Services;

use App\Jobs\ProcessGenerationJob;
use App\Models\AiProvider;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Upload;

class GenerationService
{
    public function createForUpload(Upload $upload): GenerationGroup
    {
        $group = GenerationGroup::create([
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        $provider = $this->resolveProvider($upload->tenant_id);
        $periods = ['3_months', '6_months', '12_months'];

        foreach ($periods as $period) {
            $job = GenerationJob::create([
                'generation_group_id' => $group->id,
                'tenant_id' => $upload->tenant_id,
                'lead_id' => $upload->lead_id,
                'upload_id' => $upload->id,
                'provider_id' => $provider->id,
                'period' => $period,
                'status' => 'pending',
                'prompt' => 'Sample hair growth preview for '.$period,
            ]);

            // In debug mode allow fast synchronous processing when using sample images
            if (config('hairvision.debug_fast_samples') && $provider->code === 'sample_image') {
                // run synchronously to reduce perceived wait during local development
                (new ProcessGenerationJob($job))->handle();
            } else {
                dispatch(new ProcessGenerationJob($job));
            }
        }

        return $group;
    }

    private function resolveProvider(int $tenantId)
    {
        $default = config('hairvision.default_ai_provider');

        return AiProvider::query()->where('code', '=', $default)->firstOrFail();
    }
}
