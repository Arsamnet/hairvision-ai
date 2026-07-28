<?php

namespace App\Http\Requests\Admin;

use App\Rules\MeaningfulRichText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creation and editing of one frequently asked question.
 *
 * @since 2.0.1
 */
class UpsertGuideFaqRequest extends FormRequest
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
     * Return frequently asked question validation rules.
     *
     * @return array<string, mixed> FAQ validation rules.
     *
     * @since 2.0.1
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:20000', app(MeaningfulRichText::class)],
        ];
    }
}
