<?php

namespace App\Services\Sms\Adapters;

use App\Models\SmsGateway;
use App\Services\Sms\Contracts\SmsGatewayAdapter;
use App\Services\Sms\DebugSmsGatewayAvailability;

/**
 * Local no-op SMS adapter.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class LocalSmsGatewayAdapter implements SmsGatewayAdapter
{
    /**
     * Create the guarded local SMS adapter.
     *
     * @param  DebugSmsGatewayAvailability  $availability  Runtime debug gateway boundary.
     *
     * @since 2.1.0
     */
    public function __construct(
        private readonly DebugSmsGatewayAvailability $availability,
    ) {}

    /**
     * Pretend to send a normal SMS in local/test mode.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $message  The message value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void
    {
        $this->availability->ensureAdapterIsEnabled();
    }

    /**
     * Pretend to send an OTP SMS in local/test mode.
     *
     * @param  SmsGateway  $gateway  The gateway value.
     * @param  string  $phoneNormalized  The phone normalized value.
     * @param  string  $code  The code value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function sendOtp(SmsGateway $gateway, string $phoneNormalized, string $code): void
    {
        $this->availability->ensureAdapterIsEnabled();
    }

    /**
     * Write a safe debug step for this adapter.
     *
     * @param  array<string, mixed>  $context
     */
}
