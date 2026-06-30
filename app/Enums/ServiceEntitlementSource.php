<?php

namespace App\Enums;

enum ServiceEntitlementSource: string
{
    case BasePlan = 'base_plan';
    case AddOn = 'add_on';
}
