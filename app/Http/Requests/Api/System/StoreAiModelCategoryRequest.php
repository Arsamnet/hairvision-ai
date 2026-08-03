<?php

namespace App\Http\Requests\Api\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates store ai model category input.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class StoreAiModelCategoryRequest extends FormRequest
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:ai_model_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'upload_guide_html' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'card_image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
