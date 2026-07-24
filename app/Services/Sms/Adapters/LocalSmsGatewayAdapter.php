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
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $message  The message value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void {}

    /**
     * Pretend to send an OTP SMS in local/test mode.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void {}

    /**
     * Write a safe debug step for this adapter.
     *
     * @param  array<string, mixed>  $context
     */
}
