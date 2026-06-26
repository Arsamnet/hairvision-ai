<?php

namespace App\Services\Otp;

use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Log;

/**
 * Sends dashboard OTP messages through the centralized SMS manager.
 *
 * @since 1.2.0
 */
class SmsOtpSender implements OtpSenderInterface
{
    public function __construct(private readonly SmsManager $smsManager) {}

    /**
     * Send a one-time password to the normalized mobile number.
     */
    public function send(string $phoneNormalized, string $code): void
    {
        $user = auth()->user();

        if (! $user) {
            $user = \App\Models\User::query()->where('phone_normalized', $phoneNormalized)->first();
        }

        if (! $user) {
            return;
        }

        try {
            $this->smsManager->sendForUser($user, $phoneNormalized, 'کد ورود HairVision: '.$code, 'admin_otp');
        } catch (\Throwable $exception) {
            Log::warning('Admin OTP SMS could not be sent.', [
                'user_id' => $user->id,
                'phone_hash' => hash('sha256', $phoneNormalized),
                'error' => $exception::class,
            ]);
        }
    }
}
