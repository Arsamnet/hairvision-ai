<?php

namespace App\Http\Requests\Admin\Auth;

use App\Rules\SupportedDigits;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates dashboard mobile OTP request input.
 *
 * @since 1.1.0
 */
class RequestAdminOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
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
