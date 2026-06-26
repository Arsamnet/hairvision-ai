<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Backend-enforced permission used by gates, policies, and dashboard menus.
 *
 * @since 1.0.0
 */
class Permission extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'code',
        'name',
        'group',
        'description',
    ];

    /**
     * Access levels that include this permission.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }
}
