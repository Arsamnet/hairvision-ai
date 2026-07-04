<?php

namespace App\Services\AI\Resolvers;

use App\Models\TenantAiSetting;
use App\Services\AI\Contracts\ImageGenerationProvider;
use App\Services\AI\Providers\SampleImageProvider;
use App\Support\DebugStepLogger;

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

        $step = $this->workflowStep($scope, $step, 'Request received', [
            'tenant_id' => $tenantId,
        ]);

        $setting = TenantAiSetting::query()
            ->with('provider')
            ->where('tenant_id', '=', $tenantId)
            ->where('is_default', '=', true)
            ->first();

        $step = $this->workflowStep($scope, $step, 'Default AI setting lookup completed', [
            'tenant_id' => $tenantId,
            'setting_found' => $setting !== null,
            'setting_id' => $setting?->id,
            'provider_id' => $setting?->provider_id,
            'provider_code' => $setting?->provider?->code,
        ]);

        if ($setting && $setting->provider && $setting->provider->code === 'sample_image') {
            $provider = app(SampleImageProvider::class);

            $this->workflowStep($scope, $step, 'Configured sample image provider resolved', [
                'tenant_id' => $tenantId,
                'setting_id' => $setting->id,
                'provider_code' => $provider->code(),
                'provider_class' => $provider::class,
            ]);

            return $provider;
        }

        $provider = app(SampleImageProvider::class);

        $this->workflowStep($scope, $step, 'Fallback sample image provider resolved', [
            'tenant_id' => $tenantId,
            'has_setting' => $setting !== null,
            'configured_provider_code' => $setting?->provider?->code,
            'provider_code' => $provider->code(),
            'provider_class' => $provider::class,
        ]);

        return $provider;
    }

    /**
     * Write a safe ordered debug step.
     *
     * @param  array<string, mixed>  $context
     */
    private function workflowStep(string $scope, int $step, string $message, array $context = []): int
    {
        $step++;

        DebugStepLogger::workflowStep($scope, $step, $message, $context);

        return $step;
    }
}