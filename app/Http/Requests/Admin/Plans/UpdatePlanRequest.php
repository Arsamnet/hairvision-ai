<?php

namespace App\Http\Requests\Admin\Plans;

use App\Enums\PlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $serviceKeys = array_keys(config('hairvision.ai_services', []));

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'plan_type' => ['required', Rule::in(array_map(fn (PlanType $type): string => $type->value, PlanType::cases()))],
            'service_key' => ['nullable', Rule::in($serviceKeys)],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'usage_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'service_credits' => ['required', 'array'],
            'service_credits.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $planType = $this->string('plan_type')->toString();
            $credits = collect($this->input('service_credits', []))->map(fn (mixed $value): int => (int) $value);

            if ($planType !== PlanType::CreditTopUp->value && ! $this->filled('duration_days')) {
                $validator->errors()->add('duration_days', __('plans.validation.duration_required'));
            }

            if ($credits->sum() < 1) {
                $validator->errors()->add('service_credits', __('plans.validation.service_credit_required'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $serviceCredits = $this->input('service_credits');

        if (! is_array($serviceCredits) || $serviceCredits === []) {
            $serviceCredits = [
                $this->input('service_key', config('hairvision.billing.default_service_key', 'hair_transplant')) => $this->input('usage_limit', 0),
            ];
        }

        $this->merge([
            'plan_type' => $this->input('plan_type', PlanType::BaseSubscription->value),
            'service_credits' => $serviceCredits,
        ]);
    }
}
