<?php

namespace App\Http\Requests\Api;

use App\Rules\SupportedDigits;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public visitor OTP request payload.
 *
 * @since 1.0.0
 */
class RequestOtpRequest extends FormRequest
{
    /**
     * Public tenant visitors may request OTP codes before authentication.
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
        if ($this->has('phone')) {
            $this->merge([
                'phone' => NumberNormalizer::toLatinDigits((string) $this->input('phone')),
            ]);
        }
    }
}
