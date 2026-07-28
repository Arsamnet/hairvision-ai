<?php

namespace App\Http\Requests\Admin;

use App\Rules\MeaningfulRichText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation and editing of one actionable guide step.
 *
 * @since 2.0.1
 */
class UpsertGuideStepRequest extends FormRequest
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
     * Return guide step validation rules.
     *
     * @return array<string, mixed> Step validation rules.
     *
     * @since 2.0.1
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000', app(MeaningfulRichText::class)],
        ];
    }
}
