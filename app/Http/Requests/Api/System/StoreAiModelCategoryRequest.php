<?php

namespace App\Http\Requests\Api\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiModelCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', 'unique:ai_model_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'card_image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
