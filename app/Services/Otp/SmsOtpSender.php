<?php

namespace App\Services\Otp;

use App\Services\Sms\SmsGatewayService;

/**
 * Sends OTP codes through the configured system SMS gateway.
 *
 * @since 1.0.0
 */
class SmsOtpSender implements OtpSenderInterface
{
    /**
     * Create a new sms otp sender instance.
     *
     * @param  SmsGatewayService  $gateways  The gateways value.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly SmsGatewayService $gateways) {}

    /**
     * Send an OTP through the configured SMS gateway.
     *
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function send(string $phoneNormalized, string $code): void
    {
        $this->gateways->sendSystemOtp($phoneNormalized, $code);
    }
}
