<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\Role;
use App\Support\NumberNormalizer;
use App\Support\StateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates dashboard user updates.
 *
 * @since 1.0.0
 */
class UpdateUserRequest extends FormRequest
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
        return $this->user()?->can('update', $this->route('user')) ?? false;
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
        $roles = $this->allowedRoleCodes();
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($userId)],
            'phone_normalized' => ['nullable', 'digits:11', Rule::unique('users', 'phone_normalized')->ignore($userId)],
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

        $target = $this->route('user');
        $role = (string) $this->input('role', $target?->role ?? 'viewer');
        $scope = $this->input('access_scope') ?: ($target?->access_scope ?? ($role === 'super_admin' ? 'system' : 'clinic'));
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
     * Execute the allowed scopes operation.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    private function allowedScopes(): array
    {
        return $this->user()?->isSuperAdmin() ? ['system', 'clinic'] : ['clinic'];
    }

    /**
     * Execute the allowed role codes operation.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
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
     * Execute the clinic role codes operation.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
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
