<?php

namespace App\Actions\Sms;

use App\Models\SmsProviderAccount;

/**
 * Updates provider account metadata and replaces credentials only when supplied.
 *
 * @since 1.2.0
 */
class UpdateSmsProviderAccountAction
{
    /**
     * Update an SMS provider account.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(SmsProviderAccount $account, array $data): SmsProviderAccount
    {
        $updates = [
            'name' => $data['name'],
            'sender_number' => $data['sender_number'] ?? null,
            'priority' => $data['priority'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_default' => (bool) ($data['is_default'] ?? false),
        ];

        if (! empty($data['credentials'])) {
            $updates['credentials'] = $data['credentials'];
        }

        $account->update($updates);

        return $account->refresh();
    }
}
