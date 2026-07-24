<?php

namespace App\Http\Requests\Api\System;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates update ai model category output input.
 *
 * @since 1.0.0
 */
class UpdateAiModelCategoryOutputRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prompt' => ['sometimes', 'required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'credit_cost' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
