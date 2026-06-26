<?php

namespace App\Models;

use App\Services\Auth\PermissionService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
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
     * Private SMS gateways configured by this user for customer messages.
     *
     * @return HasMany<SmsGateway, $this>
     */
    public function smsGateways(): HasMany
    {
        return $this->hasMany(SmsGateway::class);
    }

    /**
     * Invoices owned by this user.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Payment transactions initiated by this user.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Plan entitlements for this user.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
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

    /**
     * Determine whether the user has a backend-enforced permission.
     */
    public function hasPermission(string $permission, ?int $tenantId = null): bool
    {
        return app(PermissionService::class)->can($this, $permission, $tenantId);
    }

    /**
     * Determine whether the user has at least one of the provided permissions.
     *
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
