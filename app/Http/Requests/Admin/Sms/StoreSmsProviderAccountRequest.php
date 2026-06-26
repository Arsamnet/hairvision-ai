<?php

namespace App\Http\Requests\Admin\Sms;

use App\Enums\SmsProviderScope;
use App\Models\SmsProviderAccount;
use App\Models\SmsProviderType;
use App\Support\AdminDashboardFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of system or user-scoped SMS provider accounts.
 *
 * @since 1.2.0
 */
class StoreSmsProviderAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SmsProviderAccount::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sms_provider_type_id' => ['required', 'integer', Rule::exists('sms_provider_types', 'id')->where('is_active', true)],
            'scope' => ['required', Rule::in([SmsProviderScope::System->value, SmsProviderScope::User->value])],
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'sender_number' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure dynamic provider credential validation.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if ($this->input('scope') === SmsProviderScope::System->value && ! $this->user()->isSuperAdmin()) {
                    $validator->errors()->add('scope', 'فقط مدیر سیستم می‌تواند سرویس پیامک سیستمی بسازد.');
                }

                $this->validateCredentials($validator, true);
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sms_provider_type_id' => AdminDashboardFormat::normalizeDigits((string) $this->input('sms_provider_type_id')),
            'priority' => filled($this->input('priority'))
                ? AdminDashboardFormat::normalizeDigits((string) $this->input('priority'))
                : $this->input('priority'),
        ]);
    }

    /**
     * Validate required credentials for the selected vendor.
     */
    private function validateCredentials($validator, bool $required): void
    {
        $type = SmsProviderType::query()->find($this->integer('sms_provider_type_id'));
        $credentials = (array) $this->input('credentials', []);

        if (! $type) {
            return;
        }

        $missing = match ($type->key) {
            'melli_payamak' => blank($credentials['api_key'] ?? null)
                && (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)),
            default => blank($credentials['api_key'] ?? null),
        };

        if ($required && $missing) {
            $validator->errors()->add('credentials', 'اطلاعات اتصال سرویس پیامک کامل نیست.');
        }
    }
}
