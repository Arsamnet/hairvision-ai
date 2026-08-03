<?php

namespace Tests\Feature;

use App\Models\GuideFaq;
use App\Models\GuidePage;
use App\Models\GuideSection;
use App\Models\GuideStep;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies clinic help visibility, relational management, and safe rich text.
 *
 * @since 2.0.1
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class AdminGuideTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure every clinic role can use the separate guide and FAQ pages.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_all_clinic_roles_can_view_the_separate_guide_and_faq_pages(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['clinic_admin', 'editor', 'viewer'] as $role) {
            $user = User::query()->where('role', $role)->firstOrFail();

            $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/help/guide')
                ->assertOk()
                ->assertSee(__('guide.defaults.title'))
                ->assertSee('id="guide-index"', false)
                ->assertSee('href="#guide-section-1"', false)
                ->assertSee('data-menu-item="admin.guide.show"', false)
                ->assertSee('data-menu-item="admin.guide.faq"', false)
                ->assertDontSee('data-menu-item="admin.system.guide.edit"', false);

            $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/help/faq')
                ->assertOk()
                ->assertSee(__('guide.faq_page_title'))
                ->assertSee('id="faq-index"', false)
                ->assertSee('href="#faq-item-1"', false)
                ->assertSee('<details', false);
        }
    }

    /**
     * Ensure clinic help remains available without an active plan.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_clinic_user_without_an_active_plan_can_still_open_help(): void
    {
        $this->seed(DatabaseSeeder::class);
        $tenant = Tenant::factory()->create();
        $viewer = User::query()->create([
            'name' => 'مشاهده‌گر بدون پلن',
            'email' => 'no-plan-guide@example.test',
            'phone_normalized' => '09121112233',
            'access_scope' => 'clinic',
            'password' => 'password',
            'role' => 'viewer',
            'status' => 'active',
        ]);
        $tenant->users()->attach($viewer->id, ['role' => 'viewer', 'status' => 'active']);

        foreach (['/admin/help/guide', '/admin/help/faq'] as $path) {
            $this->actingAs($viewer)
                ->withSession(['admin_active_tenant_id' => $tenant->id])
                ->forHost('hairvision-ai.test')
                ->get($path)
                ->assertOk();
        }
    }

    /**
     * Ensure platform management has two protected pages and distinct menu items.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_management_has_separate_permission_protected_guide_and_faq_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $viewer = User::query()->where('role', 'viewer')->firstOrFail();
        $manager = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($viewer)->forHost('hairvision-ai.test')->get('/admin/settings/guide')->assertForbidden();
        $this->actingAs($viewer)->forHost('hairvision-ai.test')->get('/admin/settings/faqs')->assertForbidden();

        $guideResponse = $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')->get('/admin/settings/guide');
        $guideResponse->assertOk()
            ->assertSee(__('guide.management_title'))
            ->assertSee('data-admin-rich-text', false)
            ->assertSee('data-rich-text-link-panel', false)
            ->assertSee('data-menu-item="admin.system.guide.edit"', false)
            ->assertSee('data-menu-item="admin.system.faq.edit"', false)
            ->assertSee('data-menu-icon="guide"', false)
            ->assertSee('data-menu-icon="faq"', false)
            ->assertDontSee('<x-admin.button', false);

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')->get('/admin/settings/faqs')
            ->assertOk()
            ->assertSee(__('guide.faq_management_title'))
            ->assertSee(route('admin.system.guide.edit'), false);
    }

    /**
     * Ensure dedicated tables replace the legacy setting and seeding is idempotent.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_guide_content_uses_dedicated_models_and_seed_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [GuidePage::count(), GuideSection::count(), GuideStep::count(), GuideFaq::count()];
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [GuidePage::count(), GuideSection::count(), GuideStep::count(), GuideFaq::count()]);
        $this->assertSame(2, GuidePage::query()->whereIn('key', [GuidePage::GUIDE, GuidePage::FAQ])->count());
        $this->assertGreaterThan(0, GuideSection::count());
        $this->assertGreaterThan(0, GuideStep::count());
        $this->assertGreaterThan(0, GuideFaq::count());
        $this->assertSame(0, SystemSetting::query()->where('key', 'guide.content')->count());
        $this->assertDatabaseHas('permissions', ['code' => 'guide.manage', 'group' => 'guide']);
    }

    /**
     * Ensure the default help content covers the current clinic-facing workflows.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_default_guide_covers_current_clinic_workflows(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach ([
            'عملکرد هفت روز اخیر را بخوانید',
            'اطلاعات و وضعیت لید را کامل کنید',
            'لید را فقط در صورت ضرورت حذف کنید',
            'فرمت و حجم عکس را کنترل کنید',
            'لینک بازگشت به سایت کلینیک را تنظیم کنید',
            'بازه شمسی گزارش کامل را تعیین کنید',
        ] as $title) {
            $this->assertDatabaseHas('guide_steps', ['title' => $title]);
        }

        foreach ([
            'وضعیت‌های لید چه معنایی دارند و آیا مرحله CRM جداگانه‌ای وجود دارد؟',
            'آیا می‌توانم یک لید را حذف کنم؟',
            'چه فرمت‌ها و حجمی برای عکس مراجعه‌کننده مجاز است؟',
            'آمار گزارش عملکرد چگونه محاسبه می‌شود؟',
            'چگونه متن یا مقصد دکمه «بازگشت به سایت کلینیک» را تغییر دهم؟',
        ] as $question) {
            $this->assertDatabaseHas('guide_faqs', ['question' => $question]);
        }

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/help/guide')
            ->assertOk()
            ->assertSee('تنظیمات کلینیک و صفحه مراجعه‌کننده')
            ->assertSee('HEIC/HEIF')
            ->assertSee('دریافت CSV');

        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/help/faq')
            ->assertOk()
            ->assertSee('آیا می‌توانم یک لید را حذف کنم؟')
            ->assertSee('آمار گزارش عملکرد چگونه محاسبه می‌شود؟');
    }

    /**
     * Ensure each guide record can be edited independently and HTML is sanitized.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_manager_can_edit_page_section_and_step_independently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->put('/admin/settings/guide', ['title' => 'راهنمای تازه', 'introduction' => '<p>مقدمه <strong>تازه</strong></p>'])
            ->assertRedirect('/admin/settings/guide')
            ->assertSessionHas('status', __('guide.page_saved'));

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->post('/admin/settings/guide/sections', [
                'title' => 'بخش آزمون',
                'audience' => 'کارکنان کلینیک',
                'description' => '<p>شرح <em>امن</em><script>alert(1)</script></p>',
            ])->assertSessionHasNoErrors();

        $section = GuideSection::query()->where('title', 'بخش آزمون')->firstOrFail();
        $this->assertStringContainsString('<em>امن</em>', $section->description_html);
        $this->assertStringNotContainsString('<script', $section->description_html);

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->post('/admin/settings/guide/sections/'.$section->id.'/steps', [
                'title' => 'گام آزمون',
                'body' => '<p><strong>متن گام</strong><img src=x onerror=alert(1)><a href="javascript:alert(1)">بد</a><a href="https://example.test">خوب</a></p>',
            ])->assertSessionHasNoErrors();

        $step = GuideStep::query()->where('title', 'گام آزمون')->firstOrFail();
        $this->assertSame($section->id, $step->guide_section_id);
        $this->assertStringContainsString('<strong>متن گام</strong>', $step->body_html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $step->body_html);
        $this->assertStringNotContainsString('javascript:', $step->body_html);
        $this->assertStringNotContainsString('<img', $step->body_html);

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')->get('/admin/help/guide')
            ->assertOk()
            ->assertSee('راهنمای تازه')
            ->assertSee('<strong>متن گام</strong>', false)
            ->assertDontSee('javascript:', false);
    }

    /**
     * Ensure ordering and deletion target only the selected guide record.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_manager_can_reorder_and_delete_individual_steps(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::query()->where('role', 'super_admin')->firstOrFail();
        $section = GuidePage::query()->where('key', GuidePage::GUIDE)->firstOrFail()->sections()->firstOrFail();
        $steps = $section->steps()->get();
        $last = $steps->last();
        $beforeLast = $steps->get($steps->count() - 2);

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->patch('/admin/settings/guide/steps/'.$last->id.'/move', ['direction' => 'up'])
            ->assertSessionHasNoErrors();

        $orderedIds = $section->steps()->pluck('id')->all();
        $this->assertLessThan(array_search($beforeLast->id, $orderedIds, true), array_search($last->id, $orderedIds, true));

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->delete('/admin/settings/guide/steps/'.$last->id)
            ->assertSessionHas('status', __('guide.step_deleted'));
        $this->assertDatabaseMissing('guide_steps', ['id' => $last->id]);
        $this->assertDatabaseHas('guide_steps', ['id' => $beforeLast->id]);
    }

    /**
     * Ensure FAQ metadata and questions are independently managed and rendered.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_manager_can_create_update_reorder_and_delete_faqs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->put('/admin/settings/faqs', ['title' => 'پرسش‌های کلینیک', 'introduction' => '<p>پاسخ‌های <strong>روزمره</strong></p>'])
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->post('/admin/settings/faqs/items', ['question' => 'پرسش آزمایشی؟', 'answer' => '<p><em>پاسخ آزمایشی</em></p>'])
            ->assertSessionHasNoErrors();

        $faq = GuideFaq::query()->where('question', 'پرسش آزمایشی؟')->firstOrFail();
        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->put('/admin/settings/faqs/items/'.$faq->id, ['question' => 'پرسش ویرایش‌شده؟', 'answer' => '<p>پاسخ ویرایش‌شده</p>'])
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')->get('/admin/help/faq')
            ->assertOk()
            ->assertSee('پرسش‌های کلینیک')
            ->assertSee('پرسش ویرایش‌شده؟')
            ->assertSee('<strong>روزمره</strong>', false);

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->delete('/admin/settings/faqs/items/'.$faq->id)
            ->assertSessionHas('status', __('guide.faq_deleted'));
        $this->assertDatabaseMissing('guide_faqs', ['id' => $faq->id]);
    }

    /**
     * Ensure empty sanitized content is rejected for each small form.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_rich_text_validation_rejects_content_without_visible_text(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($manager)->withSession(['admin_all_tenants' => true])->forHost('hairvision-ai.test')
            ->from('/admin/settings/guide')
            ->put('/admin/settings/guide', ['title' => 'راهنما', 'introduction' => '<img src="x"><script>alert(1)</script>'])
            ->assertRedirect('/admin/settings/guide')
            ->assertSessionHasErrors('introduction');
    }

    /**
     * Ensure editor selection, paste, source mode, and reduced-motion contracts remain present.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function test_rich_editor_and_smooth_motion_contracts_are_functional(): void
    {
        $appCss = (string) file_get_contents(resource_path('css/app.css'));
        $guideCss = (string) file_get_contents(resource_path('css/components/admin-guide.css'));
        $editor = (string) file_get_contents(resource_path('js/admin/rich-text-editor.js'));
        $fragmentNavigation = (string) file_get_contents(resource_path('js/admin/fragment-navigation.js'));
        $component = (string) file_get_contents(resource_path('views/components/admin/rich-text-editor.blade.php'));
        $layout = (string) file_get_contents(resource_path('views/admin/layout.blade.php'));

        $this->assertStringContainsString('scroll-behavior: smooth', $appCss);
        $this->assertStringContainsString('scroll-behavior: auto !important', $appCss);
        $this->assertStringContainsString('position: fixed', $appCss);
        $this->assertStringContainsString('height: 100dvh', $appCss);
        $this->assertStringContainsString('font-weight: 400', $appCss);
        $this->assertStringContainsString('grid-template-rows: 0fr', $guideCss);
        $this->assertStringContainsString('grid-template-rows: 1fr', $guideCss);
        $this->assertStringContainsString('savedRange', $editor);
        $this->assertStringContainsString('restoreSelection', $editor);
        $this->assertStringContainsString("document.execCommand('styleWithCSS', false, false)", $editor);
        $this->assertStringContainsString("element.tagName === 'SPAN'", $editor);
        $this->assertStringContainsString('MutationObserver', $editor);
        $this->assertStringContainsString('event.formData.set(input.name, input.value)', $editor);
        $this->assertStringContainsString('source.required = false', $editor);
        $this->assertStringContainsString("editor.addEventListener('paste'", $editor);
        $this->assertStringContainsString('data-rich-text-link-apply', $editor);
        $this->assertStringContainsString('sanitizeRichTextHtml', $editor);
        $this->assertStringNotContainsString('window.prompt', $editor);
        $this->assertSame(1, substr_count($component, 'name="{{ $name }}"'));
        $this->assertStringContainsString('data-rich-text-source', $component);
        $this->assertStringNotContainsString("text-sm font-bold']) }} data-admin-rich-text", $component);
        $this->assertStringContainsString('data-admin-scroll-container', $layout);
        $this->assertStringContainsString('data-admin-scroll-guard', $layout);
        $this->assertStringContainsString("history.scrollRestoration = 'manual'", $fragmentNavigation);
        $this->assertStringContainsString('scrollAdminContentToHash', $fragmentNavigation);
        $this->assertStringContainsString('resetRootScroll', $fragmentNavigation);
    }

    /**
     * Force the request URL and server host used by dashboard feature tests.
     *
     * @param  string  $host  HTTP host for the next request.
     * @return self Current test instance.
     *
     * @since 2.0.1
     */
    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables(['HTTP_HOST' => $host, 'SERVER_NAME' => $host]);
    }
}
