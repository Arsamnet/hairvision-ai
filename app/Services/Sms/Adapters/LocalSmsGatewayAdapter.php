<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;

/**
 * Local no-op SMS adapter.
 *
 * @since 1.0.0
 */
class LocalSmsGatewayAdapter implements SmsGatewayAdapter
{
    /**
     * Pretend to send a normal SMS in local/test mode.
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void {}

    /**
     * Pretend to send an OTP SMS in local/test mode.
     */
    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void {}

    /**
     * Write a safe debug step for this adapter.
     *
     * @param  array<string, mixed>  $context
     */
}
