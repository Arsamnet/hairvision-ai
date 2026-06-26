<?php

namespace App\Enums;

/**
 * Supported SMS provider vendor keys.
 *
 * @since 1.2.0
 */
enum SmsProviderTypeKey: string
{
    case MelliPayamak = 'melli_payamak';
    case KaveNegar = 'kavenegar';
    case Ippanel = 'ippanel';
    case SmsIr = 'sms_ir';
}
