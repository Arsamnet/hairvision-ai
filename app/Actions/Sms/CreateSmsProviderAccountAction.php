<?php

namespace App\Actions\Sms;

use App\Enums\SmsProviderScope;
use App\Models\SmsProviderAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Creates SMS provider accounts while enforcing ownership invariants.
 *
 * @since 1.2.0
 */
class CreateSmsProviderAccountAction
{
    /**
     * Persist a new provider account for a system admin or owning user.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor): SmsProviderAccount
    {
        $scope = SmsProviderScope::from($data['scope']);

        if ($scope === SmsProviderScope::System && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'scope' => 'فقط مدیر سیستم می‌تواند سرویس پیامک سیستمی بسازد.',
            ]);
        }

        return SmsProviderAccount::create([
            'sms_provider_type_id' => $data['sms_provider_type_id'],
            'owner_user_id' => $scope === SmsProviderScope::User ? $actor->id : null,
            'scope' => $scope,
            'name' => $data['name'],
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
            'sender_number' => $data['sender_number'] ?? null,
            'priority' => $data['priority'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ]);
    }
}
