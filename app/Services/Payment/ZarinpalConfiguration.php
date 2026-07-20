<?php

namespace App\Services\Payment;

use Illuminate\Support\Str;

/**
 * Shared validation rules for Zarinpal gateway credentials.
 *
 * @since 2.0.0
 */
final class ZarinpalConfiguration
{
    public const SEEDED_PLACEHOLDER_MERCHANT_ID = '11111111-1111-4111-8111-111111111111';

    /**
     * Determine whether a merchant ID is safe to use for provider requests.
     */
    public static function hasUsableMerchantId(mixed $merchantId): bool
    {
        if (! is_string($merchantId)) {
            return false;
        }

        $merchantId = trim($merchantId);

        return $merchantId !== ''
            && $merchantId !== self::SEEDED_PLACEHOLDER_MERCHANT_ID
            && Str::isUuid($merchantId);
    }
}
