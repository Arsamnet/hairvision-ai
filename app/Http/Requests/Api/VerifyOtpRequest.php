<?php

namespace App\Http\Requests\Api;

use App\Rules\SupportedDigits;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public visitor OTP verification payload.
 *
 * @since 1.0.0
 */
class VerifyOtpRequest extends FormRequest
{
    /**
     * Public tenant visitors may verify OTP codes before authentication.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, array<int, string>>
     *
     * @since 1.0.0
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SupportedDigits(__('auth.invalid_phone')), 'digits:11'],
            'code' => ['required', 'string', new SupportedDigits(__('auth.invalid_code')), 'digits:6'],
        ];
    }

    /**
     * Return localized validation messages.
     *
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function messages(): array
    {
        return [
            'phone.required' => __('auth.phone_required'),
            'phone.string' => __('auth.phone_string'),
            'phone.digits' => __('auth.invalid_phone'),
            'code.required' => __('auth.code_required'),
            'code.string' => __('auth.code_string'),
            'code.digits' => __('auth.invalid_code'),
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
        $payload = [];

        if ($this->has('phone')) {
            $payload['phone'] = NumberNormalizer::toLatinDigits((string) $this->input('phone'));
        }

        if ($this->has('code')) {
            $payload['code'] = NumberNormalizer::toLatinDigits((string) $this->input('code'));
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }
}
