<?php

namespace App\Http\Requests\Admin\Clinics;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates direct Super Admin plan assignment for a clinic user.
 *
 * @since 1.0.0
 */
class AssignClinicPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isSuperAdmin() === true && $user->hasPermission('billing.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '_form' => ['nullable', 'string'],
            'plan_id' => [
                'required',
                Rule::exists('plans', 'id')->where('status', 'active'),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! Plan::query()->whereKey((int) $this->input('plan_id'))->where('status', 'active')->exists()) {
                $validator->errors()->add('plan_id', __('clinics.validation.plan_not_active'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $clinic = $this->route('clinic');
        $clinicId = is_object($clinic) && method_exists($clinic, 'getKey') ? (int) $clinic->getKey() : (int) $clinic;

        $this->merge([
            '_form' => $this->input('_form', 'assign_clinic_plan_'.$clinicId),
        ]);
    }
}
