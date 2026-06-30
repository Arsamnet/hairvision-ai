<?php

namespace App\Enums;

enum PlanType: string
{
    case BaseSubscription = 'base_subscription';
    case ServiceAddOn = 'service_add_on';
    case CreditTopUp = 'credit_top_up';
}
