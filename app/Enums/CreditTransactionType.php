<?php

namespace App\Enums;

enum CreditTransactionType: string
{
    case Grant = 'grant';
    case Use = 'use';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case TopUp = 'top_up';
}
