<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates independent v2 generation creation.
 *
 * @since 2.0.0
 */
class StoreIndependentGenerationRequest extends FormRequest
{
    /**
     * Determine whether the current user may submit this request.
     *
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the validation rules for the request.
     *
     * @return array<string, array<int, mixed>>
     *
     * @since 2.0.0
     */
    public function rules(): array
    {
        return [
            'source_image_id' => ['required', 'uuid'],
            'category_id' => ['required', 'uuid'],
            'output_id' => ['required', 'uuid'],
            'idempotency_key' => ['nullable', 'uuid', 'required_without:header_idempotency_key'],
            'header_idempotency_key' => ['nullable'],
        ];
    }

    /**
     * Execute the with validator operation.
     *
     * @param  Validator  $validator  The validator value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = ['source_image_id', 'category_id', 'output_id', 'idempotency_key', 'header_idempotency_key'];
            $unknown = array_diff(array_keys($this->all()), $allowed);

            foreach ($unknown as $key) {
                $validator->errors()->add($key, __('validation.prohibited', ['attribute' => $key]));
            }
        });
    }

    /**
     * Normalize request input before validation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    protected function prepareForValidation(): void
    {
        $header = $this->header('Idempotency-Key');

        $this->merge([
            'header_idempotency_key' => $header,
            'idempotency_key' => $this->input('idempotency_key') ?: $header,
        ]);
    }
}
