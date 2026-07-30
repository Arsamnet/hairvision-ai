<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;
use UnexpectedValueException;

/**
 * Provides tenant seeder behavior.
 *
 * @since 1.0.0
 */
class TenantSeeder extends Seeder
{
    /**
     * Execute the tenant seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        $appHost = $this->appUrlHost();

        $aryaTenant = Tenant::query()->where('slug', 'arya')->first()
            ?? Tenant::query()->where('slug', 'aria')->first()
            ?? new Tenant;

        $aryaTenant->forceFill([
            'name' => 'کلینیک آریا',
            'slug' => 'arya',
            'status' => 'active',
        ])->save();

        $nojaTenant = Tenant::query()->updateOrCreate(
            ['slug' => 'noja'],
            [
                'name' => 'کلینیک نوژا',
                'status' => 'active',
            ]
        );

        $demoTenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'کلینیک دمو',
                'status' => 'active',
            ]
        );

        $silverSandsTenant = Tenant::query()->updateOrCreate(
            ['slug' => 'silversands'],
            [
                'name' => 'کلینیک شن‌های نقره‌ای',
                'status' => 'active',
            ]
        );

        $this->upsertDomain($aryaTenant, 'arya.'.$appHost, 'tenant_subdomain');
        $this->upsertDomain($nojaTenant, 'noja.'.$appHost, 'tenant_subdomain');
        $this->upsertDomain($demoTenant, 'demo.'.$appHost, 'tenant_subdomain');
        $this->upsertDomain($silverSandsTenant, 'silversands.'.$appHost, 'tenant_subdomain');
        $this->upsertDomain($aryaTenant, $appHost, 'platform_subdomain');

    }

    /**
     * Extract the normalized database host from the configured application URL.
     *
     * @return string The lowercase host without a trailing dot.
     *
     * @throws UnexpectedValueException When APP_URL does not contain a host.
     *
     * @since 2.0.0
     */
    private function appUrlHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            throw new UnexpectedValueException('APP_URL must contain a valid host before running TenantSeeder.');
        }

        return strtolower(rtrim($host, '.'));
    }

    /**
     * Execute the upsert domain operation.
     *
     * @param  Tenant  $tenant  Tenant in the current operation scope.
     * @param  string  $domain  The domain value.
     * @param  string  $type  The type value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function upsertDomain(Tenant $tenant, string $domain, string $type): void
    {
        $tenantDomain = TenantDomain::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', $type)
            ->first()
            ?? TenantDomain::query()->where('domain', $domain)->first()
            ?? new TenantDomain;

        $tenantDomain->forceFill([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => $type,
            'status' => 'active',
            'ssl_status' => 'unknown',
        ])->save();
    }
}
