<?php

namespace App\Services\Landing;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages landing template lifecycle, default selection, and tenant assignment.
 *
 * @since 2.0.0
 */
class LandingTemplateManagementService
{
    /**
     * Create a centrally managed landing template.
     *
     * @param  array<string, mixed>  $data  Validated template fields.
     * @param  User  $actor  Authenticated system administrator.
     * @return Template Created template.
     *
     * @since 2.0.0
     */
    public function create(array $data, User $actor): Template
    {
        return DB::transaction(function () use ($data, $actor): Template {
            $hasDefault = Template::query()->lockForUpdate()->where('is_default', true)->exists();
            $template = Template::query()->create(array_merge($data, [
                'is_default' => ! $hasDefault,
                'is_active' => ! $hasDefault || (bool) $data['is_active'],
                'revision' => 1,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]));

            return $template->refresh();
        });
    }

    /**
     * Update editable template content and increment its revision.
     *
     * @param  Template  $template  Template being updated.
     * @param  array<string, mixed>  $data  Validated template fields.
     * @param  User  $actor  Authenticated system administrator.
     * @return Template Updated template.
     *
     * @since 2.0.0
     */
    public function update(Template $template, array $data, User $actor): Template
    {
        if ($template->is_default && ! (bool) $data['is_active']) {
            throw ValidationException::withMessages([
                'is_active' => __('landing_templates.validation.default_must_be_active'),
            ]);
        }

        $template->update(array_merge($data, [
            'revision' => $template->revision + 1,
            'updated_by' => $actor->id,
        ]));

        return $template->refresh();
    }

    /**
     * Make an active template the single platform default.
     *
     * @param  Template  $template  Template selected as the new default.
     * @param  User  $actor  Authenticated system administrator.
     * @return Template Updated default template.
     *
     * @since 2.0.0
     */
    public function makeDefault(Template $template, User $actor): Template
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'template' => __('landing_templates.validation.inactive_default'),
            ]);
        }

        return DB::transaction(function () use ($template, $actor): Template {
            Template::query()->lockForUpdate()->get(['id']);
            Template::query()->where('is_default', true)->update(['is_default' => false]);
            $template->forceFill([
                'is_default' => true,
                'revision' => $template->revision + 1,
                'updated_by' => $actor->id,
            ])->save();

            return $template->refresh();
        });
    }

    /**
     * Delete an unused non-default template.
     *
     * @param  Template  $template  Template being deleted.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function delete(Template $template): void
    {
        if ($template->is_default) {
            throw ValidationException::withMessages([
                'template' => __('landing_templates.validation.default_cannot_delete'),
            ]);
        }

        if ($template->themeSettings()->exists()) {
            throw ValidationException::withMessages([
                'template' => __('landing_templates.validation.assigned_cannot_delete'),
            ]);
        }

        Template::destroy($template->getKey());
    }

    /**
     * Assign an active central template to a tenant without deleting legacy custom content.
     *
     * @param  Tenant  $tenant  Clinic receiving the template.
     * @param  Template  $template  Active template to assign.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function assignToTenant(Tenant $tenant, Template $template): void
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'template_id' => __('landing_templates.validation.inactive_assignment'),
            ]);
        }

        DB::transaction(function () use ($tenant, $template): void {
            $tenant->theme()->updateOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'template_id' => $template->id,
                    'use_template_content' => true,
                ]
            );
        });
    }

    /**
     * Attach the active default template to a newly created clinic.
     *
     * @param  Tenant  $tenant  Newly persisted clinic.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function assignDefaultToNewTenant(Tenant $tenant): void
    {
        $template = Template::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template' => __('landing_templates.validation.default_missing'),
            ]);
        }

        $this->assignToTenant($tenant, $template);
    }
}
