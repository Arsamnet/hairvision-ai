<?php

namespace App\Http\Requests\Admin\Leads;

use App\Models\Lead;
use App\Models\Tenant;
use App\Support\NumberNormalizer;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates dashboard lead creation while policies enforce tenant permissions.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class StoreLeadRequest extends FormRequest
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
        return $this->user()?->can('create', Lead::class) ?? false;
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function rules(): array
    {
        $tenant = $this->attributes->get('adminTenant');
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : null;

        return [
            'full_name' => ['nullable', 'string', 'max:120'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone_normalized' => [
                'required',
                'digits:11',
                Rule::unique('leads', 'phone_normalized')->where('tenant_id', $tenantId),
            ],
            'source' => ['nullable', 'string', 'max:80'],
            'status' => ['required', Rule::in(StateCatalog::values('leads.statuses'))],
            'assigned_user_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
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
        if ($this->has('phone_normalized')) {
            $this->merge([
                'phone_normalized' => NumberNormalizer::digitsOnly((string) $this->input('phone_normalized')),
            ]);
        }
    }
}
