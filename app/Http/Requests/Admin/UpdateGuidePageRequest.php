<?php

namespace App\Http\Requests\Admin;

use App\Rules\MeaningfulRichText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates independently editable guide or FAQ page metadata.
 *
 * @since 2.0.1
 */
class UpdateGuidePageRequest extends FormRequest
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
     * Return page metadata validation rules.
     *
     * @return array<string, mixed> Page validation rules.
     *
     * @since 2.0.1
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'introduction' => ['required', 'string', 'max:10000', app(MeaningfulRichText::class)],
        ];
    }
}
