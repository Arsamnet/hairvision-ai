<?php

namespace App\Services\Otp;

/**
 * Local no-op OTP sender for development and tests.
 *
 * A real SMS provider should implement OtpSenderInterface and be bound in the
 * service container without changing authentication controllers.
 *
 * @since 1.0.0
 */
class LocalOtpSender implements OtpSenderInterface
{
    /**
     * Accept an OTP locally without external delivery.
     *
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function send(string $phoneNormalized, string $code): void
    {
        // Intentionally no-op: do not log or expose plain OTP values here.
    }
}
