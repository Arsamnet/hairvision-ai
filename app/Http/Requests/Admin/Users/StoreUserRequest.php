<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\Role;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates dashboard user creation.
 *
 * @since 1.0.0
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('users.create', $this->attributes->get('adminTenant')?->id) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roles = Role::query()->where('status', 'active')->pluck('code')->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone_normalized' => ['nullable', 'digits:11', Rule::unique('users', 'phone_normalized')],
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked', 'suspended', 'disabled'])],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
            'tenant_roles' => ['array'],
            'tenant_roles.*' => [Rule::in(array_values(array_diff($roles, ['super_admin'])))],
            'tenant_statuses' => ['array'],
            'tenant_statuses.*' => [Rule::in(['active', 'inactive'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone_normalized')) {
            $this->merge([
                'phone_normalized' => NumberNormalizer::digitsOnly((string) $this->input('phone_normalized')),
            ]);
        }
    }
}
