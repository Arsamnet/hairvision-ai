<?php

namespace App\Contracts\Sms;

use App\Models\SmsProviderAccount;

/**
 * Builds vendor adapters for configured provider accounts.
 *
 * @since 1.2.0
 */
interface SmsProviderFactoryInterface
{
    /**
     * Create a provider adapter for a configured account.
     */
    public function make(SmsProviderAccount $account): SmsProviderInterface;
}
