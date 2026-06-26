<?php

namespace App\Services\Sms\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;

/**
 * Melli Payamak adapter.
 *
 * @since 1.2.0
 */
class MelliPayamakProvider extends AbstractHttpSmsProvider implements SmsProviderInterface
{
    public function send(SendSmsData $data): SmsSendResult
    {
        $credentials = $this->account->credentials ?? [];
        $required = filled($credentials['api_key'] ?? null) ? ['api_key'] : ['username', 'password'];
        $credentials = $this->requireCredentials($required);

        $response = $this->http
            ->timeout((int) config('sms.http.timeout'))
            ->post((string) config('sms.providers.melli_payamak.endpoint'), [
                'username' => $credentials['username'] ?? null,
                'password' => $credentials['password'] ?? null,
                'apikey' => $credentials['api_key'] ?? null,
                'from' => $this->account->sender_number ?: ($credentials['sender_number'] ?? null),
                'to' => $data->recipientMobile,
                'text' => $data->message,
            ]);

        return $this->resultFromResponse($response, 'Value');
    }
}
