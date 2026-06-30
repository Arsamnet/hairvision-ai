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
            'code' => ['required', 'string', new SupportedDigits(__('auth.invalid_code')), 'digits:6'],
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
            'code.required' => __('auth.code_required'),
            'code.string' => __('auth.code_string'),
            'code.digits' => __('auth.invalid_code'),
        ];
    }

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
