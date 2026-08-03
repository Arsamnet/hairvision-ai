<?php

namespace App\Http\Requests\Admin\Reports;

use App\Rules\JalaliDate as JalaliDateRule;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a clinic performance report date range.
 *
 * @since 2.1.0
 */
class PerformanceReportRequest extends FormRequest
{
    /**
     * Authorize users granted access to clinic performance reports.
     *
     * @return bool Whether the request is authorized.
     *
     * @since 2.1.0
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(
            'reports.user.view',
            $this->attributes->get('adminTenant')?->id,
        ) ?? false;
    }

    /**
     * Return report range validation rules.
     *
     * @return array<string, mixed> Validation rules.
     *
     * @since 2.1.0
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', new JalaliDateRule],
            'date_until' => ['nullable', new JalaliDateRule],
            'clinic_id' => [
                Rule::prohibitedIf(! ($this->user()?->isSystemScoped() ?? false)),
                'nullable',
                'integer',
                Rule::exists('tenants', 'id'),
            ],
        ];
    }

    /**
     * Ensure the requested end date does not precede the start date.
     *
     * @param  Validator  $validator  Laravel validator instance.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->string('date_from')->toString();
            $until = $this->string('date_until')->toString();

            if ($from === '' || $until === '' || $validator->errors()->isNotEmpty()) {
                return;
            }

            if (JalaliDate::fromJalaliDate($until)?->lt(JalaliDate::fromJalaliDate($from))) {
                $validator->errors()->add('date_until', __('reports.validation.invalid_range'));
            }
        });
    }

    /**
     * Normalize supported digits and separators before date validation.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    protected function prepareForValidation(): void
    {
        $from = $this->input('date_from');
        $until = $this->input('date_until');

        $this->merge([
            'date_from' => is_string($from) ? JalaliDate::normalizeDate($from) : $from,
            'date_until' => is_string($until) ? JalaliDate::normalizeDate($until) : $until,
        ]);
    }
}
