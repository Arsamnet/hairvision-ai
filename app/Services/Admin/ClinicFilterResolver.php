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
     * @throws ValidationException
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
