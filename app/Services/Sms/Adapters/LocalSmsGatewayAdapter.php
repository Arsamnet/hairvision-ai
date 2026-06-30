<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;

/**
 * Local no-op SMS adapter for development, tests, and the MVP default gateway.
 *
 * @since 1.0.0
 */
class LocalSmsGatewayAdapter implements SmsGatewayAdapter
{
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void
    {
        // Intentionally no-op. Do not log plain OTP codes or customer messages.
    }

    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void
    {
        // Intentionally no-op. Do not log plain OTP codes or customer messages.
    }
}
