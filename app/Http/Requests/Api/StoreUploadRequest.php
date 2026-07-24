<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the private visitor image upload request.
 *
 * @since 1.0.0
 */
class StoreUploadRequest extends FormRequest
{
    /**
     * Session lead authorization is enforced after validation in the controller
     * through tenant-scoped services.
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
            'image' => ['required', 'image', 'max:5120'],
            'consent' => ['accepted'],
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
            'image.required' => __('messages.upload_image_required'),
            'image.image' => __('messages.upload_image_invalid'),
            'image.max' => __('messages.upload_image_max'),
            'consent.accepted' => __('messages.upload_consent_required'),
        ];
    }
}
