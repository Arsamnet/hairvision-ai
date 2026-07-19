<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\SmsGateway;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    private const DEFAULT_IPPANEL_AUTHORIZATION_TOKEN = 'YTIxMzVhYmMtZGRkYS00OWJhLWE2ZTItNjUzYjhkYTg5M2U0OGRiZjZjMzZiNjBkNjgxYmUwZTAwM2IwZDc1MzczMTU=';

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

        $demoTenant = Tenant::query()->updateOrCreate(
            ['slug' => 'demo'],
            [
                'name' => 'کلینیک دمو',
                'status' => 'active',
            ]
        );

        $this->upsertDomain($ariaTenant, 'aria.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($nojaTenant, 'noja.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($demoTenant, 'demo.hairvision-ai.test', 'tenant_subdomain');
        $this->upsertDomain($ariaTenant, 'hairvision-ai.test', 'platform_subdomain');

        $this->upsertDemoIppanelGateway($demoTenant);
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
