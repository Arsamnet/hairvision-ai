<?php

namespace App\Http\Requests\Admin\Sms;

use App\Models\SmsProviderType;
use App\Support\AdminDashboardFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates updates to an existing SMS provider account.
 *
 * @since 1.2.0
 */
class UpdateSmsProviderAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('provider')) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
            'sender_number' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure credential replacement validation when new secrets are supplied.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                if (! $this->filled('credentials')) {
                    return;
                }

                $account = $this->route('provider');
                $type = SmsProviderType::query()->find($account?->sms_provider_type_id);
                $credentials = (array) $this->input('credentials', []);

                if (! $type) {
                    return;
                }

                $missing = match ($type->key) {
                    'melli_payamak' => blank($credentials['api_key'] ?? null)
                        && (blank($credentials['username'] ?? null) || blank($credentials['password'] ?? null)),
                    default => blank($credentials['api_key'] ?? null),
                };

                if ($missing) {
                    $validator->errors()->add('credentials', 'اطلاعات اتصال جدید کامل نیست.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('priority')) {
            $this->merge([
                'priority' => filled($this->input('priority'))
                    ? AdminDashboardFormat::normalizeDigits((string) $this->input('priority'))
                    : $this->input('priority'),
            ]);
        }
    }
}
