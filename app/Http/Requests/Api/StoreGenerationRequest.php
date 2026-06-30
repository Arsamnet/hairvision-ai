<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation of a three-period generation group.
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
        ];
    }
}
