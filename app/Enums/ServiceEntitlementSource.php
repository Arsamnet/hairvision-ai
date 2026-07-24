<?php

namespace App\Enums;

/**
 * Defines the supported service entitlement source values.
 *
 * @since 1.0.0
 */
enum ServiceEntitlementSource: string
{
    case BasePlan = 'base_plan';
    case AddOn = 'add_on';
}
