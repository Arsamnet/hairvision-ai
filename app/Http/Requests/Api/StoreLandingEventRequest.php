<?php

namespace App\Http\Requests\Api;

use App\Support\Analytics\LandingEventCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one anonymous, PII-free landing analytics event.
 *
 * @since 2.1.0
 */
class StoreLandingEventRequest extends FormRequest
{
    /**
     * Allow public event submission after host-based tenant resolution.
     *
     * @return bool Whether the request is authorized.
     *
     * @since 2.1.0
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return the public analytics event validation rules.
     *
     * @return array<string, mixed> Validation rules.
     *
     * @since 2.1.0
     */
    public function rules(): array
    {
        return [
            'visit_token' => ['required', 'string', 'size:64'],
            'event_id' => ['required', 'uuid'],
            'event_type' => ['required', 'string', Rule::in(LandingEventCatalog::all())],
            'category_id' => ['nullable', 'uuid'],
            'output_id' => ['nullable', 'uuid'],
        ];
    }
}
