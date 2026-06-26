<?php

namespace App\Contracts\Sms;

use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;
use App\Exceptions\Sms\SmsProviderException;

/**
 * Defines the contract for sending SMS messages through a provider.
 *
 * @since 1.2.0
 */
interface SmsProviderInterface
{
    /**
     * Send a text message to a mobile number.
     *
     * @throws SmsProviderException
     */
    public function send(SendSmsData $data): SmsSendResult;
}
