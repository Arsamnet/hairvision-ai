<?php

namespace App\Services\Landing;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantThemeSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Persists tenant-owned landing presentation overrides.
 *
 * @since 2.1.0
 */
class ClinicLandingSettingsService
{
    /**
     * Update the public back-link settings for one clinic.
     *
     * @param  Tenant  $tenant  Clinic that owns the settings.
     * @param  array{back_link_title: string, back_link_url: string, back_link_target: string}  $data  Validated link settings.
     * @return TenantThemeSetting Updated tenant theme settings.
     *
     * @throws ValidationException When no active landing template is available.
     *
     * @since 2.1.0
     */
    public function updateBackLink(Tenant $tenant, array $data): TenantThemeSetting
    {
        return DB::transaction(function () use ($tenant, $data): TenantThemeSetting {
            $theme = $tenant->theme()->lockForUpdate()->first();

            if (! $theme) {
                $template = Template::query()
                    ->where('is_active', true)
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->first();

                if (! $template) {
                    throw ValidationException::withMessages([
                        'back_link_url' => __('clinic_settings.validation.template_missing'),
                    ]);
                }

                $theme = new TenantThemeSetting([
                    'tenant_id' => $tenant->id,
                    'template_id' => $template->id,
                    'use_template_content' => true,
                ]);
            }

            $theme->fill([
                'back_link_title' => $data['back_link_title'],
                'back_link_url' => $data['back_link_url'],
                'back_link_target' => $data['back_link_target'],
            ])->save();

            return $theme->refresh();
        });
    }
}
