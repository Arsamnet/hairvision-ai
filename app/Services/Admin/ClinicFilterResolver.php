<?php

namespace App\Services\Admin;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Resolves optional dashboard clinic filters without trusting request input.
 *
 * @since 1.0.0
 */
class ClinicFilterResolver
{
    /**
     * Resolve a filterable clinic after confirming the actor can access it.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  User  $user  Authenticated user performing the operation.
     * @param  string  $input  The input value.
     * @return Tenant|null Resolved operation result.
     *
     * @throws ValidationException
     *
     * @since 1.0.0
     */
    public function resolve(Request $request, User $user, string $input = 'clinic_id'): ?Tenant
    {
        if (! $request->filled($input)) {
            return null;
        }

        $clinicId = (int) $request->input($input);

        if (! $user->canAccessTenant($clinicId)) {
            throw ValidationException::withMessages([
                $input => __('permissions.tenant_denied'),
            ]);
        }

        return Tenant::query()
            ->whereKey($clinicId)
            ->where('status', 'active')
            ->firstOrFail();
    }
}
