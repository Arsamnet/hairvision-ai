<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates updates to an existing CRM follow-up entry.
 *
 * @since 1.0.0
 */
class UpdateFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateNote', [$this->route('lead'), $this->route('leadNote')]) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::in(array_keys(__('leads.crm_stages')))],
            'note' => ['sometimes', 'required', 'string', 'max:2000'],
            'communication_channel' => ['nullable', Rule::in(array_keys(__('crm.channels')))],
            'next_follow_up_at' => ['nullable', 'date'],
            'is_completed' => ['nullable', 'boolean'],
        ];
    }
}
