<?php

namespace App\Http\Requests\Admin\Leads;

use App\Rules\JalaliDateTime;
use App\Support\JalaliDate;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates CRM follow-up entries without requiring full lead edits.
 *
 * @since 1.0.0
 */
class StoreFollowUpRequest extends FormRequest
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
        return $this->user()?->can('createNote', $this->route('lead')) ?? false;
    }

    /**
     * Normalize request input before validation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('next_follow_up_at')) {
            $this->merge([
                'next_follow_up_at' => JalaliDate::normalizeDateTime((string) $this->input('next_follow_up_at')),
            ]);
        }
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(StateCatalog::values('leads.crm_stages'))],
            'note' => ['required', 'string', 'max:2000'],
            'communication_channel' => ['nullable', Rule::in(StateCatalog::values('lead_notes.communication_channels'))],
            'next_follow_up_at' => ['nullable', new JalaliDateTime],
            'is_completed' => ['nullable', 'boolean'],
        ];
    }
}
