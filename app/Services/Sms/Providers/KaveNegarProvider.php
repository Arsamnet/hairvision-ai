<?php

namespace App\Services\Sms\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;

/**
 * KaveNegar SMS adapter.
 *
 * @since 1.2.0
 */
class KaveNegarProvider extends AbstractHttpSmsProvider implements SmsProviderInterface
{
    public function send(SendSmsData $data): SmsSendResult
    {
        $credentials = $this->requireCredentials(['api_key']);
        $endpoint = rtrim((string) config('sms.providers.kavenegar.endpoint'), '/')
            .'/'.$credentials['api_key'].'/sms/send.json';

        $response = $this->http
            ->timeout((int) config('sms.http.timeout'))
            ->asForm()
            ->post($endpoint, [
                'receptor' => $data->recipientMobile,
                'sender' => $this->account->sender_number ?: ($credentials['sender_number'] ?? null),
                'message' => $data->message,
            ]);

        return $this->resultFromResponse($response, 'entries.0.messageid');
    }
}
