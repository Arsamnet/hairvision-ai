<?php

namespace App\Enums;

enum PlanAccessStatus: string
{
    case Exempt = 'exempt';
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case CreditFinished = 'credit_finished';
}
