<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

/**
 * Authenticatable user reserved for future admin and client dashboards.
 *
 * @since 1.0.0
 */
#[Fillable(['name', 'email', 'phone_normalized', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Tenants this dashboard user can access.
     *
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * Leads assigned to this dashboard user.
     *
     * @return HasMany<Lead, $this>
     */
    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_user_id');
    }

    /**
     * Determine whether the user has platform-level access.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Determine whether the user is active and may authenticate.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Determine whether the user can access a tenant.
     */
    public function canAccessTenant(?int $tenantId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $tenantId) {
            return false;
        }

        return $this->tenants()
            ->whereKey($tenantId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Return the role that applies inside a tenant context.
     */
    public function roleForTenant(?int $tenantId): string
    {
        if ($this->isSuperAdmin()) {
            return 'super_admin';
        }

        if (! $tenantId) {
            return $this->role;
        }

        $tenant = $this->tenants()
            ->whereKey($tenantId)
            ->wherePivot('status', 'active')
            ->first();

        return $tenant?->pivot?->role ?? $this->role;
    }
}
