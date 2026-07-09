<?php

namespace App\Services\AI\Resolvers;

use App\Models\TenantAiSetting;
use App\Services\AI\Contracts\ImageGenerationProvider;
use App\Services\AI\Providers\SampleImageProvider;

/**
 * Resolves tenant AI provider implementations.
 *
 * @since 1.0.0
 */
class ProviderResolver
{
    /**
     * Resolve the default provider for a tenant.
     */
    public function resolveForTenant(int $tenantId): ImageGenerationProvider
    {
        $scope = 'ai.provider_resolver.resolve_for_tenant';
        $step = 0;

        $step++;
        $setting = TenantAiSetting::query()
            ->with('provider')
            ->where('tenant_id', '=', $tenantId)
            ->where('is_default', '=', true)
            ->first();

        $step++;
        if ($setting && $setting->provider && $setting->provider->code === 'sample_image') {
            $provider = app(SampleImageProvider::class);

            return $provider;
        }

        $provider = app(SampleImageProvider::class);

        return $provider;
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
}
