<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an adjacent guide-content ordering action.
 *
 * @since 2.0.1
 */
class MoveGuideContentRequest extends FormRequest
{
    /**
     * Determine whether the user may manage help content.
     *
     * @return bool Whether the request is authorized.
     *
     * @since 2.0.1
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('guide.manage') ?? false;
    }

    /**
     * Return adjacent ordering validation rules.
     *
     * @return array<string, mixed> Ordering validation rules.
     *
     * @since 2.0.1
     */
    public function rules(): array
    {
        return ['direction' => ['required', Rule::in(['up', 'down'])]];
    }
}
