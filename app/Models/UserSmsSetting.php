<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores a dashboard user's selected SMS account and fallback behavior.
 *
 * @since 1.2.0
 */
#[Fillable(['user_id', 'default_sms_provider_account_id', 'fallback_to_system'])]
class UserSmsSetting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fallback_to_system' => 'boolean',
        ];
    }

    /**
     * User that owns these SMS settings.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Selected provider account.
     *
     * @return BelongsTo<SmsProviderAccount, $this>
     */
    public function defaultProviderAccount(): BelongsTo
    {
        return $this->belongsTo(SmsProviderAccount::class, 'default_sms_provider_account_id');
    }
}
