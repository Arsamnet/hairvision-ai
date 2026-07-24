<?php

namespace App\Enums;

/**
 * Defines the supported plan type values.
 *
 * @since 1.0.0
 */
enum PlanType: string
{
    case BaseSubscription = 'base_subscription';
    case ServiceAddOn = 'service_add_on';
    case CreditTopUp = 'credit_top_up';
}
