<?php

namespace App\Http\Requests\Api\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates store ai model input.
 *
 * @since 1.0.0
 */
class StoreAiModelRequest extends FormRequest
{
    /**
     * Determine whether the current user may submit this request.
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
     * @return array Resolved data array.
     *
     * @since 1.0.0
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'uuid'],
            'type' => ['required', 'string', Rule::in(array_keys(config('ai-models.types', [])))],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['draft', 'active', 'paused', 'degraded', 'archived'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'concurrency_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'settings' => ['nullable', 'array'],
            'credentials' => ['nullable', 'array'],
            'output_configs' => ['required', 'array', 'min:1'],
            'output_configs.*.output_id' => ['required', 'uuid'],
            'output_configs.*.is_active' => ['nullable', 'boolean'],
            'output_configs.*.configuration' => ['nullable', 'array'],
        ];
    }
}
