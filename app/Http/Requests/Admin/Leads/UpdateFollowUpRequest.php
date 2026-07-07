<?php

namespace App\Http\Requests\Admin\Leads;

use App\Rules\JalaliDateTime;
use App\Support\JalaliDate;
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

    protected function prepareForValidation(): void
    {
        if ($this->filled('next_follow_up_at')) {
            $this->merge([
                'next_follow_up_at' => JalaliDate::normalizeDateTime((string) $this->input('next_follow_up_at')),
            ]);
        }
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
            'next_follow_up_at' => ['nullable', new JalaliDateTime],
            'is_completed' => ['nullable', 'boolean'],
        ];
    }
}
