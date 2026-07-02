<?php

namespace App\Http\Requests\Admin\SmsGateways;

use App\Models\SmsGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates system SMS gateway updates.
 *
 * @since 1.0.0
 */
class UpdateSmsGatewayRequest extends FormRequest
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
            'provider_code' => ['required', 'alpha_dash', 'max:80', Rule::in($this->providerCodes())],
            'owner_scope' => ['required', Rule::in(['system', 'user'])],
            'user_id' => ['nullable', 'required_if:owner_scope,user', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(['enabled', 'disabled'])],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'sender' => ['nullable', 'string', 'max:120'],
            'from_number' => ['nullable', 'string', 'max:120'],
            'otp_pattern_code' => ['nullable', 'string', 'max:120'],
            'otp_pattern_variable' => ['nullable', 'string', 'max:120'],
            'endpoint_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function providerCodes(): array
    {
        $gateway = $this->route('smsGateway');
        $current = $gateway instanceof SmsGateway ? [$gateway->provider_code] : [];

        return array_values(array_unique(array_merge(
            ['local', 'ippanel'],
            SmsGateway::query()->system()->enabled()->pluck('provider_code')->all(),
            $current
        )));
    }
}
