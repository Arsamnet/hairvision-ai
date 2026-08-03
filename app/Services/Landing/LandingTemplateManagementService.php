<?php

namespace App\Services\Landing;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages trusted landing template content and clinic-selected assignments.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
     * Assign an active central template to a tenant without deleting legacy custom content.
     *
     * @param  Tenant  $tenant  Clinic receiving the template.
     * @param  Template  $template  Active template to assign.
     * @return void No value is returned.
     *
     * @since 2.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function assignToTenant(Tenant $tenant, Template $template): void
    {
        if (! $template->is_active) {
            throw ValidationException::withMessages([
                'template_id' => __('landing_templates.validation.inactive_assignment'),
            ]);
        }

        DB::transaction(fn () => $this->persistTenantAssignment($tenant, $template));
    }

    /**
     * Persist the clinic assignment without opening another transaction.
     *
     * @param  Tenant  $tenant  Clinic receiving the managed template.
     * @param  Template  $template  Template becoming authoritative for public content.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    private function persistTenantAssignment(Tenant $tenant, Template $template): void
    {
        $tenant->theme()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'template_id' => $template->id,
                'use_template_content' => true,
            ]
        );
    }
}
