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
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', new SupportedDigits(__('auth.invalid_phone')), 'digits:11'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => __('auth.phone_required'),
            'phone.string' => __('auth.phone_string'),
            'phone.digits' => __('auth.invalid_phone'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => NumberNormalizer::toLatinDigits((string) $this->input('phone')),
            ]);
        }
    }
}
