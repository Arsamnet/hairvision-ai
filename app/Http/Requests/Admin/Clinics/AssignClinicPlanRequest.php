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
    /**
     * Determine whether the current user may submit this request.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->isSuperAdmin() === true && $user->hasPermission('billing.manage');
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
            'plan_id' => [
                'required',
                Rule::exists('plans', 'id')->where('status', 'active'),
            ],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Execute the with validator operation.
     *
     * @param  Validator  $validator  The validator value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! Plan::query()->whereKey((int) $this->input('plan_id'))->where('status', 'active')->exists()) {
                $validator->errors()->add('plan_id', __('clinics.validation.plan_not_active'));
            }
        });
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
        $clinic = $this->route('clinic');
        $clinicId = is_object($clinic) && method_exists($clinic, 'getKey') ? (int) $clinic->getKey() : (int) $clinic;

        $this->merge([
            '_form' => $this->input('_form', 'assign_clinic_plan_'.$clinicId),
            'discount_amount' => $this->input('discount_amount', 0),
        ]);
    }
}
