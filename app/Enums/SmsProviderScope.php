<?php

namespace App\Enums;

/**
 * Defines ownership levels for configured SMS provider accounts.
 *
 * @since 1.2.0
 */
enum SmsProviderScope: string
{
    case System = 'system';
    case User = 'user';
}
