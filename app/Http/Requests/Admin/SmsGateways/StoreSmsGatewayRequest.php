<?php

namespace App\Http\Requests\Admin\SmsGateways;

use App\Models\SmsGateway;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates system SMS gateway creation.
 *
 * @since 1.0.0
 */
class StoreSmsGatewayRequest extends FormRequest
{
    /**
     * Determine whether the current user may submit this request.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', SmsGateway::class) ?? false;
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'provider_code' => ['required', 'alpha_dash', 'max:80', Rule::in($this->providerCodes())],
            'owner_scope' => ['required', Rule::in(StateCatalog::values('sms_gateways.owner_scopes'))],
            'tenant_id' => [
                'nullable',
                'required_if:owner_scope,tenant',
                Rule::exists('tenants', 'id'),
                Rule::unique('sms_gateways', 'tenant_id')->where(fn ($query) => $query->where('owner_scope', 'tenant')),
            ],
            'status' => ['required', Rule::in(StateCatalog::values('sms_gateways.statuses'))],
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
     * Execute the provider codes operation.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function providerCodes(): array
    {
        return array_values(array_unique(array_merge(
            ['local', 'ippanel'],
            SmsGateway::query()->system()->enabled()->pluck('provider_code')->all()
        )));
    }
}
