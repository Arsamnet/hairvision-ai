<?php

namespace Database\Seeders;

use App\Enums\SmsProviderScope;
use App\Enums\SmsProviderTypeKey;
use App\Models\SmsProviderAccount;
use App\Models\SmsProviderType;
use Illuminate\Database\Seeder;

/**
 * Seeds supported SMS vendors and disabled system accounts.
 *
 * @since 1.2.0
 */
class SmsProviderSeeder extends Seeder
{
    public function run(): void
    {
        $this->renameLegacyNotifSmsType();

        $types = [
            SmsProviderTypeKey::MelliPayamak->value => ['name' => 'ملی پیامک', 'description' => 'Melli Payamak SMS gateway.'],
            SmsProviderTypeKey::KaveNegar->value => ['name' => 'کاوه نگار', 'description' => 'KaveNegar SMS gateway.'],
            SmsProviderTypeKey::Ippanel->value => ['name' => 'IPPanel', 'description' => 'IPPanel SMS gateway.'],
            SmsProviderTypeKey::SmsIr->value => ['name' => 'SMS.ir', 'description' => 'SMS.ir gateway.'],
        ];

        foreach ($types as $key => $data) {
            $type = SmsProviderType::updateOrCreate(
                ['key' => $key],
                ['name' => $data['name'], 'description' => $data['description'], 'is_active' => true]
            );

            if ($key === SmsProviderTypeKey::Ippanel->value) {
                $this->seedIppanelSystemAccounts($type);

                continue;
            }

            $this->seedSystemAccount($type, $data['name'].' سیستم', []);
        }
    }

    /**
     * Seed the two system IPPanel accounts: normal send and OTP pattern.
     */
    private function seedIppanelSystemAccounts(SmsProviderType $type): void
    {
        SmsProviderAccount::query()
            ->where('sms_provider_type_id', $type->id)
            ->where('scope', SmsProviderScope::System->value)
            ->where('name', 'نوتیف پیامک سیستم')
            ->delete();

        $this->seedSystemAccount($type, 'IPPanel سیستم - ارسال معمولی', [
            'sending_type' => 'normal',
            'from_number' => config('sms.providers.ippanel.from_number'),
        ]);

        $this->seedSystemAccount($type, 'IPPanel سیستم - کد تایید', [
            'sending_type' => 'pattern',
            'from_number' => config('sms.providers.ippanel.from_number'),
            'code' => config('sms.providers.ippanel.otp_pattern_code'),
            'params' => [
                'verification-code' => null,
            ],
        ]);
    }

    /**
     * Seed one disabled system account without real credentials.
     *
     * @param  array<string, mixed>  $settings
     */
    private function seedSystemAccount(SmsProviderType $type, string $name, array $settings): void
    {
        SmsProviderAccount::updateOrCreate(
            ['scope' => SmsProviderScope::System->value, 'sms_provider_type_id' => $type->id, 'owner_user_id' => null, 'name' => $name],
            [
                'credentials' => [],
                'settings' => $settings,
                'sender_number' => null,
                'priority' => null,
                'is_active' => false,
                'is_default' => false,
            ]
        );
    }

    /**
     * Update local databases seeded before NotifSMS was replaced by IPPanel.
     */
    private function renameLegacyNotifSmsType(): void
    {
        $legacyType = SmsProviderType::query()->where('key', 'notif_sms')->first();

        if (! $legacyType || SmsProviderType::query()->where('key', SmsProviderTypeKey::Ippanel->value)->exists()) {
            return;
        }

        $legacyType->update([
            'key' => SmsProviderTypeKey::Ippanel->value,
            'name' => 'IPPanel',
            'description' => 'IPPanel SMS gateway.',
            'is_active' => true,
        ]);
    }
}
