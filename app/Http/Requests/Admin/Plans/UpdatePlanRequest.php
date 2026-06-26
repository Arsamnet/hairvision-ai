<?php

namespace App\Http\Requests\Admin\Plans;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates plan definition updates for authorized plan managers.
 *
 * @since 1.0.0
 */
class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['plans.edit', 'plans.manage']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'usage_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
