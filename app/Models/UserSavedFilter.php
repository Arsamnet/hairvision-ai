<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reusable dashboard list filter preset owned by one authenticated user.
 *
 * @since 1.0.0
 */
class UserSavedFilter extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'user_id',
        'filter_key',
        'name',
        'filters_json',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Dashboard user that owns this saved filter.
     *
     * @return BelongsTo<User, $this>
     *
     * @since 1.0.0
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
