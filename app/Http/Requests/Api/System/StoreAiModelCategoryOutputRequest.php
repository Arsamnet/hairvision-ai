<?php

namespace App\Http\Requests\Api\System;

use App\Models\AiModelCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates store ai model category output input.
 *
 * @since 1.0.0
 */
class StoreAiModelCategoryOutputRequest extends FormRequest
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
        $categoryUuid = (string) $this->route('category');
        $categoryId = AiModelCategory::query()->where('uuid', $categoryUuid)->value('id');

        return [
            'code' => [
                'required',
                'string',
                'max:80',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('ai_model_category_outputs', 'code')->where('ai_model_category_id', $categoryId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'prompt' => ['required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'credit_cost' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
