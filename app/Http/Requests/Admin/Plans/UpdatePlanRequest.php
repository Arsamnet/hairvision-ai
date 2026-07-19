<?php

namespace App\Http\Requests\Admin\Plans;

use App\Enums\PlanType;
use App\Services\Billing\ServiceCatalog;
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
        $serviceKeys = array_keys(app(ServiceCatalog::class)->all());

        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'plan_type' => ['required', Rule::in(array_map(fn (PlanType $type): string => $type->value, PlanType::cases()))],
            'service_key' => ['nullable', Rule::in($serviceKeys)],
            'price' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'max:8'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'usage_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'service_keys' => ['required', 'array', 'min:1'],
            'service_keys.*' => ['required', Rule::in($serviceKeys)],
            'service_credits' => ['nullable', 'array'],
            'service_credits.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_public' => ['nullable', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $planType = $this->string('plan_type')->toString();

            if ($planType !== PlanType::CreditTopUp->value && ! $this->filled('duration_days')) {
                $validator->errors()->add('duration_days', __('plans.validation.duration_required'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $serviceCredits = $this->input('service_credits');

        $serviceKeys = $this->input('service_keys');

        if (! is_array($serviceKeys) || $serviceKeys === []) {
            $serviceKeys = collect(is_array($serviceCredits) ? $serviceCredits : [])
                ->filter(fn (mixed $credit): bool => (int) $credit > 0)
                ->keys()
                ->values()
                ->all();
        }

        if ($serviceKeys === []) {
            $serviceKeys = [$this->input('service_key', config('hairvision.billing.default_service_key', 'hair_transplant'))];
        }

        if (! is_array($serviceCredits) || $serviceCredits === []) {
            $serviceCredits = [
                $this->input('service_key', config('hairvision.billing.default_service_key', 'hair_transplant')) => $this->input('usage_limit', 0),
            ];
        }

        if (! $this->filled('usage_limit')) {
            $this->merge([
                'usage_limit' => collect($serviceCredits)->sum(fn (mixed $credit): int => (int) $credit),
            ]);
        }

        $this->merge([
            'plan_type' => $this->input('plan_type', PlanType::BaseSubscription->value),
            'service_keys' => $serviceKeys,
            'service_credits' => $serviceCredits,
            'is_public' => $this->boolean('is_public'),
        ]);
    }
}
