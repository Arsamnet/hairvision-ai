<?php

namespace App\Enums;

/**
 * Defines the supported credit transaction type values.
 *
 * @since 1.0.0
 */
enum CreditTransactionType: string
{
    case Grant = 'grant';
    case Use = 'use';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case TopUp = 'top_up';
    case Reservation = 'reservation';
    case Release = 'release';
}
