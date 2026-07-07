<?php

namespace App\Http\Requests\Admin\Clinics;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates clinic creation from the admin dashboard.
 *
 * @since 1.0.0
 */
class StoreClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tenant::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'alpha_dash', 'max:100', Rule::unique('tenants', 'slug')],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', Rule::unique('tenant_domains', 'domain')],
            'domain_type' => ['required', 'string', Rule::in(['tenant_subdomain', 'custom_domain', 'primary_domain', 'platform_subdomain'])],
            'domain_status' => ['required', 'string', Rule::in(['active', 'inactive', 'pending'])],
        ];
    }

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
