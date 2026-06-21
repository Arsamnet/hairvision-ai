<?php

namespace App\Http\Requests\Api;

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
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
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
            'code.required' => 'کد تایید را وارد کنید.',
            'code.string' => 'کد تایید باید به صورت متن عددی ارسال شود.',
        ];
    }
}
