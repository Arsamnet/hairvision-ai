<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\User;
use App\Support\LandingTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies managed landing templates, default activation, and clinic assignment.
 *
 * @since 2.0.0
 */
class LandingTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure system administrators can create trusted inline templates and external assets are rejected.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_system_admin_can_manage_inline_landing_template_code(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates')
            ->assertOk()
            ->assertSee(__('landing_templates.title'))
            ->assertSee(__('landing_templates.clinics_tab'))
            ->assertSee(__('menu.items.landing_templates'))
            ->assertDontSee('menu.items.landing_templates');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates/create')
            ->assertOk()
            ->assertSee(__('landing_templates.fields.header_html'))
            ->assertSee(__('landing_templates.code_editor_hint'));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $this->templatePayload([
                'name' => 'قالب مدرن',
                'code' => 'modern',
                'header_html' => '<style>.clinic-header{color:#123456}</style><header class="clinic-header">هدر مدرن</header>',
                'footer_html' => '<footer>فوتر مدرن</footer><script>window.hairVisionTemplate = true;</script>',
            ]))
            ->assertRedirect();

        $template = Template::query()->where('code', 'modern')->firstOrFail();

        $this->assertTrue($template->is_default);
        $this->assertTrue($template->is_active);
        $this->assertSame(1, $template->revision);
        $this->assertStringContainsString('<script>', $template->footer_html);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates/'.$template->id.'/edit')
            ->assertOk()
            ->assertSee('window.hairVisionTemplate = true;', false);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/templates/'.$template->id, $this->templatePayload([
                'name' => 'قالب مدرن ویرایش‌شده',
                'header_html' => '<header>نسخه دوم</header>',
            ], includeCode: false))
            ->assertRedirect();

        $this->assertSame(2, $template->refresh()->revision);
        $this->assertSame('قالب مدرن ویرایش‌شده', $template->name);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $this->templatePayload([
                'name' => 'قالب ناامن',
                'code' => 'unsafe',
                'header_html' => '<script src="https://cdn.example.test/app.js"></script>',
            ]))
            ->assertSessionHasErrors('header_html');

        $this->assertDatabaseMissing('templates', ['code' => 'unsafe']);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $this->templatePayload([
                'name' => 'قالب با فاوآیکون خارجی',
                'code' => 'unsafe-favicon',
                'favicon_path' => 'https://cdn.example.test/favicon.png',
            ]))
            ->assertSessionHasErrors('favicon_path');

        $this->assertDatabaseMissing('templates', ['code' => 'unsafe-favicon']);
    }

    /**
     * Ensure assigning a template activates central HTML while retaining legacy tenant data.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_template_assignment_customizes_clinic_landing_and_preserves_legacy_content(): void
    {
        $admin = $this->systemAdmin();
        $classic = $this->createTemplate([
            'code' => 'classic',
            'name' => 'کلاسیک',
            'is_default' => true,
        ]);
        $modern = $this->createTemplate([
            'code' => 'modern-clinic',
            'name' => 'مدرن کلینیک',
            'primary_color' => '#123456',
            'header_html' => '<style>.modern-header{color:#123456}</style><div class="modern-header">هدر اختصاصی قالب</div>',
            'body_html' => '<section data-custom-body>محتوای تکمیلی قالب</section>',
            'footer_html' => '<div>فوتر اختصاصی قالب</div><script>window.templateReady = true;</script>',
        ]);
        $clinic = $this->clinic('clinic-template', 'clinic-template.hairvision-ai.test');
        $legacyHeader = '<div>هدر قدیمی کلینیک</div>';

        TenantThemeSetting::query()->create([
            'tenant_id' => $clinic->id,
            'template_id' => $classic->id,
            'use_template_content' => false,
            'header_html' => $legacyHeader,
            'footer_html' => '<div>فوتر قدیمی کلینیک</div>',
            'primary_color' => '#654321',
        ]);

        $this->forHost('clinic-template.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('هدر قدیمی کلینیک')
            ->assertDontSee('هدر اختصاصی قالب');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates/clinics')
            ->assertOk()
            ->assertSee($clinic->name)
            ->assertSee(__('landing_templates.statuses.legacy'));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/templates/clinics/'.$clinic->id, [
                'template_id' => $modern->id,
            ])
            ->assertRedirect();

        $theme = $clinic->theme()->firstOrFail();
        $this->assertTrue($theme->use_template_content);
        $this->assertSame($modern->id, $theme->template_id);
        $this->assertSame($legacyHeader, $theme->header_html);

        $this->forHost('clinic-template.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('data-landing-template="modern-clinic"', false)
            ->assertSee('هدر اختصاصی قالب')
            ->assertSee('data-custom-body', false)
            ->assertSee('فوتر اختصاصی قالب')
            ->assertSee('window.templateReady = true;', false)
            ->assertDontSee('هدر قدیمی کلینیک')
            ->assertSee('--hv-primary-color: #123456;', false);

        $this->forHost('clinic-template.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('tenant.theme.template', 'modern-clinic')
            ->assertJsonPath('tenant.theme.primary_color', '#123456');

        $modern->update(['is_active' => false]);

        $this->forHost('clinic-template.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('data-landing-template="classic"', false)
            ->assertDontSee('هدر اختصاصی قالب');
    }

    /**
     * Ensure a newly created clinic receives the active default template in the same workflow.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_new_clinic_is_activated_with_the_default_template(): void
    {
        $admin = $this->systemAdmin();
        $default = $this->createTemplate([
            'code' => 'default-clinic',
            'name' => 'قالب پیش‌فرض کلینیک',
            'is_default' => true,
        ]);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics', [
                '_form' => 'create_clinic',
                'name' => 'کلینیک جدید',
                'slug' => 'new-clinic',
                'status' => 'active',
                'domain' => 'new-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'active',
            ])
            ->assertRedirect();

        $clinic = Tenant::query()->where('slug', 'new-clinic')->firstOrFail();
        $theme = $clinic->theme()->firstOrFail();

        $this->assertSame($default->id, $theme->template_id);
        $this->assertTrue($theme->use_template_content);
    }

    /**
     * Ensure default and deletion invariants are enforced and clinic users cannot manage templates.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_template_lifecycle_and_system_permissions_are_enforced(): void
    {
        $admin = $this->systemAdmin();
        $clinicUser = User::query()->create([
            'name' => 'مدیر کلینیک',
            'email' => 'clinic@example.test',
            'phone_normalized' => '09120000001',
            'access_scope' => 'clinic',
            'password' => 'password',
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);
        $classic = $this->createTemplate([
            'code' => 'classic',
            'name' => 'کلاسیک',
            'is_default' => true,
        ]);
        $modern = $this->createTemplate([
            'code' => 'modern',
            'name' => 'مدرن',
        ]);

        foreach ([
            'landing_templates.view',
            'landing_templates.create',
            'landing_templates.update',
            'landing_templates.delete',
            'landing_templates.assign',
        ] as $permission) {
            $this->assertDatabaseHas('permissions', [
                'code' => $permission,
                'group' => 'landing_templates',
            ]);
        }

        $this->actingAs($clinicUser)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates')
            ->assertForbidden();

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/settings/templates/'.$modern->id.'/default')
            ->assertRedirect();

        $this->assertFalse($classic->refresh()->is_default);
        $this->assertTrue($modern->refresh()->is_default);
        $this->assertSame(1, Template::query()->where('is_default', true)->count());

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/templates/'.$modern->id, $this->templatePayload([
                'name' => $modern->name,
                'is_active' => false,
            ], includeCode: false))
            ->assertSessionHasErrors('is_active');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/settings/templates/'.$modern->id)
            ->assertSessionHasErrors('template');
    }

    /**
     * Ensure Tailwind display utilities no longer conflict with the initial hidden state.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_landing_hidden_elements_do_not_declare_conflicting_flex_or_grid_utilities(): void
    {
        $landing = (string) file_get_contents(resource_path('views/landing.blade.php'));
        $javascript = (string) file_get_contents(resource_path('js/landing.js'));

        preg_match_all('/class="([^"]*)"/', $landing, $classMatches);

        foreach ($classMatches[1] as $classes) {
            $tokens = preg_split('/\s+/', trim($classes)) ?: [];

            $this->assertFalse(
                in_array('hidden', $tokens, true) && (
                    in_array('flex', $tokens, true)
                    || in_array('grid', $tokens, true)
                ),
                $classes
            );
        }

        $this->assertStringContainsString("otpActions?.classList.toggle('flex', isOtp);", $javascript);
        $this->assertStringContainsString("container.classList.toggle('grid', items.length > 0);", $javascript);
    }

    /**
     * Ensure the HTML editors use readable theme-aware colors instead of a forced dark surface.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_landing_template_code_editors_follow_the_admin_theme_with_explicit_contrast(): void
    {
        $form = (string) file_get_contents(resource_path('views/admin/system/landing-templates/partials/form.blade.php'));
        $stylesheet = (string) file_get_contents(resource_path('css/components/code-editor.css'));

        $this->assertSame(3, substr_count($form, 'data-landing-template-code-editor'));
        $this->assertStringNotContainsString('bg-zinc-950', $form);
        $this->assertStringContainsString('background-color: var(--admin-elevated) !important;', $stylesheet);
        $this->assertStringContainsString('color: var(--admin-text) !important;', $stylesheet);
        $this->assertStringContainsString('-webkit-text-fill-color: var(--admin-text);', $stylesheet);
        $this->assertStringContainsString('caret-color: var(--admin-primary);', $stylesheet);
    }

    /**
     * Create a system administrator that bypasses tenant-scoped permissions.
     *
     * @return User Persisted system administrator.
     *
     * @since 2.0.0
     */
    private function systemAdmin(): User
    {
        return User::query()->create([
            'name' => 'مدیر سیستم',
            'email' => 'system@example.test',
            'phone_normalized' => '09129999999',
            'access_scope' => 'system',
            'password' => 'password',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    /**
     * Create an active clinic and its public host.
     *
     * @param  string  $slug  Clinic slug.
     * @param  string  $domain  Public clinic domain.
     * @return Tenant Persisted clinic.
     *
     * @since 2.0.0
     */
    private function clinic(string $slug, string $domain): Tenant
    {
        $clinic = Tenant::query()->create([
            'name' => 'کلینیک '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $clinic->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        return $clinic;
    }

    /**
     * Create a template with valid default content.
     *
     * @param  array<string, mixed>  $overrides  Template attribute overrides.
     * @return Template Persisted template.
     *
     * @since 2.0.0
     */
    private function createTemplate(array $overrides = []): Template
    {
        return Template::query()->create(array_merge(
            LandingTemplateDefaults::content(),
            [
                'code' => 'template-'.str()->random(8),
                'name' => 'قالب تست',
                'is_active' => true,
                'is_default' => false,
                'revision' => 1,
            ],
            $overrides
        ));
    }

    /**
     * Build a valid landing template request payload.
     *
     * @param  array<string, mixed>  $overrides  Request field overrides.
     * @param  bool  $includeCode  Whether the immutable code is included.
     * @return array<string, mixed> Request payload.
     *
     * @since 2.0.0
     */
    private function templatePayload(array $overrides = [], bool $includeCode = true): array
    {
        $payload = array_merge(LandingTemplateDefaults::content(), [
            '_form' => $includeCode ? 'create_landing_template' : 'edit_landing_template',
            'name' => 'قالب تست',
            'code' => 'template-test',
            'description' => 'قالب تست مدیریت لندینگ',
            'is_active' => true,
        ], $overrides);

        if (! $includeCode) {
            unset($payload['code']);
        }

        return $payload;
    }

    /**
     * Configure the application test client to issue requests for a specific host.
     *
     * @param  string  $host  Request host.
     * @return self Current test instance.
     *
     * @since 2.0.0
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
