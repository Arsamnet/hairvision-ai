<?php

namespace App\Http\Requests\Admin\Leads;

use App\Support\NumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates dashboard lead updates while LeadPolicy gates the target record.
 *
 * @since 1.0.0
 */
class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone_normalized' => ['sometimes', 'required', 'digits:11'],
            'source' => ['nullable', 'string', 'max:80'],
            'status' => ['sometimes', 'required', 'in:new,verified,contacted,qualified,archived'],
            'crm_stage' => ['sometimes', 'required', Rule::in(array_keys(__('leads.crm_stages')))],
            'assigned_user_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone_normalized')) {
            $this->merge([
                'phone_normalized' => NumberNormalizer::digitsOnly((string) $this->input('phone_normalized')),
            ]);
        }
    }
}
