<?php

namespace App\Http\Requests\Admin\Clinics;

use App\Rules\JalaliDateTime;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates manual clinic invoice updates by billing managers.
 *
 * @since 1.0.0
 */
class UpdateClinicInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('billing.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '_form' => ['nullable', 'string'],
            'plan_id' => ['nullable', Rule::exists('plans', 'id')->where('status', 'active')],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('status', 'active')],
            'amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'status' => ['required', Rule::in(StateCatalog::values('invoices.statuses'))],
            'due_at' => ['nullable', new JalaliDateTime],
            'expires_at' => ['nullable', new JalaliDateTime],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            '_form' => $this->input('_form', 'edit_clinic_invoice_'.$this->route('invoice')?->id),
            'currency' => $this->input('currency', 'ریال'),
        ]);
    }
}
