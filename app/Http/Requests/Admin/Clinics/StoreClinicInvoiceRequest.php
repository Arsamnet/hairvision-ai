<?php

namespace App\Http\Requests\Admin\Clinics;

use App\Rules\JalaliDateTime;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates manual clinic invoice creation by billing managers.
 *
 * @since 1.0.0
 */
class StoreClinicInvoiceRequest extends FormRequest
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
        return $this->user()?->hasPermission('billing.manage') ?? false;
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

    /**
     * Normalize request input before validation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            '_form' => $this->input('_form', 'create_clinic_invoice'),
            'currency' => $this->input('currency', 'ریال'),
            'status' => $this->input('status', 'pending'),
        ]);
    }
}
