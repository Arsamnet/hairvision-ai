<?php

namespace App\Services\Sms\Providers;

use App\Data\Sms\SendSmsData;
use App\Data\Sms\SmsSendResult;
use App\Exceptions\Sms\SmsProviderException;
use App\Models\SmsProviderAccount;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Shared helpers for HTTP-backed SMS provider adapters.
 *
 * @since 1.2.0
 */
abstract class AbstractHttpSmsProvider
{
    public function __construct(
        protected readonly HttpFactory $http,
        protected readonly SmsProviderAccount $account,
    ) {}

    /**
     * Validate required credential keys.
     *
     * @param  array<int, string>  $keys
     *
     * @throws SmsProviderException
     */
    protected function requireCredentials(array $keys): array
    {
        $credentials = $this->account->credentials ?? [];

        foreach ($keys as $key) {
            if (blank($credentials[$key] ?? null)) {
                throw new SmsProviderException('SMS provider credentials are incomplete.');
            }
        }

        return $credentials;
    }

    /**
     * Convert a response into a normalized result.
     */
    protected function resultFromResponse(Response $response, string $idKey = 'message_id'): SmsSendResult
    {
        if ($response->successful()) {
            $payload = $response->json();

            return SmsSendResult::sent((string) data_get($payload, $idKey, data_get($payload, 'id')));
        }

        return SmsSendResult::failed((string) $response->status(), 'SMS provider rejected the message.');
    }

    /**
     * Send one SMS message.
     *
     * @throws SmsProviderException
     */
    abstract public function send(SendSmsData $data): SmsSendResult;
}
