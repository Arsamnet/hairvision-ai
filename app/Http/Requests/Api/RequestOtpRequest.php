<?php

namespace App\Http\Requests\Api;

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
            'phone' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'شماره موبایل را وارد کنید.',
            'phone.string' => 'شماره موبایل باید به صورت متن عددی ارسال شود.',
        ];
    }
}
