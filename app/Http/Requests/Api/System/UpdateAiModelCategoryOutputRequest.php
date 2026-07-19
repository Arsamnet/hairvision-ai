<?php

namespace App\Http\Requests\Api\System;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAiModelCategoryOutputRequest extends FormRequest
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
            'prompt' => ['sometimes', 'required', 'string', 'max:20000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'credit_cost' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
