<?php

namespace App\Services\Sms\Contracts;

use App\Models\SmsGateway;

/**
 * Common adapter contract for supported SMS gateway providers.
 *
 * @since 1.0.0
 */
interface SmsGatewayAdapter
{
    /**
     * Send a message through the configured gateway.
     *
     * Implementations must not log credentials or message-sensitive metadata.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $message  The message value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void;

    /**
     * Send a one-time password through the configured gateway.
     *
     * Implementations must keep the plain OTP out of logs and persisted
     * metadata.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void;
}
