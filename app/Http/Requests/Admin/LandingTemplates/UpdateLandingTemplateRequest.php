<?php

namespace App\Http\Requests\Admin\LandingTemplates;

use App\Rules\InlineLandingTemplateHtml;
use App\Rules\PrimaryLandingAssetPath;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates updates to a centrally managed landing template.
 *
 * @since 2.0.0
 */
class UpdateLandingTemplateRequest extends FormRequest
{
    /**
     * Determine whether the current user may update landing templates.
     *
     * @return bool Whether updating is authorized.
     *
     * @since 2.0.0
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('landing_templates.update') ?? false;
    }

    /**
     * Return landing template update validation rules.
     *
     * @return array<string, mixed> Validation rules.
     *
     * @since 2.0.0
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'favicon_path' => ['nullable', 'string', 'max:2048', new PrimaryLandingAssetPath],
            'consent_text' => ['required', 'string', 'max:5000'],
            'header_html' => ['required', 'string', 'max:250000', new InlineLandingTemplateHtml],
            'body_html' => ['nullable', 'string', 'max:250000', new InlineLandingTemplateHtml],
            'footer_html' => ['required', 'string', 'max:250000', new InlineLandingTemplateHtml],
            'back_link_title' => ['nullable', 'string', 'max:150', 'required_with:back_link_url'],
            'back_link_url' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Normalize the active-state control before validation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
