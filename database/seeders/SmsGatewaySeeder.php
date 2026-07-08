<?php

namespace Database\Seeders;

use App\Models\SmsGateway;
use Illuminate\Database\Seeder;

class SmsGatewaySeeder extends Seeder
{
    private const DEFAULT_IPPANEL_AUTHORIZATION_TOKEN = 'YTIxMzVhYmMtZGRkYS00OWJhLWE2ZTItNjUzYjhkYTg5M2U0OGRiZjZjMzZiNjBkNjgxYmUwZTAwM2IwZDc1MzczMTU=';

    public function run(): void
    {
        SmsGateway::query()->updateOrCreate(
            ['owner_scope' => 'system', 'provider_code' => 'local', 'user_id' => null],
            [
                'tenant_id' => null,
                'name' => __('messages.local_sms_gateway'),
                'status' => 'enabled',
                'is_default' => true,
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'system_messages'],
            ]
        );

        $ippanelToken = config('hairvision.sms.ippanel.authorization_token') ?: self::DEFAULT_IPPANEL_AUTHORIZATION_TOKEN;
        $ippanelFromNumber = config('hairvision.sms.ippanel.from_number');

        if (! filled($ippanelToken)) {
            return;
        }

        SmsGateway::query()->updateOrCreate(
            ['owner_scope' => 'system', 'provider_code' => 'ippanel', 'user_id' => null],
            [
                'tenant_id' => null,
                'name' => __('messages.ippanel_sms_gateway'),
                'status' => 'disabled',
                'is_default' => false,
                'encrypted_credentials' => ['api_key' => $ippanelToken],
                'settings_json' => array_filter([
                    'purpose' => 'system_messages',
                    'from_number' => $ippanelFromNumber,
                    'sender' => $ippanelFromNumber,
                    'otp_pattern_code' => config('hairvision.sms.ippanel.otp_pattern_code'),
                    'otp_pattern_variable' => config('hairvision.sms.ippanel.otp_pattern_variable'),
                    'endpoint_url' => config('hairvision.sms.ippanel.endpoint_url'),
                ]),
            ]
        );
    }
}
