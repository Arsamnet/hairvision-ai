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
            'image' => ['required', 'image', 'max:5120'],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
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
