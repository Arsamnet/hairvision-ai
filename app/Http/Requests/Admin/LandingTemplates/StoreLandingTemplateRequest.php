<?php

namespace App\Http\Requests\Admin\LandingTemplates;

use App\Rules\InlineLandingTemplateHtml;
use App\Rules\PrimaryLandingAssetPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of a centrally managed landing template.
 *
 * @since 2.0.0
 */
class StoreLandingTemplateRequest extends FormRequest
{
    /**
     * Determine whether the current user may create landing templates.
     *
     * @return bool Whether creation is authorized.
     *
     * @since 2.0.0
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('landing_templates.create') ?? false;
    }

    /**
     * Return landing template creation validation rules.
     *
     * @return array<string, mixed> Validation rules.
     *
     * @since 2.0.0
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'alpha_dash:ascii', 'max:100', Rule::unique('templates', 'code')],
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
     * Normalize stable template identifiers and checkbox values before validation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtolower(trim((string) $this->input('code'))),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
