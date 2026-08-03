<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a v2 profile source image upload.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class StoreSourceImageRequest extends FormRequest
{
    /**
     * Determine whether the current user may submit this request.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, array<int, mixed>>
     *
     * @since 2.0.0
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:5120'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * Return localized validation messages for public source-image uploads.
     *
     * @return array<string, string> Validation messages keyed by rule.
     *
     * @since 2.1.0
     */
    public function messages(): array
    {
        return [
            'image.max' => 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.',
        ];
    }
}
