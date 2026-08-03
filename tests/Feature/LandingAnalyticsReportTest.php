<?php

namespace Tests\Feature;

use App\Models\AiModelCategory;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantLandingEvent;
use App\Models\TenantLandingPageVisit;
use App\Models\TenantThemeSetting;
use App\Models\User;
use App\Rules\TrackingCodeFragment;
use App\Services\Analytics\ClinicPerformanceReportService;
use App\Support\Analytics\LandingEventCatalog;
use App\Support\JalaliDate;
use Carbon\CarbonImmutable;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies tenant landing analytics, SEO rendering, and clinic report boundaries.
 *
 * @since 2.1.0
 * @since 2.1.1 Covers unique-visitor-to-lead final conversion reporting.
 */
class LandingAnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify public events are idempotent, tenant-scoped, and linked after OTP.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_public_events_are_idempotent_tenant_scoped_and_linked_after_verification(): void
    {
        $tenant = $this->createTenant('کلینیک آریا', 'aria', 'aria.hairvision-ai.test');
        $otherTenant = $this->createTenant('کلینیک دیگر', 'other', 'other.hairvision-ai.test');
        $category = AiModelCategory::firstOrCreate([
            'code' => 'hair_transplant',
        ], [
            'name' => 'کاشت مو',
            'status' => 'active',
        ]);

        $landing = $this->forHost('aria.hairvision-ai.test')
            ->withHeader('Referer', 'https://search.example.test/results?phone=09123456789')
            ->get('/')
            ->assertOk();
        $this->assertMatchesRegularExpression('/"visitToken":"[A-Za-z0-9]{64}"/', $landing->getContent());
        preg_match('/"visitToken":"([A-Za-z0-9]{64})"/', $landing->getContent(), $matches);
        $token = $matches[1];
        $visit = TenantLandingPageVisit::query()->where('tenant_id', $tenant->id)->sole();
        $eventId = (string) Str::uuid();

        $this->assertSame(hash('sha256', $token), $visit->analytics_token_hash);
        $this->assertNotSame($token, $visit->analytics_token_hash);
        $this->assertSame('https://search.example.test', $visit->referer);

        $payload = [
            'visit_token' => $token,
            'event_id' => $eventId,
            'event_type' => LandingEventCatalog::APPLICATION_CLICKED,
            'category_id' => $category->uuid,
        ];

        $this->forHost('aria.hairvision-ai.test')->postJson('/api/v1/landing/events', $payload)->assertAccepted();
        $this->forHost('aria.hairvision-ai.test')->postJson('/api/v1/landing/events', $payload)->assertAccepted();
        $this->assertDatabaseCount('tenant_landing_events', 1);

        $this->forHost('other.hairvision-ai.test')->postJson('/api/v1/landing/events', [
            ...$payload,
            'event_id' => (string) Str::uuid(),
        ])->assertAccepted();
        $this->assertDatabaseMissing('tenant_landing_events', ['tenant_id' => $otherTenant->id]);

        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09123456789',
            'phone_normalized' => '09123456789',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/landing/events', [
                'visit_token' => $token,
                'event_id' => (string) Str::uuid(),
                'event_type' => LandingEventCatalog::OTP_VERIFIED,
                'category_id' => $category->uuid,
            ])
            ->assertAccepted();

        $this->assertDatabaseHas('tenant_landing_page_visits', [
            'id' => $visit->id,
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
        ]);
        $this->assertSame(2, TenantLandingEvent::query()
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->count());
    }

    /**
     * Verify managed SEO and approved tracker fragments render on tenant landings.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_managed_seo_and_tracker_fragments_render_on_the_tenant_landing(): void
    {
        $tenant = $this->createTenant('کلینیک آریا', 'aria', 'aria.hairvision-ai.test');
        $template = Template::create(array_merge(TemplateSeeder::classicContent(), [
            'code' => 'analytics-seo',
            'name' => 'Analytics SEO',
            'seo_title' => 'پیش‌نمایش کلینیک آریا',
            'seo_description' => 'توضیح اختصاصی خدمات کلینیک آریا',
            'seo_image_path' => '/theme/category-images/hair-transplant.jpg',
            'seo_robots' => 'index,follow',
            'tracking_head_code' => '<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST" async></script>',
            'tracking_body_code' => '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TEST"></iframe></noscript>',
            'is_active' => true,
            'is_default' => false,
        ]));
        TenantThemeSetting::create([
            'tenant_id' => $tenant->id,
            'template_id' => $template->id,
            'use_template_content' => true,
        ]);

        $response = $this->forHost('aria.hairvision-ai.test')->get('/')->assertOk();

        $response
            ->assertSee('<title>پیش‌نمایش کلینیک آریا</title>', false)
            ->assertSee('name="description" content="توضیح اختصاصی خدمات کلینیک آریا"', false)
            ->assertSee('rel="canonical" href="https://aria.hairvision-ai.test/"', false)
            ->assertSee('property="og:image" content="https://aria.hairvision-ai.test/theme/category-images/hair-transplant.jpg"', false)
            ->assertSee('"@type":"MedicalClinic"', false)
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST', false)
            ->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-TEST', false);
    }

    /**
     * Verify system template management persists dedicated SEO and tracker fields.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_system_admin_can_store_template_seo_and_tracker_fields(): void
    {
        $systemUser = User::factory()->create([
            'phone_normalized' => '09120000001',
            'access_scope' => 'system',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $payload = array_merge(TemplateSeeder::classicContent(), [
            'name' => 'قالب تحلیل',
            'code' => 'analytics-template',
            'description' => 'قالب دارای تنظیمات سئو و تحلیل',
            'seo_title' => 'عنوان اختصاصی سئو',
            'seo_description' => 'توضیح اختصاصی سئو',
            'seo_robots' => 'noindex,follow',
            'tracking_head_code' => '<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>',
            'tracking_body_code' => '<noscript><img src="https://tracker.example.test/pixel.gif" alt=""></noscript>',
            'is_active' => true,
        ]);

        $this->actingAs($systemUser)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/templates', $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('templates', [
            'code' => 'analytics-template',
            'seo_title' => 'عنوان اختصاصی سئو',
            'seo_robots' => 'noindex,follow',
            'tracking_head_code' => $payload['tracking_head_code'],
            'tracking_body_code' => $payload['tracking_body_code'],
        ]);
    }

    /**
     * Verify tracker validation allows secure analytics tags and rejects active-page markup.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_tracker_fragment_rule_accepts_secure_tags_and_rejects_unsafe_markup(): void
    {
        $valid = Validator::make([
            'tracker' => '<script async src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script><script>window.dataLayer=window.dataLayer||[];</script>',
        ], ['tracker' => [new TrackingCodeFragment]]);
        $insecure = Validator::make([
            'tracker' => '<script src="http://tracker.example.test/tag.js"></script>',
        ], ['tracker' => [new TrackingCodeFragment]]);
        $form = Validator::make([
            'tracker' => '<form action="https://tracker.example.test"><input name="phone"></form>',
        ], ['tracker' => [new TrackingCodeFragment]]);
        $unquoted = Validator::make([
            'tracker' => '<img src=javascript:alert(1)>',
        ], ['tracker' => [new TrackingCodeFragment]]);

        $this->assertFalse($valid->fails());
        $this->assertTrue($insecure->fails());
        $this->assertTrue($form->fails());
        $this->assertTrue($unquoted->fails());
    }

    /**
     * Verify every server-allowlisted funnel stage is wired to the public client contract.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_public_client_wires_every_allowlisted_funnel_event_without_pii_fields(): void
    {
        $javascript = (string) file_get_contents(resource_path('js/landing.js'));

        foreach (LandingEventCatalog::all() as $eventType) {
            $this->assertStringContainsString("trackLandingEvent('{$eventType}'", $javascript);
        }

        $this->assertStringContainsString('window.dataLayer.push(publicEvent);', $javascript);
        $this->assertStringContainsString("new CustomEvent('hairvision:analytics'", $javascript);
        $this->assertStringNotContainsString('phone_normalized:', $javascript);
    }

    /**
     * Verify cohort metrics count visits, unique visitors, clicks, and conversions correctly.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_performance_report_builds_tenant_scoped_cohort_metrics(): void
    {
        $tenant = $this->createTenant('کلینیک آریا', 'aria', 'aria.hairvision-ai.test');
        $otherTenant = $this->createTenant('کلینیک دیگر', 'other', 'other.hairvision-ai.test');
        $category = AiModelCategory::firstOrCreate([
            'code' => 'hair_transplant',
        ], [
            'name' => 'کاشت مو',
            'status' => 'active',
        ]);
        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09121111111',
            'phone_normalized' => '09121111111',
            'phone_verified_at' => now()->subDays(2),
            'status' => 'new',
        ]);
        $otherLead = Lead::create([
            'tenant_id' => $otherTenant->id,
            'phone' => '09122222222',
            'phone_normalized' => '09122222222',
            'phone_verified_at' => now()->subDay(),
            'status' => 'new',
        ]);
        $firstVisit = $this->createVisit($tenant, now()->subDays(2));
        $firstVisit->update(['lead_id' => $lead->id]);
        $secondVisit = $this->createVisit($tenant, now()->subDay());
        $secondVisit->update(['lead_id' => $lead->id]);
        $otherVisit = $this->createVisit($otherTenant, now()->subDay());
        $otherVisit->update(['lead_id' => $otherLead->id]);

        $this->createEvent($firstVisit, LandingEventCatalog::APPLICATION_CLICKED, $category);
        $this->createEvent($firstVisit, LandingEventCatalog::APPLICATION_CLICKED, $category);
        $this->createEvent($firstVisit, LandingEventCatalog::PHONE_SUBMITTED, $category);
        $this->createEvent($firstVisit, LandingEventCatalog::GENERATION_REQUESTED, $category);
        $this->createEvent($otherVisit, LandingEventCatalog::GENERATION_REQUESTED, $category);

        $report = app(ClinicPerformanceReportService::class)->report(
            $tenant,
            CarbonImmutable::now()->subMonth()->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );

        $this->assertSame(2, $report['totals']['landing_views']);
        $this->assertSame(1, $report['totals']['unique_visitors']);
        $this->assertSame(1, $report['totals']['total_leads']);
        $this->assertSame(2, $report['totals']['application_clicks']);
        $this->assertSame(1, $report['totals']['application_visitors']);
        $this->assertSame(1, $report['totals']['phone_submitted']);
        $this->assertSame(1, $report['totals']['generation_requested']);
        $this->assertSame(100.0, $report['totals']['overall_conversion_rate']);
        $this->assertSame(2, $report['applications'][0]['clicks']);
        $this->assertSame(1, $report['applications'][0]['unique_visitors']);
        $this->assertSame(100.0, $report['applications'][0]['conversion_rate']);
        $this->assertSame(2, collect($report['monthly'])->sum('landing_views'));
        $expectedJalaliMonth = substr((string) JalaliDate::toJalaliDate($firstVisit->visited_at), 0, 7);
        $this->assertSame(2, collect($report['monthly'])->firstWhere('month', $expectedJalaliMonth)['landing_views']);
        $this->assertSame(1, collect($report['monthly'])->firstWhere('month', $expectedJalaliMonth)['unique_visitors']);
        $this->assertSame(1, collect($report['monthly'])->firstWhere('month', $expectedJalaliMonth)['total_leads']);
        $this->assertNotContains($firstVisit->visited_at->format('Y/m'), collect($report['monthly'])->pluck('month'));

        $recent = app(ClinicPerformanceReportService::class)->recentDailySummary($tenant);
        $this->assertCount(7, $recent['daily']);
        $this->assertSame(2, $recent['totals']['landing_views']);
        $this->assertSame(1, $recent['totals']['unique_visitors']);
        $this->assertSame(1, $recent['totals']['leads']);
        $this->assertSame(1, $recent['totals']['generation_requested']);
        $this->assertSame(100.0, $recent['totals']['conversion_rate']);
        $this->assertMatchesRegularExpression('/^(0[1-9]|1[0-2])\/(0[1-9]|[12]\d|3[01])$/', $recent['daily'][0]['label']);

        $systemReport = app(ClinicPerformanceReportService::class)->systemReport(
            CarbonImmutable::now()->subMonth()->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
        $this->assertSame(3, $systemReport['totals']['landing_views']);
        $this->assertSame(2, $systemReport['totals']['unique_visitors']);
        $this->assertSame(2, $systemReport['totals']['total_leads']);
        $this->assertSame(2, $systemReport['totals']['generation_requested']);
    }

    /**
     * Verify performance-report shortcuts use exact Jalali month boundaries.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_performance_report_exposes_current_and_previous_jalali_month_shortcuts(): void
    {
        $fixedNow = CarbonImmutable::instance(JalaliDate::fromJalaliDate('1405/07/15'))
            ->setTime(12, 0);
        CarbonImmutable::setTestNow($fixedNow);

        try {
            $tenant = $this->createTenant('کلینیک آریا', 'aria', 'aria.hairvision-ai.test');
            $clinicUser = User::factory()->create([
                'phone_normalized' => '09121111111',
                'access_scope' => 'clinic',
                'role' => 'clinic_admin',
                'status' => 'active',
            ]);
            $tenant->users()->attach($clinicUser->id, ['role' => 'clinic_admin', 'status' => 'active']);
            $this->activateSubscription($clinicUser);

            $quickRanges = app(ClinicPerformanceReportService::class)->quickRanges();

            $this->assertSame([
                'date_from' => '1405/07/01',
                'date_until' => '1405/07/15',
            ], $quickRanges['current_month']);
            $this->assertSame([
                'date_from' => '1405/06/01',
                'date_until' => '1405/06/31',
            ], $quickRanges['previous_month']);

            $this->actingAs($clinicUser)
                ->withSession(['admin_active_tenant_id' => $tenant->id])
                ->forHost('hairvision-ai.test')
                ->get('/admin/reports/performance')
                ->assertOk()
                ->assertSee('data-performance-quick-ranges', false)
                ->assertSee(__('reports.current_month'))
                ->assertSee(__('reports.previous_month'))
                ->assertSee(__('reports.default_range'))
                ->assertSee(route('admin.reports', $quickRanges['current_month']))
                ->assertSee(route('admin.reports', $quickRanges['previous_month']));

            $this->actingAs($clinicUser)
                ->withSession(['admin_active_tenant_id' => $tenant->id])
                ->forHost('hairvision-ai.test')
                ->get(route('admin.reports', $quickRanges['current_month']))
                ->assertOk()
                ->assertSee('name="date_from" value="1405/07/01"', false)
                ->assertSee('name="date_until" value="1405/07/15"', false);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Verify clinic users receive their own report while the clinic list stays system-only.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_clinic_report_is_available_but_management_clinic_list_is_system_only(): void
    {
        $tenant = $this->createTenant('کلینیک آریا', 'aria', 'aria.hairvision-ai.test');
        $clinicUser = User::factory()->create([
            'phone_normalized' => '09121111111',
            'access_scope' => 'clinic',
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);
        $tenant->users()->attach($clinicUser->id, ['role' => 'clinic_admin', 'status' => 'active']);
        $this->activateSubscription($clinicUser);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/performance')
            ->assertOk()
            ->assertSee(__('reports.funnel_title'))
            ->assertSee(__('reports.application_clicks_title'))
            ->assertSee(__('reports.unique_visitors'))
            ->assertSee(__('reports.total_leads'))
            ->assertSee(__('reports.overall_conversion_rate'));

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/performance?clinic_id='.$tenant->id)
            ->assertRedirect()
            ->assertSessionHasErrors('clinic_id');

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.performance.title'))
            ->assertSee(__('dashboard.performance.unique_visitors'))
            ->assertSee(__('dashboard.performance.conversion_rate'))
            ->assertSee(__('dashboard.performance.view_report'))
            ->assertSee('href="'.route('admin.reports').'"', false)
            ->assertDontSee(__('dashboard.plan.status_title'));

        $export = $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/performance/export')
            ->assertOk()
            ->assertDownload('hairvision-clinic-performance.csv');
        $exportedContent = $export->streamedContent();
        $this->assertStringContainsString(__('reports.monthly_table_title'), $exportedContent);
        $this->assertStringContainsString(__('reports.application_clicks_title'), $exportedContent);
        $this->assertStringContainsString(substr((string) JalaliDate::toJalaliDate(CarbonImmutable::now()), 0, 7), $exportedContent);
        $this->assertStringNotContainsString(CarbonImmutable::now()->format('Y/m'), $exportedContent);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/management')
            ->assertForbidden();

        $systemUser = User::factory()->create([
            'phone_normalized' => '09122222222',
            'access_scope' => 'system',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($systemUser)
            ->withSession(['admin_active_tenant_id' => null])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/management')
            ->assertOk()
            ->assertSee($tenant->name)
            ->assertSee(route('admin.reports', ['clinic_id' => $tenant->id]), false);

        $this->actingAs($systemUser)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/performance')
            ->assertOk()
            ->assertSee(__('reports.funnel_title'))
            ->assertSee(__('reports.all_clinics'))
            ->assertSee($tenant->name)
            ->assertSee('name="clinic_id"', false)
            ->assertDontSee('برای مشاهده گزارش عملکرد، یک کلینیک را از انتخاب‌گر بالای داشبورد انتخاب کنید.');

        $this->actingAs($systemUser)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/performance?clinic_id='.$tenant->id)
            ->assertOk()
            ->assertSee(__('reports.report_scope').': '.$tenant->name)
            ->assertSee(route('admin.reports', array_merge(
                ['clinic_id' => $tenant->id],
                app(ClinicPerformanceReportService::class)->quickRanges()['current_month'],
            )))
            ->assertDontSee('برای مشاهده گزارش عملکرد، یک کلینیک را از انتخاب‌گر بالای داشبورد انتخاب کنید.');

        $this->actingAs($systemUser)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/reports/performance/export')
            ->assertOk()
            ->assertDownload('hairvision-system-performance.csv');
    }

    /**
     * Create an active tenant and host mapping.
     *
     * @param  string  $name  Clinic name.
     * @param  string  $slug  Clinic slug.
     * @param  string  $domain  Clinic domain.
     * @return Tenant Created tenant.
     *
     * @since 2.1.0
     */
    private function createTenant(string $name, string $slug, string $domain): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => $slug, 'status' => 'active']);
        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        return $tenant;
    }

    /**
     * Create one anonymous tenant landing visit.
     *
     * @param  Tenant  $tenant  Owning tenant.
     * @param  mixed  $visitedAt  Visit timestamp.
     * @return TenantLandingPageVisit Created visit.
     *
     * @since 2.1.0
     */
    private function createVisit(Tenant $tenant, mixed $visitedAt): TenantLandingPageVisit
    {
        return TenantLandingPageVisit::create([
            'tenant_id' => $tenant->id,
            'host' => $tenant->domains()->value('domain'),
            'analytics_token_hash' => hash('sha256', (string) Str::uuid()),
            'visited_at' => $visitedAt,
        ]);
    }

    /**
     * Create one landing event for a visit and application category.
     *
     * @param  TenantLandingPageVisit  $visit  Owning visit.
     * @param  string  $eventType  Stable event type.
     * @param  AiModelCategory  $category  Application category.
     * @return TenantLandingEvent Created event.
     *
     * @since 2.1.0
     */
    private function createEvent(
        TenantLandingPageVisit $visit,
        string $eventType,
        AiModelCategory $category,
    ): TenantLandingEvent {
        return TenantLandingEvent::create([
            'tenant_id' => $visit->tenant_id,
            'tenant_landing_page_visit_id' => $visit->id,
            'ai_model_category_id' => $category->id,
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'category_code' => $category->code,
            'category_name' => $category->name,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Give a clinic dashboard user active-plan access.
     *
     * @param  User  $user  Clinic dashboard user.
     * @return Subscription Active subscription.
     *
     * @since 2.1.0
     */
    private function activateSubscription(User $user): Subscription
    {
        $plan = Plan::create([
            'name' => 'پلن تست',
            'description' => 'پلن تست',
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => 30,
            'usage_limit' => 100,
            'status' => 'active',
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => 100,
            'usage_used' => 0,
        ]);
    }

    /**
     * Configure the test client for a specific request host.
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
