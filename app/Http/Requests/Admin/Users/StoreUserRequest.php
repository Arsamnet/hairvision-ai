<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\Role;
use App\Support\NumberNormalizer;
use App\Support\StateCatalog;
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
        $roles = $this->allowedRoleCodes();

        return [
            'name' => ['required', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone_normalized' => ['nullable', 'digits:11', Rule::unique('users', 'phone_normalized')],
            'access_scope' => ['required', Rule::in($this->allowedScopes())],
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(StateCatalog::values('users.statuses'))],
            'tenant_id' => ['nullable', 'required_if:access_scope,clinic', 'integer', Rule::exists('tenants', 'id')],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
            'tenant_roles' => ['array'],
            'tenant_roles.*' => [Rule::in($this->clinicRoleCodes())],
            'tenant_statuses' => ['array'],
            'tenant_statuses.*' => [Rule::in(StateCatalog::values('users.statuses'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone_normalized')) {
            $this->merge([
                'phone_normalized' => NumberNormalizer::digitsOnly((string) $this->input('phone_normalized')),
            ]);
        }

        $role = (string) $this->input('role', 'viewer');
        $scope = $this->input('access_scope') ?: ($role === 'super_admin' ? 'system' : 'clinic');
        $tenantId = $this->input('tenant_id') ?: collect($this->input('tenant_ids', []))->first();
        $firstName = trim((string) $this->input('first_name', ''));
        $lastName = trim((string) $this->input('last_name', ''));
        $name = trim((string) $this->input('name', ''));

        $this->merge([
            'name' => $name !== '' ? $name : trim($firstName.' '.$lastName),
            'access_scope' => $scope,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function allowedScopes(): array
    {
        return $this->user()?->isSuperAdmin() ? ['system', 'clinic'] : ['clinic'];
    }

    /**
     * @return array<int, string>
     */
    private function allowedRoleCodes(): array
    {
        $scope = $this->input('access_scope') === 'system' ? 'platform' : 'clinic';
        $query = Role::query()->where('status', 'active')->where('scope', $scope);

        if (! $this->user()?->isSuperAdmin()) {
            $query->whereIn('code', ['clinic_admin', 'editor', 'viewer']);
        }

        return $query->pluck('code')->all();
    }

    /**
     * @return array<int, string>
     */
    private function clinicRoleCodes(): array
    {
        return Role::query()
            ->where('status', 'active')
            ->clinic()
            ->pluck('code')
            ->all();
    }
}
