<?php

namespace App\Services\Otp;

/**
 * Sends one-time passwords without coupling controllers to an SMS provider.
 *
 * @since 1.0.0
 */
interface OtpSenderInterface
{
    /**
     * Send a one-time password to the normalized mobile number.
     *
     * Implementations must not persist or log the plain OTP code.
     *
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function send(string $phoneNormalized, string $code): void;
}
