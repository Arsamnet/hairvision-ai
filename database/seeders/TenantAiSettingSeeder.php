<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use Illuminate\Database\Seeder;

class TenantAiSettingSeeder extends Seeder
{
    public function run(): void
    {
        $sampleProvider = AiProvider::query()->where('code', 'sample_image')->firstOrFail();
        $gapGptProvider = AiProvider::query()->where('code', 'gapgpt')->firstOrFail();

        Tenant::query()
            ->whereIn('slug', ['aria', 'noja'])
            ->get()
            ->each(fn (Tenant $tenant) => TenantAiSetting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'provider_id' => $sampleProvider->id],
                ['is_default' => true]
            ));

        $demoTenant = Tenant::query()->where('slug', 'demo')->first();

        if ($demoTenant) {
            TenantAiSetting::query()->updateOrCreate(
                ['tenant_id' => $demoTenant->id, 'provider_id' => $gapGptProvider->id],
                [
                    'model_name' => config('ai-models.models.gapgpt.default_model'),
                    'settings_json' => [
                        'endpoint_url' => config('ai-models.models.gapgpt.endpoint'),
                        'size' => config('ai-models.models.gapgpt.default_size'),
                        'response_format' => config('ai-models.models.gapgpt.default_response_format'),
                    ],
                    'is_default' => true,
                ]
            );
        }
    }
}
