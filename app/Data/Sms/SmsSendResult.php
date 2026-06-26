<?php

namespace App\Data\Sms;

use App\Enums\SmsMessageStatus;

/**
 * Normalized SMS provider response.
 *
 * @since 1.2.0
 */
final readonly class SmsSendResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public SmsMessageStatus $status,
        public ?string $providerMessageId = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?float $cost = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function sent(?string $providerMessageId = null, ?float $cost = null, array $metadata = []): self
    {
        return new self(SmsMessageStatus::Sent, $providerMessageId, cost: $cost, metadata: $metadata);
    }

    /**
     * Create a failed result with sanitized error metadata.
     */
    public static function failed(?string $errorCode, string $errorMessage): self
    {
        return new self(SmsMessageStatus::Failed, errorCode: $errorCode, errorMessage: $errorMessage);
    }
}
