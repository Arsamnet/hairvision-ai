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
    /**
     * Execute the before operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool|null Resolved operation result.
     *
     * @since 1.0.0
     */
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    /**
     * Execute the view any operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('sms_gateways.list');
    }

    /**
     * Execute the view operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  SmsGateway  $gateway  The gateway value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function view(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.view')
            || ($gateway->owner_scope === 'tenant' && $user->canAccessTenant($gateway->tenant_id));
    }

    /**
     * Display the form for creating a new sms gateway policy.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('sms_gateways.create');
    }

    /**
     * Update the requested sms gateway policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  SmsGateway  $gateway  The gateway value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function update(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.edit')
            || ($gateway->owner_scope === 'tenant' && $user->canAccessTenant($gateway->tenant_id));
    }

    /**
     * Delete the requested sms gateway policy record.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  SmsGateway  $gateway  The gateway value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function delete(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.delete');
    }
}
