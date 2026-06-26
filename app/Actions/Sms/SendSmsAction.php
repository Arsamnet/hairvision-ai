<?php

namespace App\Actions\Sms;

use App\Data\Sms\SmsSendResult;
use App\Models\User;
use App\Services\Sms\SmsManager;

/**
 * Application action for sending SMS without exposing provider details.
 *
 * @since 1.2.0
 */
class SendSmsAction
{
    public function __construct(private readonly SmsManager $manager) {}

    /**
     * Send one SMS for a user and persist a delivery log.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function execute(User $user, string $mobile, string $message, string $purpose = 'general', array $metadata = []): SmsSendResult
    {
        return $this->manager->sendForUser($user, $mobile, $message, $purpose, $metadata);
    }
}
