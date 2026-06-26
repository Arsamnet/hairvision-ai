<?php

namespace App\Http\Requests\Admin\Leads;

use App\Models\Lead;
use App\Support\AdminDashboardFormat;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates dashboard lead creation while policies enforce tenant permissions.
 *
 * @since 1.1.0
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['nullable', 'string', 'max:120'],
            'phone_normalized' => ['required', 'digits:11'],
            'source' => ['nullable', 'string', 'max:80'],
            'status' => ['required', 'in:new,verified,contacted,qualified,archived'],
            'crm_stage' => ['required', 'in:new_lead,needs_follow_up,consultation_booked,converted,lost'],
            'assigned_user_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone_normalized')) {
            $this->merge([
                'phone_normalized' => preg_replace('/[^0-9]/', '', AdminDashboardFormat::normalizeDigits((string) $this->input('phone_normalized'))),
            ]);
        }
    }
}
