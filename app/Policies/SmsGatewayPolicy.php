<?php

namespace App\Policies;

use App\Models\SmsGateway;
use App\Models\User;
use App\Services\Sms\DebugSmsGatewayAvailability;

/**
 * Authorizes system and clinic SMS gateway management.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class SmsGatewayPolicy
{
    /**
     * Create the SMS gateway policy.
     *
     * @param  DebugSmsGatewayAvailability  $debugGateway  Runtime debug gateway boundary.
     *
     * @since 2.1.0
     */
    public function __construct(
        private readonly DebugSmsGatewayAvailability $debugGateway,
    ) {}

    /**
     * Execute the before operation.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  string  $ability  The policy ability being evaluated.
     * @return bool|null Resolved operation result.
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isSuperAdmin()) {
            return null;
        }

        return in_array($ability, ['update', 'delete', 'manageDefault', 'markDefault'], true) ? null : true;
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
        return ! $gateway->is_protected
            && (
                $user->hasPermission('sms_gateways.edit')
                || ($gateway->owner_scope === 'tenant' && $user->canAccessTenant($gateway->tenant_id))
            );
    }

    /**
     * Determine whether the default-selection action belongs in the gateway menu.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  SmsGateway  $gateway  Gateway shown in the management list.
     * @return bool Whether the action may be displayed.
     *
     * @since 2.1.0
     */
    public function manageDefault(User $user, SmsGateway $gateway): bool
    {
        return $user->hasPermission('sms_gateways.edit');
    }

    /**
     * Determine whether the gateway may currently be selected as default.
     *
     * @param  User  $user  Authenticated user performing the operation.
     * @param  SmsGateway  $gateway  Gateway being selected.
     * @return bool Whether the default-selection request is authorized.
     *
     * @since 2.1.0
     */
    public function markDefault(User $user, SmsGateway $gateway): bool
    {
        return $this->manageDefault($user, $gateway)
            && $this->debugGateway->canBeSelectedAsDefault($gateway);
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
        return ! $gateway->is_protected && $user->hasPermission('sms_gateways.delete');
    }
}
