<?php

namespace App\Http\Requests\Admin\SmsGateways;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates private SMS gateway updates for the authenticated owner.
 *
 * @since 1.0.0
 */
class UpdatePrivateSmsGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('smsGateway')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80'],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
        ];
    }
}
