<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $ariaTenant = Tenant::query()->updateOrCreate(
            ['slug' => 'aria'],
            [
                'name' => 'کلینیک آریا',
                'status' => 'active',
            ]
        );

        $nojaTenant = Tenant::query()->updateOrCreate(
            ['slug' => 'noja'],
            [
                'name' => 'کلینیک نوژا',
                'status' => 'active',
            ]
        );

        $this->upsertDomain($ariaTenant, 'aria.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($nojaTenant, 'noja.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($ariaTenant, 'hairvision-ai.test', 'platform_subdomain');
    }

    private function upsertDomain(Tenant $tenant, string $domain, string $type): void
    {
        TenantDomain::query()->updateOrCreate(
            ['domain' => $domain],
            [
                'tenant_id' => $tenant->id,
                'type' => $type,
                'status' => 'active',
                'ssl_status' => 'unknown',
            ]
        );
    }
}
