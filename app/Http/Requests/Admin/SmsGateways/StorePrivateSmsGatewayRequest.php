<?php

namespace App\Http\Requests\Admin\SmsGateways;

use App\Models\SmsGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates private SMS gateway creation for the authenticated user.
 *
 * @since 1.0.0
 */
class StorePrivateSmsGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'provider_code' => ['nullable', 'alpha_dash', 'max:80', Rule::in($this->providerCodes())],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'from_number' => ['nullable', 'string', 'max:120'],
            'sender' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $providerCodes = $this->providerCodes();

        $this->merge([
            'name' => $this->input('name') ?: __('sms.dedicated_panel_name'),
            'provider_code' => $this->input('provider_code') ?: ($providerCodes[0] ?? 'ippanel'),
            'status' => $this->boolean('is_active') ? 'enabled' : 'disabled',
            'is_default' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function providerCodes(): array
    {
        $codes = SmsGateway::query()->system()->enabled()->pluck('provider_code')->all();

        return $codes !== []
            ? array_values(array_unique($codes))
            : ['local', 'ippanel'];
    }
}
