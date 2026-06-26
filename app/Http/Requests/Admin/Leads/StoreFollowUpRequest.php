<?php

namespace App\Http\Requests\Admin\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates CRM follow-up entries without requiring full lead edits.
 *
 * @since 1.0.0
 */
class StoreFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createNote', $this->route('lead')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(__('leads.crm_stages')))],
            'note' => ['required', 'string', 'max:2000'],
            'communication_channel' => ['nullable', Rule::in(array_keys(__('crm.channels')))],
            'next_follow_up_at' => ['nullable', 'date'],
            'is_completed' => ['nullable', 'boolean'],
        ];
    }
}
