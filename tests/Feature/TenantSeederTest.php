<?php

namespace Tests\Feature;

use App\Models\TenantDomain;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies environment-aware tenant domain seeding.
 *
 * @since 2.0.0
 */
class TenantSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure seeded domain mappings follow APP_URL and update on reseeding.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_seeded_domains_are_derived_from_app_url_and_updated_on_reseed(): void
    {
        config(['app.url' => 'https://Initial.Example:8443/application']);

        $this->seed(TenantSeeder::class);

        $this->assertSame([
            'arya.initial.example',
            'demo.initial.example',
            'initial.example',
            'noja.initial.example',
            'silversands.initial.example',
        ], TenantDomain::query()->orderBy('domain')->pluck('domain')->all());

        config(['app.url' => 'https://production.example/']);

        $this->seed(TenantSeeder::class);

        $this->assertSame([
            'arya.production.example',
            'demo.production.example',
            'noja.production.example',
            'production.example',
            'silversands.production.example',
        ], TenantDomain::query()->orderBy('domain')->pluck('domain')->all());
        $this->assertDatabaseCount('tenant_domains', 5);
        $this->assertDatabaseMissing('tenant_domains', ['domain' => 'initial.example']);
    }
}
