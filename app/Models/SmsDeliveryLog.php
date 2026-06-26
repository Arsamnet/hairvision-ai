<?php

namespace App\Models;

use App\Enums\SmsMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores safe metadata for SMS delivery attempts.
 *
 * @since 1.2.0
 */
#[Fillable([
    'user_id',
    'sms_provider_account_id',
    'sms_provider_type_id',
    'recipient_mobile',
    'message_preview',
    'message_hash',
    'purpose',
    'status',
    'provider_message_id',
    'error_code',
    'error_message',
    'cost',
    'metadata',
    'sent_at',
    'failed_at',
])]
class SmsDeliveryLog extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SmsMessageStatus::class,
            'metadata' => 'array',
            'cost' => 'decimal:4',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * User associated with this send attempt.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Provider account used for this attempt.
     *
     * @return BelongsTo<SmsProviderAccount, $this>
     */
    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(SmsProviderAccount::class, 'sms_provider_account_id');
    }

    /**
     * Vendor type used for this attempt.
     *
     * @return BelongsTo<SmsProviderType, $this>
     */
    public function providerType(): BelongsTo
    {
        return $this->belongsTo(SmsProviderType::class, 'sms_provider_type_id');
    }
}
