<?php

namespace App\Http\Requests\Admin\AiModels;

use App\Models\AiModelAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates assigning an AI model access record to a clinic.
 *
 * @since 1.0.0
 */
class StoreClinicAiModelAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assignToClinic', AiModelAccess::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ai_model_access_id' => ['required', 'integer', Rule::exists('ai_model_accesses', 'id')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
