<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\OtpCode;
use App\Models\Plan;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Admin\LeadDashboardService;
use App\Support\StateCatalog;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verifies public-root, dashboard, lead-management, and administrator behavior.
 *
 * @since 1.0.0
 * @since 2.1.0 Coverage expanded for unified lead status and demographics.
 * @since 2.1.1 Covers latest-note tooltips in tenant-scoped lead lists.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.debug' => true,
            'hairvision.admin_otp.max_requests' => 2,
            'hairvision.admin_otp.decay_seconds' => 600,
            'hairvision.admin_otp.max_verify_attempts' => 5,
            'hairvision.admin_otp.verify_decay_seconds' => 600,
            'hairvision.admin_otp.max_attempts' => 5,
        ]);
    }

    public function test_primary_root_displays_public_product_landing_page(): void
    {
        $tenant = Tenant::create([
            'name' => 'Clinic One',
            'slug' => 'arya',
            'status' => 'active',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'hairvision-ai.test',
            'type' => 'platform_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        $this->forHost('hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('HairVision')
            ->assertSee('/admin')
            ->assertSee('data-hairvision-copyright', false)
            ->assertSee(__('landing_templates.system_copyright'))
            ->assertSee('href="https://hairvision-ai.test"', false)
            ->assertDontSee('تصویر خود را ثبت کنید');

        $this->assertDatabaseCount('tenant_landing_page_visits', 0);
    }

    public function test_primary_host_does_not_expose_public_tenant_api(): void
    {
        $tenant = Tenant::create([
            'name' => 'Clinic One',
            'slug' => 'arya',
            'status' => 'active',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'hairvision-ai.test',
            'type' => 'platform_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        $this->forHost('hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertNotFound();
    }

    public function test_admin_otp_login_stores_hash_and_authenticates_user(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09217359491');
        $this->enableIppanelOtpGateway();

        $response = $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/request', ['phone' => '09217359491'])
            ->assertRedirect(route('admin.otp.form'));

        $this->assertFalse($response->getSession()->has('debug_sms'));

        $this->forHost('hairvision-ai.test')
            ->get('/admin/otp')
            ->assertOk()
            ->assertDontSee('کد ورود آزمایشی')
            ->assertSee('/theme/admin-logo-light.svg', false)
            ->assertSee('id="adminOtpCountdown"', false)
            ->assertSee('id="adminResendOtp"', false)
            ->assertSee('id="adminOtpResendForm"', false)
            ->assertSee('ارسال مجدد کد')
            ->assertDontSee('ارسال مجدد تا');

        $code = $this->latestSentOtpCode();

        $otp = OtpCode::query()->where('purpose', 'admin_login')->firstOrFail();
        $this->assertSame($tenant->id, $otp->tenant_id);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));

        $verifyResponse = $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/verify', [
                'phone' => '09217359491',
                'code' => $code,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(
            $verifyResponse->getSession()->has('login_web_'.sha1(SessionGuard::class)),
            'Admin OTP login should persist the web guard user id in the scoped admin session.'
        );
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($otp->refresh()->verified_at);

        $this->app['auth']->forgetGuards();

        $this->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_expired_admin_session_redirects_to_admin_login(): void
    {
        $this->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads')
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_theme_defaults_to_light_until_user_persists_dark_mode(): void
    {
        $themeScript = file_get_contents(resource_path('views/admin/partials/theme-script.blade.php'));
        $themeModule = file_get_contents(resource_path('js/admin/theme.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("const defaultTheme = 'light';", $themeModule);
        $this->assertStringContainsString(": 'light';", $themeScript);
        $this->assertStringNotContainsString('prefers-color-scheme', $themeScript);
        $this->assertStringNotContainsString('prefers-color-scheme', $themeModule);
        $this->assertStringContainsString('.admin-auth-shell', $css);
        $this->assertStringContainsString('.hover\\:bg-zinc-50\\/80:hover', $css);
        $this->assertStringContainsString('[data-admin-table-container] tbody tr:hover', $css);
        $this->assertStringContainsString('[data-admin-table-container] tbody tr[data-clickable-table-row="true"]', $css);
        $this->assertStringContainsString("font-family: 'Vazirmatn FD';", $css);
        $this->assertStringContainsString('Vazirmatn-FD-Regular.woff2', $css);
        $this->assertStringContainsString(':where(.admin-shell, .admin-auth-shell)', $css);
        $this->assertStringContainsString('.admin-auth-theme-toggle', $css);
        $this->assertStringContainsString('--admin-brand-start: #8b5cf6;', $css);
        $this->assertStringContainsString('--admin-brand-end: #14b8a6;', $css);
        $this->assertStringContainsString('background-image: var(--admin-brand-gradient) !important;', $css);
        $this->assertStringContainsString('.divide-zinc-100 > :not(:last-child)', $css);
        $this->assertStringContainsString('.disabled\\:bg-zinc-100:disabled', $css);
        $this->assertStringContainsString("window.addEventListener('storage'", $themeModule);
        $layout = file_get_contents(resource_path('views/admin/layout.blade.php'));
        $breadcrumbs = file_get_contents(resource_path('views/components/admin/breadcrumbs.blade.php'));
        $this->assertStringContainsString('admin-content min-h-0 flex-1 overflow-y-auto p-5', $layout);
        $this->assertStringContainsString('\\App\\Support\\AdminNavigation::groups()', $layout);
        $this->assertStringContainsString('<nav {{ $attributes->merge([\'class\' => \'admin-breadcrumbs\']) }}', $breadcrumbs);
        $this->assertStringNotContainsString('admin-breadcrumbs-inner', $breadcrumbs);

        $this->forHost('hairvision-ai.test')
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-admin-theme-script', false)
            ->assertSee('hairvision.admin.theme', false)
            ->assertSee('admin-auth-shell', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('/theme/admin-logo-light.svg', false)
            ->assertSee('/theme/admin-logo-dark.svg', false);

        $this->withSession(['admin_pending_phone' => '09217359491'])
            ->forHost('hairvision-ai.test')
            ->get('/admin/otp')
            ->assertOk()
            ->assertSee('data-admin-theme-script', false)
            ->assertSee('admin-auth-shell', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('/theme/admin-logo-light.svg', false)
            ->assertSee('/theme/admin-logo-dark.svg', false);
    }

    public function test_admin_buttons_use_shared_component_with_pointer_cursor(): void
    {
        $button = Blade::render('<x-admin.button type="submit">ثبت</x-admin.button>');
        $link = Blade::render('<x-admin.button href="/admin" variant="secondary">بازگشت</x-admin.button>');

        $this->assertStringContainsString('<button', $button);
        $this->assertStringContainsString('cursor-pointer', $button);
        $this->assertStringContainsString('data-admin-button', $button);
        $this->assertStringContainsString('<a href="/admin"', $link);
        $this->assertStringContainsString('cursor-pointer', $link);
        $this->assertStringContainsString('data-admin-button', $link);

        $viewFiles = collect([
            resource_path('views/admin'),
            resource_path('views/components/admin'),
        ])->flatMap(fn (string $path) => File::allFiles($path));

        foreach ($viewFiles as $file) {
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());

            if ($relativePath === 'resources/views/components/admin/button.blade.php') {
                continue;
            }

            $this->assertStringNotContainsString(
                '<button',
                $file->getContents(),
                $relativePath.' should use x-admin.button instead of a raw button element.'
            );
        }
    }

    public function test_admin_empty_record_states_use_the_shared_component_with_specific_messages(): void
    {
        $emptyState = Blade::render('<x-admin.empty-state icon="bell">هنوز پیامی ثبت نشده است.</x-admin.empty-state>');

        $this->assertStringContainsString('data-admin-empty-state', $emptyState);
        $this->assertStringContainsString('هنوز پیامی ثبت نشده است.', $emptyState);

        $expectedBindings = [
            'resources/views/admin/notifications/index.blade.php' => ['notifications.empty_inbox'],
            'resources/views/admin/system/notifications/index.blade.php' => ['notifications.empty_management'],
            'resources/views/admin/system/ai-model-categories/index.blade.php' => ['ai_models.empty.categories'],
            'resources/views/admin/system/ai-model-categories/show.blade.php' => ['ai_models.empty.category_outputs'],
            'resources/views/admin/dashboard.blade.php' => ['dashboard.plan.no_service_credit', 'dashboard.empty_recent_leads'],
            'resources/views/admin/billing/index.blade.php' => ['dashboard.plan.no_service_credit', 'payments.empty_invoices'],
            'resources/views/admin/reports.blade.php' => ['reports.empty', 'reports.no_application_clicks'],
            'resources/views/admin/reports/credit-usage.blade.php' => ['dashboard.plan.no_service_credit', 'reports.credit_usage_empty'],
            'resources/views/admin/leads/show.blade.php' => ['leads.uploads.empty', 'leads.generations.empty', 'crm.empty'],
            'resources/views/admin/users/show.blade.php' => ['system.users.no_clinics'],
            'resources/views/admin/ai-models/show.blade.php' => ['ai_models.empty.credentials'],
            'resources/views/admin/system/permissions/index.blade.php' => ['system.empty_permissions'],
        ];

        foreach ($expectedBindings as $relativePath => $translationKeys) {
            $contents = file_get_contents(base_path($relativePath));

            foreach ($translationKeys as $translationKey) {
                $this->assertMatchesRegularExpression(
                    '/<x-admin\.empty-state\b[^>]*>.*?'.preg_quote("__('{$translationKey}')", '/').'.*?<\/x-admin\.empty-state>/s',
                    $contents,
                    $relativePath.' should render '.$translationKey.' through x-admin.empty-state.'
                );
            }
        }

        $adminViews = File::allFiles(resource_path('views/admin'));

        foreach ($adminViews as $view) {
            $this->assertStringNotContainsString(
                ">{{ __('messages.none') }}</x-admin.empty-state>",
                $view->getContents(),
                $view->getRelativePathname().' should use a meaningful empty-state message instead of messages.none.'
            );
        }
    }

    public function test_notification_surfaces_use_the_shared_rounded_bell_icon(): void
    {
        $bell = Blade::render('<x-admin.icon name="bell" class="h-5 w-5" />');
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('fi-rr-bell-ring', $bell);
        $this->assertStringContainsString('data-menu-icon="bell"', $bell);
        $this->assertStringNotContainsString('<svg', $bell);
        $this->assertStringContainsString('.fi-rr-bell-ring::before { content: "\\f235"; }', $css);

        $notificationViews = [
            'resources/views/admin/layout.blade.php',
            'resources/views/admin/notifications/index.blade.php',
            'resources/views/admin/system/notifications/index.blade.php',
        ];

        foreach ($notificationViews as $relativePath) {
            $this->assertStringContainsString(
                'bell',
                file_get_contents(base_path($relativePath)),
                $relativePath.' should use the shared bell icon for notifications.'
            );
        }
    }

    public function test_table_view_actions_make_dashboard_rows_clickable(): void
    {
        $menu = Blade::render('<x-admin.table.action-menu><x-admin.table.action-item href="/admin/crm/leads/1" icon="eye">مشاهده</x-admin.table.action-item></x-admin.table.action-menu>');
        $viewAction = Blade::render('<x-admin.table.action-item href="/admin/crm/leads/1" icon="eye">مشاهده</x-admin.table.action-item>');
        $editAction = Blade::render('<x-admin.table.action-item href="/admin/crm/leads/1/edit" icon="edit">ویرایش</x-admin.table.action-item>');
        $actionMenus = file_get_contents(resource_path('js/admin/action-menus.js'));

        $this->assertStringContainsString('data-table-action-menu-panel', $menu);
        $this->assertStringContainsString('data-table-row-view-action', $viewAction);
        $this->assertStringNotContainsString('data-table-row-view-action', $editAction);
        $this->assertStringContainsString('data-clickable-table-row', $actionMenus);
        $this->assertStringContainsString('window.location.assign(href)', $actionMenus);
        $this->assertStringContainsString('viewAction.click()', $actionMenus);
        $this->assertStringContainsString('[data-table-action-menu]', $actionMenus);
        $this->assertStringContainsString('positionActionMenu', $actionMenus);
        $this->assertStringContainsString("panel.style.position = 'fixed';", $actionMenus);
    }

    public function test_database_seeder_creates_super_admin_and_clinic_demo_data(): void
    {
        config([
            'hairvision.super_admin.email' => 'satrapit@gmail.com',
            'hairvision.super_admin.phone' => '09124248164',
        ]);

        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'satrapit@gmail.com',
            'phone_normalized' => '09124248164',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'editor@example.com',
            'role' => 'editor',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'کامران فضلی',
            'phone_normalized' => '09127072327',
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        $demoTenant = Tenant::query()->where('slug', 'demo')->firstOrFail();

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $demoTenant->id,
            'user_id' => User::query()->where('phone_normalized', '09127072327')->firstOrFail()->id,
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->assertSame(8, $tenant->leads()->count());
        $this->assertSame(1, $tenant->leads()->where('status', 'successful')->count());
        $this->assertSame(2, $tenant->leads()->where('status', 'canceled')->count());
        $expectedStatuses = collect(array_keys(__('leads.statuses')))->sort()->values()->all();
        $seededStatuses = $tenant->leads()->distinct()->pluck('status')->sort()->values()->all();

        $this->assertSame($expectedStatuses, $seededStatuses);

        $this->assertSame(__('leads.statuses'), StateCatalog::options('leads.statuses'));

        $activeSubscription = Subscription::query()
            ->with(['plan', 'serviceEntitlements'])
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->firstOrFail();
        $nojaTenant = Tenant::query()->where('slug', 'noja')->firstOrFail();
        $nojaSubscription = Subscription::query()
            ->with(['plan', 'serviceEntitlements'])
            ->where('tenant_id', $nojaTenant->id)
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame('پلن شروع', $activeSubscription->plan->name);
        $this->assertSame(365, $activeSubscription->plan->duration_days);
        $this->assertSame('ریال', $activeSubscription->plan->currency);
        $this->assertSame(1200, $activeSubscription->usage_limit);
        $this->assertTrue((bool) data_get($activeSubscription->plan->features_json, 'ai_generation'));
        $this->assertSame(1200, $activeSubscription->serviceEntitlements->sum('included_credit'));
        $this->assertSame(600, $activeSubscription->serviceEntitlements->firstWhere('service_key', 'hair_transplant')?->included_credit);
        $this->assertSame(600, $activeSubscription->serviceEntitlements->firstWhere('service_key', 'eyebrow_transplant')?->included_credit);
        $this->assertDatabaseHas('plans', [
            'name' => 'پلن شروع کاشت مو',
            'description' => 'پلن آغازین کاشت مو',
            'plan_type' => 'base_subscription',
            'price' => 3500000000,
            'currency' => 'ریال',
            'usage_limit' => 600,
        ]);
        $this->assertDatabaseHas('plans', [
            'name' => 'افزودن کاشت ابرو',
            'plan_type' => 'service_add_on',
            'price' => 500000000,
            'currency' => 'ریال',
            'usage_limit' => 600,
        ]);
        $this->assertDatabaseHas('plans', [
            'name' => 'بسته افزایش اعتبار کاشت مو و ابرو',
            'plan_type' => 'credit_top_up',
            'price' => 1000000000,
            'currency' => 'ریال',
            'usage_limit' => 1200,
        ]);
        $this->assertSame('پلن شروع کاشت مو', $nojaSubscription->plan->name);
        $this->assertNull($nojaSubscription->user_id);
        $this->assertSame(600, $nojaSubscription->usage_limit);
        $this->assertSame(600, $nojaSubscription->serviceEntitlements->firstWhere('service_key', 'hair_transplant')?->included_credit);
        $this->assertNull($nojaSubscription->serviceEntitlements->firstWhere('service_key', 'eyebrow_transplant'));
    }

    public function test_admin_lead_list_is_scoped_to_active_tenant(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $user = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $leadA = $this->createLead($tenantA, '09120000001', 'Tenant A Lead');
        $leadB = $this->createLead($tenantB, '09120000002', 'Tenant B Lead');
        LeadNote::create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'user_id' => $user->id,
            'note' => 'یادداشت قدیمی لید آریا',
        ]);
        LeadNote::create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'user_id' => $user->id,
            'note' => 'آخرین یادداشت لید آریا',
        ]);
        LeadNote::create([
            'tenant_id' => $tenantB->id,
            'lead_id' => $leadB->id,
            'note' => 'یادداشت محرمانه کلینیک دیگر',
        ]);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads')
            ->assertOk()
            ->assertSee($leadA->full_name)
            ->assertSee('آخرین یادداشت لید آریا')
            ->assertDontSee('یادداشت قدیمی لید آریا')
            ->assertSee('role="tooltip"', false)
            ->assertSee('border-dotted', false)
            ->assertDontSee($leadB->full_name)
            ->assertDontSee('یادداشت محرمانه کلینیک دیگر');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$leadB->id)
            ->assertForbidden();
    }

    public function test_dashboard_rejects_duplicate_lead_phone_and_preserves_form_input(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $existingLead = $this->createLead($tenant, '09124444444', 'Original Lead');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->from('/admin/crm/leads/create')
            ->post('/admin/crm/leads', [
                'full_name' => 'Duplicate Lead',
                'phone_normalized' => '۰۹۱۲۴۴۴۴۴۴۴',
                'age' => '۳۴',
                'city' => 'تهران',
                'source' => 'dashboard',
                'status' => 'new',
                'assigned_user_id' => $user->id,
                'note' => 'This note must not be stored.',
            ])
            ->assertRedirect('/admin/crm/leads/create')
            ->assertSessionHasErrors([
                'phone_normalized' => 'این شماره موبایل قبلاً ثبت شده است.',
            ])
            ->assertSessionHasInput('full_name', 'Duplicate Lead')
            ->assertSessionHasInput('phone_normalized', '09124444444');

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('lead_notes', 0);
        $this->assertSame('Original Lead', $existingLead->fresh()->full_name);
        $this->assertSame('landing_page', $existingLead->fresh()->source);
    }

    public function test_dashboard_allows_the_same_lead_phone_in_another_tenant(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $user = $this->createUserForTenant($tenantB, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $this->createLead($tenantA, '09124444444', 'Tenant A Lead');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/crm/leads', [
                'full_name' => 'Tenant B Lead',
                'phone_normalized' => '09124444444',
                'age' => 34,
                'city' => 'تهران',
                'source' => 'dashboard',
                'status' => 'new',
                'assigned_user_id' => $user->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $tenantB->id,
            'full_name' => 'Tenant B Lead',
            'phone_normalized' => '09124444444',
        ]);
        $this->assertDatabaseCount('leads', 2);
    }

    public function test_dashboard_lead_service_rejects_a_duplicate_when_request_validation_is_bypassed(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $existingLead = $this->createLead($tenant, '09124444444', 'Original Lead');

        try {
            $this->app->make(LeadDashboardService::class)->create([
                'full_name' => 'Concurrent Duplicate',
                'phone_normalized' => '09124444444',
                'age' => 34,
                'city' => 'تهران',
                'source' => 'dashboard',
                'status' => 'new',
                'assigned_user_id' => $user->id,
                'note' => 'This note must not be stored.',
            ], $tenant, $user);

            $this->fail('A duplicate tenant lead must raise a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['phone_normalized' => ['این شماره موبایل قبلاً ثبت شده است.']],
                $exception->errors(),
            );
        }

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('lead_notes', 0);
        $this->assertSame('Original Lead', $existingLead->fresh()->full_name);
    }

    public function test_dashboard_lead_forms_use_one_status_and_persist_numeric_age_and_city(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/create')
            ->assertOk()
            ->assertSee('name="age"', false)
            ->assertSee('name="city"', false)
            ->assertSee('name="status"', false)
            ->assertDontSee('name="crm_stage"', false)
            ->assertSeeInOrder(['جدید', 'نیاز به پیگیری', 'عدم پاسخ', 'موفق شده', 'لغو شده']);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/crm/leads', [
                'full_name' => 'لید دارای مشخصات',
                'phone_normalized' => '۰۹۱۲۴۴۴۴۴۴۴',
                'age' => '۳۴',
                'city' => 'تهران',
                'source' => 'dashboard',
                'status' => 'canceled',
                'assigned_user_id' => $user->id,
            ])
            ->assertRedirect();

        $lead = Lead::query()->where('tenant_id', $tenant->id)->where('phone_normalized', '09124444444')->firstOrFail();
        $this->assertSame(34, $lead->age);
        $this->assertSame('تهران', $lead->city);
        $this->assertSame('canceled', $lead->status);

        $youngLead = $this->createLead($tenant, '09123333334', 'لید کم‌سن');
        $youngLead->forceFill(['age' => 25])->save();
        $olderLead = $this->createLead($tenant, '09123333335', 'لید پرسن');
        $olderLead->forceFill(['age' => 46])->save();

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads?age_from=۳۰&age_until=۴۰')
            ->assertOk()
            ->assertSee(__('leads.fields.age'))
            ->assertSee('34')
            ->assertSee('name="age_from"', false)
            ->assertSee('name="age_until"', false)
            ->assertSee('value="30"', false)
            ->assertSee('value="40"', false)
            ->assertDontSee($youngLead->full_name)
            ->assertDontSee($olderLead->full_name)
            ->assertSee('data-lead-status="canceled"', false)
            ->assertSee('bg-rose-50 text-rose-700', false);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/edit')
            ->assertOk()
            ->assertSee('name="age"', false)
            ->assertSee('name="city"', false)
            ->assertDontSee('name="crm_stage"', false);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/crm/leads/'.$lead->id, [
                'full_name' => $lead->full_name,
                'phone_normalized' => $lead->phone_normalized,
                'age' => '۳۵',
                'city' => 'کرج',
                'source' => $lead->source,
                'status' => 'successful',
                'assigned_user_id' => $user->id,
            ])
            ->assertRedirect(route('admin.leads.show', $lead));

        $lead->refresh();
        $this->assertSame(35, $lead->age);
        $this->assertSame('کرج', $lead->city);
        $this->assertSame('successful', $lead->status);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/crm/leads', [
                'phone_normalized' => '09125555555',
                'age' => 'سی و پنج',
                'status' => 'new',
            ])
            ->assertSessionHasErrors('age');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/crm/leads', [
                'phone_normalized' => '09126666666',
                'status' => 'verified',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_viewer_cannot_edit_leads_and_editor_can_only_update_limited_fields(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $viewer = $this->createUserForTenant($tenant, 'viewer', '09122222222');
        $editor = $this->createUserForTenant($tenant, 'editor', '09123333333');
        $this->activateSubscription($viewer);
        $this->activateSubscription($editor);
        $lead = $this->createLead($tenant, '09124444444', 'Original Name');

        $this->actingAs($viewer)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/edit')
            ->assertForbidden();

        $this->actingAs($editor)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/crm/leads/'.$lead->id, [
                'full_name' => 'Changed Name',
                'phone_normalized' => '09125555555',
                'age' => 99,
                'city' => 'Changed City',
                'source' => 'phone',
                'status' => 'needs_follow_up',
            ])
            ->assertRedirect(route('admin.leads.show', $lead));

        $lead->refresh();
        $this->assertSame('Original Name', $lead->full_name);
        $this->assertSame('09124444444', $lead->phone_normalized);
        $this->assertNull($lead->age);
        $this->assertNull($lead->city);
        $this->assertSame('phone', $lead->source);
        $this->assertSame('needs_follow_up', $lead->status);
    }

    private function createTenant(string $name, string $slug, string $domain): Tenant
    {
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        return $tenant;
    }

    private function createUserForTenant(Tenant $tenant, string $role, string $phone): User
    {
        $user = User::factory()->create([
            'phone_normalized' => $phone,
            'role' => $role,
            'status' => 'active',
        ]);

        $tenant->users()->attach($user->id, [
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }

    private function createLead(Tenant $tenant, string $phone, string $name): Lead
    {
        return Lead::create([
            'tenant_id' => $tenant->id,
            'full_name' => $name,
            'phone' => $phone,
            'phone_normalized' => $phone,
            'source' => 'landing_page',
            'phone_verified_at' => now(),
            'status' => 'new',
        ]);
    }

    private function activateSubscription(User $user): Subscription
    {
        $plan = Plan::query()->create([
            'name' => 'پلن تست',
            'description' => 'پلن تست',
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => 30,
            'usage_limit' => 100,
            'status' => 'active',
        ]);

        return Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => 100,
            'usage_used' => 0,
        ]);
    }

    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }

    private function enableIppanelOtpGateway(): SmsGateway
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        return SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => false,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);
    }

    private function latestSentOtpCode(): string
    {
        $record = Http::recorded()->last();
        $request = $record[0] ?? null;

        $code = $request['params']['verification-code'] ?? null;

        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);

        return $code;
    }
}
