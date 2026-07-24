<?php

namespace App\Http\Requests\Admin\LandingTemplates;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates assigning an active landing template to a clinic.
 *
 * @since 2.0.0
 */
class AssignLandingTemplateRequest extends FormRequest
{
    /**
     * Determine whether the current user may assign landing templates.
     *
     * @return bool Whether assignment is authorized.
     *
     * @since 2.0.0
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('landing_templates.assign') ?? false;
    }

    /**
     * Return clinic template assignment validation rules.
     *
     * @return array<string, mixed> Validation rules.
     *
     * @since 2.0.0
     */
    public function rules(): array
    {
        return [
            'template_id' => [
                'required',
                'integer',
                Rule::exists('templates', 'id')->where('is_active', true),
            ],
        ];
    }
}
