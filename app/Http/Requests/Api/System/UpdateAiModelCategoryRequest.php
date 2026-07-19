<?php

namespace App\Http\Requests\Api\System;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiModelCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'archived'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:100'],
            'card_image_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
