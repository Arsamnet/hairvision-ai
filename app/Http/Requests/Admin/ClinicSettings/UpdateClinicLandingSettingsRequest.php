<?php

namespace App\Http\Requests\Admin\ClinicSettings;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates tenant-scoped public landing link customization.
 *
 * @since 2.1.0
 */
class UpdateClinicLandingSettingsRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may update the active clinic.
     *
     * @return bool Whether the request is authorized.
     *
     * @since 2.1.0
     */
    public function authorize(): bool
    {
        $tenant = $this->attributes->get('adminTenant');
        $user = $this->user();

        return $tenant instanceof Tenant
            && (bool) $user?->canAccessTenant($tenant->id)
            && (bool) $user?->hasPermission('clinic_settings.manage', $tenant->id);
    }

    /**
     * Return validation rules for the editable landing link fields.
     *
     * @return array<string, array<int, mixed>> Validation rules keyed by field.
     *
     * @since 2.1.0
     */
    public function rules(): array
    {
        return [
            'back_link_title' => ['bail', 'required', 'string', 'max:150'],
            'back_link_url' => ['bail', 'required', 'url:http,https', 'max:2048'],
            'back_link_target' => ['bail', 'required', Rule::in(['_self', '_blank'])],
        ];
    }

    /**
     * Normalize surrounding whitespace before validation and persistence.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'back_link_title' => trim((string) $this->input('back_link_title')),
            'back_link_url' => trim((string) $this->input('back_link_url')),
            'back_link_target' => trim((string) $this->input('back_link_target')),
        ]);
    }

    /**
     * Return localized validation attribute names.
     *
     * @return array<string, string> Attribute labels keyed by input name.
     *
     * @since 2.1.0
     */
    public function attributes(): array
    {
        return [
            'back_link_title' => __('clinic_settings.fields.back_link_title'),
            'back_link_url' => __('clinic_settings.fields.back_link_url'),
            'back_link_target' => __('clinic_settings.fields.back_link_target'),
        ];
    }
}
