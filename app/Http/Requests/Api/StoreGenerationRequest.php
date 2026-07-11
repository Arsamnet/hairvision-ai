<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of a public generation group.
 *
 * @since 1.0.0
 */
class StoreGenerationRequest extends FormRequest
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
            'upload_id' => ['required', 'uuid'],
            'usage_category_key' => [
                'sometimes',
                'string',
                Rule::in(array_keys(config('hairvision.generation.services', ['hair_transplant' => []]))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'upload_id.required' => __('messages.upload_id_required'),
            'upload_id.uuid' => __('messages.upload_id_invalid'),
            'usage_category_key.string' => __('messages.generation_service_invalid'),
            'usage_category_key.in' => __('messages.generation_service_invalid'),
        ];
    }
}
