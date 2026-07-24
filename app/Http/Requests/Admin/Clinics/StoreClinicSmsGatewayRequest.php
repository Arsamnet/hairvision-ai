<?php

namespace App\Http\Requests\Admin\Clinics;

use App\Models\SmsGateway;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates clinic-scoped SMS gateway settings.
 *
 * @since 1.0.0
 */
class StoreClinicSmsGatewayRequest extends FormRequest
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
        $user = $this->user();
        $clinic = $this->route('clinic');

        return ($user?->hasAnyPermission(['sms_gateways.manage', 'sms_gateways.edit']) ?? false)
            && ($user?->isSuperAdmin() || $user?->canAccessTenant($clinic?->id));
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
            '_form' => ['nullable', 'string'],
            'name' => ['nullable', 'string', 'max:120'],
            'provider_code' => ['nullable', 'alpha_dash', 'max:80', Rule::in($this->providerCodes())],
            'status' => ['required', Rule::in(StateCatalog::values('sms_gateways.statuses'))],
            'is_default' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'from_number' => ['nullable', 'string', 'max:120'],
            'sender' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Normalize request input before validation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        $providerCodes = $this->providerCodes();

        $this->merge([
            '_form' => $this->input('_form', 'clinic_sms_gateway'),
            'name' => $this->input('name') ?: __('sms.clinic_gateway_name'),
            'provider_code' => $this->input('provider_code') ?: ($providerCodes[0] ?? 'ippanel'),
            'status' => $this->boolean('is_active') ? 'enabled' : 'disabled',
            'is_default' => true,
        ]);
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
        $codes = SmsGateway::query()->system()->enabled()->pluck('provider_code')->all();

        return $codes !== []
            ? array_values(array_unique($codes))
            : ['local', 'ippanel'];
    }
}
