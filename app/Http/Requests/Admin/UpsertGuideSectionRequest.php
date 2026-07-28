<?php

namespace App\Http\Requests\Admin;

use App\Rules\MeaningfulRichText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation and editing of one guide section.
 *
 * @since 2.0.1
 */
class UpsertGuideSectionRequest extends FormRequest
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
     * Return guide section validation rules.
     *
     * @return array<string, mixed> Section validation rules.
     *
     * @since 2.0.1
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'audience' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:10000', app(MeaningfulRichText::class)],
        ];
    }
}
