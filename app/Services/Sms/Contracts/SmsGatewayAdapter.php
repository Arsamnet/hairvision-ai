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
     */
    public function send(SmsGateway $gateway, string $phoneNormalized, string $message): void;
}
