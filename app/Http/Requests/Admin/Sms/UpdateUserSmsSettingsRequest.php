<?php

namespace App\Http\Requests\Admin\Sms;

use App\Models\SmsProviderAccount;
use App\Support\AdminDashboardFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a user's selected SMS provider account.
 *
 * @since 1.2.0
 */
class UpdateUserSmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_sms_provider_account_id' => ['nullable', 'integer', 'exists:sms_provider_accounts,id'],
            'fallback_to_system' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Verify the selected provider is active and accessible by the user.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $providerId = $this->input('default_sms_provider_account_id');

                if (blank($providerId)) {
                    return;
                }

                $provider = SmsProviderAccount::query()->find($providerId);

                if (! $provider?->isAccessibleBy($this->user())) {
                    $validator->errors()->add('default_sms_provider_account_id', 'این سرویس پیامک برای حساب شما قابل انتخاب نیست.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('default_sms_provider_account_id')) {
            $this->merge([
                'default_sms_provider_account_id' => filled($this->input('default_sms_provider_account_id'))
                    ? AdminDashboardFormat::normalizeDigits((string) $this->input('default_sms_provider_account_id'))
                    : $this->input('default_sms_provider_account_id'),
            ]);
        }
    }
}
