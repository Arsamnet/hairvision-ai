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

        Tenant::query()
            ->whereIn('slug', ['aria', 'noja'])
            ->get()
            ->each(fn (Tenant $tenant) => TenantAiSetting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'provider_id' => $sampleProvider->id],
                ['is_default' => true]
            ));
    }
}
