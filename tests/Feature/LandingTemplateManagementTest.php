<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantThemeSetting;
use App\Models\User;
use App\Support\LandingTemplateVariables;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Verifies managed landing templates and clinic-form assignment.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function test_system_admin_can_manage_inline_landing_template_code(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates')
            ->assertOk()
            ->assertSee(__('landing_templates.title'))
            ->assertSee(__('menu.items.landing_templates'))
            ->assertDontSee('menu.items.landing_templates')
            ->assertDontSee(__('clinics.fields.landing_template'));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/templates/create')
            ->assertOk()
            ->assertSee(__('landing_templates.fields.header_html'))
            ->assertSee(__('landing_templates.code_editor_hint'))
            ->assertSee(__('landing_templates.variable_guide_title'))
            ->assertSee(LandingTemplateVariables::BACK_LINK_TITLE)
            ->assertSee(LandingTemplateVariables::BACK_LINK_URL)
            ->assertSee(LandingTemplateVariables::BACK_LINK_TARGET)
            ->assertSee(LandingTemplateVariables::backLinkExample())
            ->assertSee(__('landing_templates.system_copyright_notice'));

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
     * Ensure template variables are accepted only in the documented secure anchor pattern.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_landing_template_variables_require_the_safe_documented_context(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $this->templatePayload([
                'name' => 'قالب با متغیر ناشناخته',
                'code' => 'unknown-variable',
                'header_html' => '<div>{{ clinic.back_link.unknown }}</div>',
            ]))
            ->assertSessionHasErrors('header_html');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $this->templatePayload([
                'name' => 'قالب با متغیر داخل اسکریپت',
                'code' => 'script-variable',
                'header_html' => '<script>const url = "{{ clinic.back_link.url }}";</script>',
            ]))
            ->assertSessionHasErrors('header_html');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $this->templatePayload([
                'name' => 'قالب با لینک ناقص',
                'code' => 'partial-variable',
                'header_html' => '<a href="{{ clinic.back_link.url }}">بازگشت</a>',
            ]))
            ->assertSessionHasErrors('header_html');

        $this->assertDatabaseMissing('templates', ['code' => 'unknown-variable']);
        $this->assertDatabaseMissing('templates', ['code' => 'script-variable']);
        $this->assertDatabaseMissing('templates', ['code' => 'partial-variable']);
    }

    /**
     * Ensure assigning a template activates central HTML while retaining legacy tenant data.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
            ->get('/admin/settings/clinics/'.$clinic->id.'/edit')
            ->assertOk()
            ->assertSee(__('clinics.fields.landing_template'))
            ->assertSee($modern->name);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/clinics/'.$clinic->id, [
                '_form' => 'edit_clinic',
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'status' => 'active',
                'domain' => 'clinic-template.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'active',
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
     * Ensure clinic forms list only active templates and reject inactive selections.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_clinic_edit_form_rejects_inactive_landing_templates(): void
    {
        $admin = $this->systemAdmin();
        $active = $this->createTemplate([
            'code' => 'active-clinic-template',
            'name' => 'قالب فعال کلینیک',
            'is_default' => true,
        ]);
        $inactive = $this->createTemplate([
            'code' => 'inactive-clinic-template',
            'name' => 'قالب غیرفعال کلینیک',
            'is_active' => false,
        ]);
        $clinic = $this->clinic('inactive-template-clinic', 'inactive-template-clinic.hairvision-ai.test');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics')
            ->assertOk()
            ->assertDontSee(__('clinics.fields.landing_template'))
            ->assertDontSee($active->name)
            ->assertDontSee($inactive->name);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$clinic->id.'/edit')
            ->assertOk()
            ->assertSee(__('clinics.fields.landing_template'))
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/clinics/'.$clinic->id, [
                '_form' => 'edit_clinic',
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'status' => 'active',
                'domain' => 'inactive-template-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'active',
                'template_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('template_id');

        $this->assertNull($clinic->theme()->first());
    }

    /**
     * Ensure code-owned copyright precedes the editable footer with a light theme.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_system_copyright_is_above_the_template_footer_with_a_light_theme_and_uses_app_url(): void
    {
        config(['app.url' => 'https://primary.example/platform']);

        $template = $this->createTemplate([
            'code' => 'copyright-test',
            'footer_html' => '<div data-template-footer>فوتر قابل‌ویرایش قالب</div>',
        ]);
        $clinic = $this->clinic('copyright-clinic', 'copyright-clinic.example.test');

        TenantThemeSetting::query()->create([
            'tenant_id' => $clinic->id,
            'template_id' => $template->id,
            'use_template_content' => true,
        ]);

        $response = $this->forHost('copyright-clinic.example.test')
            ->get('/')
            ->assertOk()
            ->assertSee('data-template-footer', false)
            ->assertSee('data-hairvision-copyright', false)
            ->assertSee(__('landing_templates.system_copyright'))
            ->assertSee(__('landing_templates.system_powered_by'))
            ->assertSee('href="https://primary.example/platform"', false)
            ->assertSee('class="w-full border-y border-zinc-200 bg-white px-4 py-4 text-zinc-600"', false)
            ->assertSee('src="/theme/admin-logo-light.svg"', false)
            ->assertDontSee('src="/theme/admin-logo-dark.svg"', false);

        $html = (string) $response->getContent();
        $mainClosingPosition = strpos($html, '</main>');
        $templateFooterPosition = strpos($html, 'data-template-footer');
        $copyrightPosition = strpos($html, 'data-hairvision-copyright');
        $poweredByPosition = strpos($html, __('landing_templates.system_powered_by'));
        $logoPosition = strpos($html, 'src="/theme/admin-logo-light.svg"');

        $this->assertNotFalse($mainClosingPosition);
        $this->assertNotFalse($templateFooterPosition);
        $this->assertNotFalse($copyrightPosition);
        $this->assertNotFalse($poweredByPosition);
        $this->assertNotFalse($logoPosition);
        $this->assertGreaterThan($mainClosingPosition, $copyrightPosition);
        $this->assertGreaterThan($copyrightPosition, $templateFooterPosition);
        $this->assertLessThan($logoPosition, $poweredByPosition);
        $this->assertStringNotContainsString('data-hairvision-copyright', (string) $template->footer_html);
        $this->assertStringNotContainsString(
            '/theme/admin-logo-dark.svg',
            TemplateSeeder::classicContent()['footer_html'],
        );
        $this->assertStringNotContainsString(
            '/theme/admin-logo-light.svg',
            TemplateSeeder::classicContent()['footer_html'],
        );
    }

    /**
     * Ensure the data migration removes only unchanged built-in footer branding.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_copyright_migration_preserves_customized_template_footers(): void
    {
        $oldClassicFooter = <<<'HTML'
<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6">
    <p>یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.</p>
    <p class="flex items-center gap-2">
        <span class="pt-1">قدرت گرفته از</span>
        <span class="grid h-7 w-7 place-items-center rounded-xl bg-linear-to-br from-emerald-500 via-indigo-500 to-fuchsia-500">
            <img src="/theme/admin-logo-dark.svg" alt="" class="h-5 w-5">
        </span>
    </p>
</div>
HTML;
        $customFooter = '<div data-custom-brand>فوتر ویرایش‌شده مدیر با لوگوی اختصاصی</div>';
        $classic = $this->createTemplate([
            'code' => 'classic',
            'footer_html' => $oldClassicFooter,
        ]);
        $custom = $this->createTemplate([
            'code' => 'custom-footer',
            'footer_html' => $customFooter,
        ]);

        DB::table('migrations')
            ->where('migration', '2026_08_02_000004_move_landing_copyright_to_system_shell')
            ->delete();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame(TemplateSeeder::classicContent()['footer_html'], $classic->refresh()->footer_html);
        $this->assertSame($customFooter, $custom->refresh()->footer_html);
    }

    /**
     * Ensure a new clinic uses the default until edit-time assignment.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_new_clinic_uses_the_default_until_an_active_template_is_assigned_on_edit(): void
    {
        $admin = $this->systemAdmin();
        $default = $this->createTemplate([
            'code' => 'default-clinic',
            'name' => 'قالب پیش‌فرض کلینیک',
            'is_default' => true,
        ]);
        $selected = $this->createTemplate([
            'code' => 'selected-clinic-template',
            'name' => 'قالب انتخاب‌شده کلینیک',
            'header_html' => '<header data-selected-clinic-template>قالب انتخاب‌شده</header>',
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

        $this->assertNull($clinic->theme()->first());

        $this->forHost('new-clinic.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('data-landing-template="default-clinic"', false)
            ->assertDontSee('data-selected-clinic-template', false);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/clinics/'.$clinic->id, [
                '_form' => 'edit_clinic',
                'name' => $clinic->name,
                'slug' => $clinic->slug,
                'status' => 'active',
                'domain' => 'new-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'active',
                'template_id' => $selected->id,
            ])
            ->assertRedirect();

        $theme = $clinic->theme()->firstOrFail();

        $this->assertNotSame($default->id, $theme->template_id);
        $this->assertSame($selected->id, $theme->template_id);
        $this->assertTrue($theme->use_template_content);

        $this->forHost('new-clinic.hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('data-landing-template="selected-clinic-template"', false)
            ->assertSee('data-selected-clinic-template', false);
    }

    /**
     * Ensure the template section exposes only create/edit operations and remains system-only.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_template_section_exposes_only_create_and_edit_operations(): void
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

        $this->assertFalse(Route::has('admin.system.landing-templates.clinics'));
        $this->assertFalse(Route::has('admin.system.landing-templates.clinics.assign'));
        $this->assertFalse(Route::has('admin.system.landing-templates.default'));
        $this->assertFalse(Route::has('admin.system.landing-templates.destroy'));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/templates/'.$classic->id, $this->templatePayload([
                'name' => $classic->name,
                'is_active' => false,
            ], includeCode: false))
            ->assertSessionHasErrors('is_active');
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function test_landing_template_code_editors_follow_the_admin_theme_with_explicit_contrast(): void
    {
        $form = (string) file_get_contents(resource_path('views/admin/system/landing-templates/partials/form.blade.php'));
        $stylesheet = (string) file_get_contents(resource_path('css/components/code-editor.css'));

        $this->assertSame(5, substr_count($form, 'data-landing-template-code-editor'));
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
            'email' => 'satrapit@gmail.com',
            'phone_normalized' => '09124248164',
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    private function createTemplate(array $overrides = []): Template
    {
        return Template::query()->create(array_merge(
            TemplateSeeder::classicContent(),
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    private function templatePayload(array $overrides = [], bool $includeCode = true): array
    {
        $payload = array_merge(TemplateSeeder::classicContent(), [
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
