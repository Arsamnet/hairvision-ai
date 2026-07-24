<?php

namespace App\Enums;

/**
 * Defines the supported plan access status values.
 *
 * @since 1.0.0
 */
enum PlanAccessStatus: string
{
    case Exempt = 'exempt';
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case CreditFinished = 'credit_finished';
    case PlanDisabled = 'plan_disabled';
}
