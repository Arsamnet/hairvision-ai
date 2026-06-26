<?php

namespace App\Services\Sms;

use App\Contracts\Sms\SmsProviderFactoryInterface;
use App\Contracts\Sms\SmsProviderInterface;
use App\Enums\SmsProviderTypeKey;
use App\Exceptions\Sms\SmsProviderException;
use App\Models\SmsProviderAccount;
use App\Services\Sms\Providers\KaveNegarProvider;
use App\Services\Sms\Providers\MelliPayamakProvider;
use App\Services\Sms\Providers\IppanelProvider;
use App\Services\Sms\Providers\SmsIrProvider;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Creates HTTP-backed SMS provider adapters by vendor key.
 *
 * @since 1.2.0
 */
class SmsProviderFactory implements SmsProviderFactoryInterface
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * Create a provider adapter for a configured account.
     *
     * @throws SmsProviderException
     */
    public function make(SmsProviderAccount $account): SmsProviderInterface
    {
        $account->loadMissing('type');

        return match ($account->type?->key) {
            SmsProviderTypeKey::MelliPayamak->value => new MelliPayamakProvider($this->http, $account),
            SmsProviderTypeKey::KaveNegar->value => new KaveNegarProvider($this->http, $account),
            SmsProviderTypeKey::Ippanel->value => new IppanelProvider($this->http, $account),
            SmsProviderTypeKey::SmsIr->value => new SmsIrProvider($this->http, $account),
            default => throw new SmsProviderException('Unsupported SMS provider type.'),
        };
    }
}
