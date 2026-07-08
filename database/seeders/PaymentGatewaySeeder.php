<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayService;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    private const DEFAULT_ZARINPAL_SANDBOX_MERCHANT_ID = '11111111-1111-4111-8111-111111111111';

    public function run(): void
    {
        PaymentGateway::query()->updateOrCreate(
            ['provider_code' => 'local'],
            [
                'name' => __('messages.local_payment_gateway'),
                'status' => 'enabled',
                'is_default' => false,
                'encrypted_credentials' => [],
                'settings_json' => ['purpose' => 'local_billing_tests'],
            ]
        );

        $zarinpalGateway = PaymentGateway::query()->updateOrCreate(
            ['provider_code' => 'zarinpal'],
            [
                'name' => __('messages.zarinpal_test_payment_gateway'),
                'status' => 'enabled',
                'is_default' => true,
                'encrypted_credentials' => [
                    'merchant_id' => self::DEFAULT_ZARINPAL_SANDBOX_MERCHANT_ID,
                ],
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

        app(PaymentGatewayService::class)->markDefault($zarinpalGateway);
    }
}
