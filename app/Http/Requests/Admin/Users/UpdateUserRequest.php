<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\Role;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates dashboard user updates.
 *
 * @since 1.0.0
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $roles = $this->allowedRoleCodes();
        $clinicRoles = $this->clinicRoleCodes();
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($userId)],
            'phone_normalized' => ['nullable', 'digits:11', Rule::unique('users', 'phone_normalized')->ignore($userId)],
            'role' => ['required', Rule::in($roles)],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked', 'suspended', 'disabled'])],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')],
            'tenant_roles' => ['array'],
            'tenant_roles.*' => [Rule::in($clinicRoles)],
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

    /**
     * @return array<int, string>
     */
    private function allowedRoleCodes(): array
    {
        $query = Role::query()->where('status', 'active');

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
