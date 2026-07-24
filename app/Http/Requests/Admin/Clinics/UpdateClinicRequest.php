<?php

namespace App\Http\Requests\Admin\Clinics;

use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates clinic updates from the admin dashboard.
 *
 * @since 1.0.0
 */
class UpdateClinicRequest extends FormRequest
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
        return $this->user()?->can('update', $this->route('clinic')) ?? false;
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
        $clinic = $this->route('clinic');
        $domain = $clinic?->domains()->orderByDesc('status')->orderBy('id')->first();

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:100', Rule::unique('tenants', 'slug')->ignore($clinic?->id)],
            'status' => ['required', 'string', Rule::in(StateCatalog::values('tenants.statuses'))],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', Rule::unique('tenant_domains', 'domain')->ignore($domain?->id)],
            'domain_type' => ['required', 'string', Rule::in(StateCatalog::values('tenant_domains.types'))],
            'domain_status' => ['required', 'string', Rule::in(StateCatalog::values('tenant_domains.statuses'))],
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
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'domain' => strtolower(trim((string) $this->input('domain'))),
            'status' => $this->input('status', 'active'),
            'domain_type' => $this->input('domain_type', 'tenant_subdomain'),
            'domain_status' => $this->input('domain_status', 'active'),
        ]);
    }
}
