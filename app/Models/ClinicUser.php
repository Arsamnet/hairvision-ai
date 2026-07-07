<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Services\Auth\PermissionService;
use Database\Factories\ClinicUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Clinic-level dashboard user scoped to exactly one tenant.
 *
 * @since 1.0.0
 */
#[Fillable(['tenant_id', 'name', 'email', 'phone_normalized', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class ClinicUser extends Authenticatable
{
    /** @use HasFactory<ClinicUserFactory> */
    use HasFactory, HasUuid, Notifiable;

    protected $table = 'clinic_users';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Clinic that owns this user.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canAccessTenant(?int $tenantId): bool
    {
        return $tenantId !== null && (int) $this->tenant_id === (int) $tenantId;
    }

    public function roleForTenant(?int $tenantId): string
    {
        return $this->canAccessTenant($tenantId) ? $this->role : 'viewer';
    }

    public function hasPermission(string $permission, ?int $tenantId = null): bool
    {
        if (! $this->canAccessTenant($tenantId ?? $this->tenant_id)) {
            return false;
        }

        return app(PermissionService::class)->can($this, $permission, $this->tenant_id);
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function hasAnyPermission(iterable $permissions, ?int $tenantId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $tenantId)) {
                return true;
            }
        }

        return false;
    }
}
