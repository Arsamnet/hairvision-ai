<?php

namespace App\Data\Sms;

use App\Models\SmsProviderAccount;
use App\Models\User;

/**
 * Immutable data required to send one SMS message.
 *
 * @since 1.2.0
 */
final readonly class SendSmsData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?User $user,
        public string $recipientMobile,
        public string $message,
        public string $purpose,
        public ?SmsProviderAccount $providerAccount = null,
        public array $metadata = [],
    ) {}
}
