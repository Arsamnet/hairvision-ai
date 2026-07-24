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
            'upload_id' => ['required_without:source_image_id', 'uuid'],
            'usage_category_key' => [
                'sometimes',
                'string',
                Rule::in(array_keys(config('hairvision.generation.services', ['hair_transplant' => []]))),
            ],
            'source_image_id' => ['required_without:upload_id', 'uuid'],
            'category_id' => ['required_with:source_image_id', 'uuid'],
            'output_id' => ['required_with:source_image_id', 'uuid'],
            'idempotency_key' => ['required_with:source_image_id', 'uuid'],
        ];
    }

    /**
     * Normalize request input before validation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('source_image_id') && ! $this->filled('idempotency_key') && $this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
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
            'upload_id.required' => __('messages.upload_id_required'),
            'upload_id.uuid' => __('messages.upload_id_invalid'),
            'usage_category_key.string' => __('messages.generation_service_invalid'),
            'usage_category_key.in' => __('messages.generation_service_invalid'),
        ];
    }
}
