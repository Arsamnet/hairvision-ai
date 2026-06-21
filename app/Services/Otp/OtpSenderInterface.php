<?php

namespace App\Services\Otp;

/**
 * Sends one-time passwords without coupling controllers to an SMS provider.
 *
 * @since 1.1.0
 */
interface OtpSenderInterface
{
    /**
     * Send a one-time password to the normalized mobile number.
     *
     * Implementations must not persist or log the plain OTP code.
     */
    public function send(string $phoneNormalized, string $code): void;
}
