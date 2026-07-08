<?php

namespace App\Policies;

use App\Models\SmsGateway;
use App\Models\User;

/**
 * Authorizes system and clinic SMS gateway management.
 *
 * @since 1.0.0
 */
class SmsGatewayPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sms_gateways.list');
    }

    public function view(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.view')
            || ($gateway->owner_scope === 'tenant' && $user->canAccessTenant($gateway->tenant_id));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sms_gateways.create');
    }

    public function update(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.edit')
            || ($gateway->owner_scope === 'tenant' && $user->canAccessTenant($gateway->tenant_id));
    }

    public function delete(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.delete');
    }
}
