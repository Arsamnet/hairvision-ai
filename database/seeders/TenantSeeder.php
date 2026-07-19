<?php

namespace Database\Seeders;

use App\Models\SmsGateway;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    private const DEFAULT_IPPANEL_AUTHORIZATION_TOKEN = 'YTIxMzVhYmMtZGRkYS00OWJhLWE2ZTItNjUzYjhkYTg5M2U0OGRiZjZjMzZiNjBkNjgxYmUwZTAwM2IwZDc1MzczMTU=';

    public function run(): void
    {
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

        $this->upsertDomain($aryaTenant, 'arya.hairvision-ai.test', 'tenant_subdomain', 'aria.hairvision-ai.test');
        $this->upsertDomain($nojaTenant, 'noja.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($demoTenant, 'demo.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($aryaTenant, 'hairvision-ai.test', 'platform_subdomain');

        $this->upsertDemoIppanelGateway($demoTenant);
    }

    private function upsertDomain(Tenant $tenant, string $domain, string $type, ?string $legacyDomain = null): void
    {
        $tenantDomain = TenantDomain::query()->where('domain', $domain)->first()
            ?? ($legacyDomain
                ? TenantDomain::query()->where('tenant_id', $tenant->id)->where('domain', $legacyDomain)->first()
                : null)
            ?? new TenantDomain;

        $tenantDomain->forceFill([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => $type,
            'status' => 'active',
            'ssl_status' => 'unknown',
        ])->save();
    }

    private function upsertDemoIppanelGateway(Tenant $tenant): void
    {
        $fromNumber = config('hairvision.sms.ippanel.from_number');

        SmsGateway::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'owner_scope' => 'tenant',
                'provider_code' => 'ippanel',
            ],
            [
                'user_id' => null,
                'name' => 'پنل پیامک IPPanel کلینیک دمو',
                'status' => 'enabled',
                'is_default' => true,
                'encrypted_credentials' => [
                    'api_key' => config('hairvision.sms.ippanel.authorization_token')
                        ?: self::DEFAULT_IPPANEL_AUTHORIZATION_TOKEN,
                ],
                'settings_json' => array_filter([
                    'purpose' => 'clinic_messages',
                    'from_number' => $fromNumber,
                    'sender' => $fromNumber,
                    'otp_pattern_code' => config('hairvision.sms.ippanel.otp_pattern_code'),
                    'otp_pattern_variable' => config('hairvision.sms.ippanel.otp_pattern_variable'),
                    'endpoint_url' => config('hairvision.sms.ippanel.endpoint_url'),
                ]),
            ]
        );
    }
}
