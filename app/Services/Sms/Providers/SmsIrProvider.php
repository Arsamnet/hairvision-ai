<?php

namespace App\Services\Sms\Providers;

use App\Contracts\Sms\SmsProviderInterface;
use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;

/**
 * SMS.ir adapter.
 *
 * @since 1.2.0
 */
class SmsIrProvider extends AbstractHttpSmsProvider implements SmsProviderInterface
{
    public function send(SendSmsData $data): SmsSendResult
    {
        $credentials = $this->requireCredentials(['api_key']);

        $response = $this->http
            ->timeout((int) config('sms.http.timeout'))
            ->withToken($credentials['api_key'])
            ->post(rtrim((string) config('sms.providers.sms_ir.endpoint'), '/').'/send/bulk', [
                'lineNumber' => $this->account->sender_number ?: ($credentials['line_number'] ?? null),
                'messageText' => $data->message,
                'mobiles' => [$data->recipientMobile],
            ]);

        return $this->resultFromResponse($response, 'data.messageIds.0');
    }
}
