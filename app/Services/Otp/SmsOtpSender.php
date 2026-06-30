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
    public function __construct(private readonly SmsGatewayService $gateways) {}

    public function send(string $phoneNormalized, string $code): void
    {
        $this->gateways->sendSystemMessage($phoneNormalized, __('auth.lead_otp_sms', ['code' => $code]));
    }
}
