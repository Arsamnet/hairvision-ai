<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\User;
use App\Support\LandingTemplateVariables;
use Database\Seeders\TemplateSeeder;
use Database\Seeders\TenantThemeSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies tenant-scoped clinic landing-link personalization.
 *
 * @since 2.1.0
 */
class ClinicLandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure clinic administrators can customize the effective public back link.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_clinic_admin_can_update_public_back_link_for_current_tenant(): void
    {
        $template = $this->template();
        $tenant = $this->tenant('clinic-one', 'clinic-one.hairvision-ai.test', $template);
        $otherTenant = $this->tenant('clinic-two', 'clinic-two.hairvision-ai.test', $template);
        $admin = $this->userFor($tenant, 'clinic_admin', '09121111111');

        $settingsResponse = $this->actingAs($admin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('clinic-one.hairvision-ai.test')
            ->get('/admin/settings/clinic');

        $settingsResponse
            ->assertOk()
            ->assertSee(__('clinic_settings.title'))
            ->assertSee('name="back_link_title"', false)
            ->assertSee('name="back_link_url"', false)
            ->assertSee('name="back_link_target"', false)
            ->assertSee('data-settings-section="landing-link"', false)
            ->assertSee('/admin/settings/clinic/landing-link', false)
            ->assertSee('<bdi dir="ltr">http://</bdi>', false)
            ->assertSee('<bdi dir="ltr">https://</bdi>', false)
            ->assertSee('data-menu-item="admin.clinic.settings.edit"', false);

        $this->assertLessThan(
            strpos($settingsResponse->getContent(), __('clinic_settings.save_landing_link')),
            strpos($settingsResponse->getContent(), 'name="back_link_url"')
        );

        $this->actingAs($admin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('clinic-one.hairvision-ai.test')
            ->put('/admin/settings/clinic/landing-link', [
                'back_link_title' => 'ورود به سایت رسمی کلینیک',
                'back_link_url' => 'https://clinic-one.example/services',
                'back_link_target' => '_blank',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('clinic_settings.messages.updated'));

        $this->assertDatabaseHas('tenant_theme_settings', [
            'tenant_id' => $tenant->id,
            'back_link_title' => 'ورود به سایت رسمی کلینیک',
            'back_link_url' => 'https://clinic-one.example/services',
            'back_link_target' => '_blank',
        ]);
        $this->assertDatabaseMissing('tenant_theme_settings', [
            'tenant_id' => $otherTenant->id,
            'back_link_url' => 'https://clinic-one.example/services',
        ]);

        $landingResponse = $this->forHost('clinic-one.hairvision-ai.test')->get('/');

        $landingResponse
            ->assertOk()
            ->assertSee('ورود به سایت رسمی کلینیک')
            ->assertSee('href="https://clinic-one.example/services"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('لینک پیش‌فرض قالب');

        $this->assertSame(1, substr_count($landingResponse->getContent(), 'href="https://clinic-one.example/services"'));
        $this->assertStringNotContainsString(LandingTemplateVariables::BACK_LINK_TITLE, $landingResponse->getContent());
        $this->assertStringNotContainsString(LandingTemplateVariables::BACK_LINK_URL, $landingResponse->getContent());
        $this->assertStringNotContainsString(LandingTemplateVariables::BACK_LINK_TARGET, $landingResponse->getContent());

        $this->actingAs($admin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('clinic-one.hairvision-ai.test')
            ->put('/admin/settings/clinic/landing-link', [
                'back_link_title' => '<img src=x onerror=alert(1)>',
                'back_link_url' => 'https://clinic-one.example/safe',
                'back_link_target' => '_self',
            ])
            ->assertRedirect();

        $this->forHost('clinic-one.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    /**
     * Ensure invalid link values are rejected and lower clinic roles cannot update them.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_clinic_link_validation_and_role_authorization_are_enforced(): void
    {
        $template = $this->template();
        $tenant = $this->tenant('clinic-validation', 'clinic-validation.hairvision-ai.test', $template);
        $admin = $this->userFor($tenant, 'clinic_admin', '09122222222');
        $editor = $this->userFor($tenant, 'editor', '09123333333');

        $this->actingAs($admin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('clinic-validation.hairvision-ai.test')
            ->put('/admin/settings/clinic/landing-link', [
                'back_link_title' => '',
                'back_link_url' => 'javascript:alert(1)',
                'back_link_target' => '_parent',
            ])
            ->assertSessionHasErrors(['back_link_title', 'back_link_url', 'back_link_target']);

        $this->actingAs($editor)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('clinic-validation.hairvision-ai.test')
            ->get('/admin/settings/clinic')
            ->assertForbidden();

        $this->actingAs($editor)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('clinic-validation.hairvision-ai.test')
            ->put('/admin/settings/clinic/landing-link', [
                'back_link_title' => 'لینک غیرمجاز',
                'back_link_url' => 'https://unauthorized.example',
                'back_link_target' => '_self',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tenant_theme_settings', [
            'tenant_id' => $tenant->id,
            'back_link_title' => 'لینک غیرمجاز',
        ]);
    }

    /**
     * Ensure reseeding keeps clinic-managed back-link values intact.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_theme_seeder_preserves_clinic_managed_back_link_values(): void
    {
        $template = $this->template(['code' => 'classic']);
        $arya = $this->tenant('arya', 'arya.hairvision-ai.test', $template);
        $this->tenant('noja', 'noja.hairvision-ai.test', $template);

        $arya->theme()->update([
            'back_link_title' => 'عنوان شخصی‌سازی‌شده',
            'back_link_url' => 'https://arya.example/custom',
            'back_link_target' => '_blank',
        ]);

        $this->seed(TemplateSeeder::class);
        $this->seed(TenantThemeSettingSeeder::class);

        $this->assertDatabaseHas('tenant_theme_settings', [
            'tenant_id' => $arya->id,
            'back_link_title' => 'عنوان شخصی‌سازی‌شده',
            'back_link_url' => 'https://arya.example/custom',
            'back_link_target' => '_blank',
        ]);
    }

    /**
     * Create a valid active landing template.
     *
     * @param  array<string, mixed>  $overrides  Template attribute overrides.
     * @return Template Persisted template.
     *
     * @since 2.1.0
     */
    private function template(array $overrides = []): Template
    {
        return Template::query()->create(array_merge(
            TemplateSeeder::classicContent(),
            [
                'code' => 'template-'.str()->random(8),
                'name' => 'قالب تست',
                'is_active' => true,
                'is_default' => true,
                'revision' => 1,
                'back_link_title' => 'لینک پیش‌فرض قالب',
                'back_link_url' => 'https://template.example',
            ],
            $overrides
        ));
    }

    /**
     * Create an active tenant, domain, and centrally managed theme row.
     *
     * @param  string  $slug  Tenant slug.
     * @param  string  $domain  Public tenant domain.
     * @param  Template  $template  Assigned landing template.
     * @return Tenant Persisted tenant.
     *
     * @since 2.1.0
     */
    private function tenant(string $slug, string $domain, Template $template): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => 'کلینیک '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        TenantThemeSetting::query()->create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'use_template_content' => true,
        ]);

        return $tenant;
    }

    /**
     * Create and attach a dashboard user to a tenant role.
     *
     * @param  Tenant  $tenant  Tenant receiving the user.
     * @param  string  $role  Tenant role code.
     * @param  string  $phone  Unique normalized mobile number.
     * @return User Persisted dashboard user.
     *
     * @since 2.1.0
     */
    private function userFor(Tenant $tenant, string $role, string $phone): User
    {
        $user = User::query()->create([
            'name' => 'کاربر '.$role,
            'email' => $phone.'@example.test',
            'phone_normalized' => $phone,
            'access_scope' => 'clinic',
            'password' => 'password',
            'role' => $role,
            'status' => 'active',
        ]);

        $tenant->users()->attach($user->id, [
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    /**
     * Configure the test client to issue requests for a specific host.
     *
     * @param  string  $host  Request host.
     * @return self Current test instance.
     *
     * @since 2.1.0
     */
    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }
}
