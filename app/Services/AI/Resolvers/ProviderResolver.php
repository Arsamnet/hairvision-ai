<?php

namespace App\Services\AI\Resolvers;

use App\Models\TenantAiSetting;
use App\Services\AI\Contracts\ImageGenerationProvider;
use App\Services\AI\Providers\SampleImageProvider;

class ProviderResolver
{
    public function resolveForTenant(int $tenantId): ImageGenerationProvider
    {
        $setting = TenantAiSetting::query()->where('tenant_id', '=', $tenantId)->where('is_default', '=', true)->first();

        if ($setting && $setting->provider && $setting->provider->code === 'sample_image') {
            return new SampleImageProvider;
        }

        return new SampleImageProvider;
    }
}
