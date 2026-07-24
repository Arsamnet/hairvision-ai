<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Dashboard access level mapped to user and tenant membership role codes.
 *
 * @since 1.0.0
 */
class Role extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'code',
        'scope',
        'name',
        'description',
        'is_system',
        'status',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Permissions granted to this access level.
     *
     * @return BelongsToMany<Permission, $this>
     *
     * @since 1.0.0
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    /**
     * Scope platform/system-level access roles.
     *
     * @param  Builder<Role>  $query
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     */
    public function scopePlatform(Builder $query): Builder
    {
        return $query->where('scope', 'platform');
    }

    /**
     * Scope predefined clinic-level access roles.
     *
     * @param  Builder<Role>  $query
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     */
    public function scopeClinic(Builder $query): Builder
    {
        return $query->where('scope', 'clinic');
    }

    /**
     * Determine whether clinic role.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function isClinicRole(): bool
    {
        return $this->scope === 'clinic';
    }
}
