<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Payment\ZarinpalConfiguration;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    private const TEST_ZARINPAL_SANDBOX_MERCHANT_ID = '22222222-2222-4222-8222-222222222222';

    public function run(): void
    {
        $isTesting = app()->environment('testing');
        $isLocal = app()->environment('local');

        $localGateway = PaymentGateway::query()->firstOrCreate(
            ['provider_code' => 'local'],
            [
                'name' => __('messages.local_payment_gateway'),
                'status' => $isLocal ? 'enabled' : 'disabled',
                'is_default' => $isLocal,
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'local_billing_tests'],
            ]
        );

        $zarinpalGateway = PaymentGateway::query()->firstOrCreate(
            ['provider_code' => 'zarinpal'],
            [
                'name' => __('messages.zarinpal_test_payment_gateway'),
                'status' => $isTesting ? 'enabled' : 'disabled',
                'is_default' => $isTesting,
                'encrypted_credentials' => $isTesting
                    ? ['merchant_id' => self::TEST_ZARINPAL_SANDBOX_MERCHANT_ID]
                    : [],
                'settings_json' => [
                    'purpose' => 'zarinpal_sandbox_billing_tests',
                    'mode' => 'sandbox',
                    'sandbox_base_url' => 'https://sandbox.zarinpal.com',
                    'request_url' => 'https://sandbox.zarinpal.com/pg/v4/payment/request.json',
                    'verify_url' => 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json',
                    'start_pay_url' => 'https://sandbox.zarinpal.com/pg/StartPay/',
                ],
            ]
        );

        if (! $isTesting && data_get($zarinpalGateway->encrypted_credentials, 'merchant_id') === ZarinpalConfiguration::SEEDED_PLACEHOLDER_MERCHANT_ID) {
            $zarinpalGateway->forceFill([
                'status' => 'disabled',
                'is_default' => false,
                'encrypted_credentials' => [],
            ])->save();
        }

        if (! $isTesting && ! $isLocal && data_get($localGateway->settings_json, 'purpose') === 'local_billing_tests') {
            $localGateway->forceFill([
                'status' => 'disabled',
                'is_default' => false,
            ])->save();
        }

        if ($isTesting) {
            app(PaymentGatewayService::class)->markDefault($zarinpalGateway);
        } elseif ($isLocal) {
            app(PaymentGatewayService::class)->markDefault($localGateway);
        }
    }
}
