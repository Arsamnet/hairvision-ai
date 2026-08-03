<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClinicSettings\UpdateClinicLandingSettingsRequest;
use App\Models\Tenant;
use App\Services\Landing\ClinicLandingSettingsService;
use App\Services\Landing\LandingTemplateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays and updates tenant-owned clinic presentation settings.
 *
 * @since 2.1.0
 */
class ClinicSettingController extends Controller
{
    /**
     * Display the active clinic's editable public landing settings.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @param  LandingTemplateResolver  $landingTemplates  Effective landing presentation resolver.
     * @return View Rendered clinic settings form.
     *
     * @since 2.1.0
     */
    public function edit(Request $request, LandingTemplateResolver $landingTemplates): View
    {
        $tenant = $this->tenantFrom($request);

        return view('admin.clinic-settings.edit', [
            'tenant' => $tenant,
            'availableTenants' => $request->attributes->get('adminTenants'),
            'landingSettings' => $landingTemplates->resolve($tenant),
        ]);
    }

    /**
     * Persist the active clinic's public landing-link section.
     *
     * @param  UpdateClinicLandingSettingsRequest  $request  Validated settings request.
     * @param  ClinicLandingSettingsService  $settings  Tenant settings service.
     * @return RedirectResponse Redirect back to the saved settings section.
     *
     * @since 2.1.0
     */
    public function updateLandingLink(
        UpdateClinicLandingSettingsRequest $request,
        ClinicLandingSettingsService $settings,
    ): RedirectResponse {
        $settings->updateBackLink($this->tenantFrom($request), $request->validated());

        return redirect()
            ->to(route('admin.clinic.settings.edit').'#landing-link')
            ->with('status', __('clinic_settings.messages.updated'));
    }

    /**
     * Resolve the tenant already authorized by the dashboard context.
     *
     * @param  Request  $request  Incoming dashboard request.
     * @return Tenant Active clinic tenant.
     *
     * @since 2.1.0
     */
    private function tenantFrom(Request $request): Tenant
    {
        $tenant = $request->attributes->get('adminTenant');

        abort_unless(
            $tenant instanceof Tenant && (bool) $request->user()?->canAccessTenant($tenant->id),
            403,
            __('permissions.tenant_denied')
        );

        return $tenant;
    }
}
