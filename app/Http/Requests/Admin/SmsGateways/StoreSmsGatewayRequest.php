<?php

namespace App\Http\Requests\Admin\SmsGateways;

use App\Models\SmsGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates system SMS gateway creation.
 *
 * @since 1.0.0
 */
class StoreSmsGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', SmsGateway::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80'],
            'owner_scope' => ['required', Rule::in(['system', 'user'])],
            'user_id' => ['nullable', 'required_if:owner_scope,user', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
        ];
    }
}
