<?php

namespace App\Services\Otp;

/**
 * Local no-op OTP sender for development and tests.
 *
 * A real SMS provider should implement OtpSenderInterface and be bound in the
 * service container without changing authentication controllers.
 *
 * @since 1.1.0
 */
class LocalOtpSender implements OtpSenderInterface
{
    public function send(string $phoneNormalized, string $code): void
    {
        // Intentionally no-op: do not log or expose plain OTP values here.
    }
}
