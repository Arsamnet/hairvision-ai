<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\OtpCode;
use App\Models\Plan;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

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
        $this->forHost('hairvision-ai.test')
            ->get('/')
            ->assertOk()
            ->assertSee('HairVision AI')
            ->assertSee('/admin');
    }

    public function test_admin_otp_login_stores_hash_and_authenticates_user(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09217359491');
        $this->enableIppanelOtpGateway();

        $response = $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/request', ['phone' => '09217359491'])
            ->assertRedirect(route('admin.otp.form'));

        $this->assertFalse($response->getSession()->has('debug_sms'));

        $this->forHost('hairvision-ai.test')
            ->get('/admin/otp')
            ->assertOk()
            ->assertDontSee('کد ورود آزمایشی');

        $code = $this->latestSentOtpCode();

        $otp = OtpCode::query()->where('purpose', 'admin_login')->firstOrFail();
        $this->assertSame($tenant->id, $otp->tenant_id);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));

        $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/verify', [
                'phone' => '09217359491',
                'code' => $code,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($otp->refresh()->verified_at);
    }

    public function test_expired_admin_session_redirects_to_admin_login(): void
    {
        $this->forHost('hairvision-ai.test')
            ->get('/admin/leads')
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

        $this->forHost('hairvision-ai.test')
            ->get('/admin')
            ->assertOk()
            ->assertSee('data-admin-theme-script', false)
            ->assertSee('hairvision.admin.theme', false)
            ->assertSee('admin-auth-shell', false);

        $this->withSession(['admin_pending_phone' => '09217359491'])
            ->forHost('hairvision-ai.test')
            ->get('/admin/otp')
            ->assertOk()
            ->assertSee('data-admin-theme-script', false)
            ->assertSee('admin-auth-shell', false);
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

    public function test_database_seeder_creates_super_admin_and_clinic_demo_data(): void
    {
        config(['hairvision.super_admin.phone' => '09129999999']);

        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'clinic1')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'superadmin@example.com',
            'phone_normalized' => '09129999999',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'editor@example.com',
            'role' => 'editor',
        ]);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->assertSame(5, $tenant->leads()->count());
        $this->assertSame(1, $tenant->leads()->where('crm_stage', 'converted')->count());
        $this->assertSame(1, $tenant->leads()->where('status', 'archived')->count());
    }

    public function test_admin_lead_list_is_scoped_to_active_tenant(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $user = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $leadA = $this->createLead($tenantA, '09120000001', 'Tenant A Lead');
        $leadB = $this->createLead($tenantB, '09120000002', 'Tenant B Lead');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads')
            ->assertOk()
            ->assertSee($leadA->full_name)
            ->assertDontSee($leadB->full_name);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$leadB->id)
            ->assertForbidden();
    }

    public function test_viewer_cannot_edit_leads_and_editor_can_only_update_limited_fields(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $viewer = $this->createUserForTenant($tenant, 'viewer', '09122222222');
        $editor = $this->createUserForTenant($tenant, 'editor', '09123333333');
        $this->activateSubscription($viewer);
        $this->activateSubscription($editor);
        $lead = $this->createLead($tenant, '09124444444', 'Original Name');

        $this->actingAs($viewer)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/edit')
            ->assertForbidden();

        $this->actingAs($editor)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/leads/'.$lead->id, [
                'full_name' => 'Changed Name',
                'phone_normalized' => '09125555555',
                'source' => 'phone',
                'crm_stage' => 'needs_follow_up',
                'status' => 'archived',
            ])
            ->assertRedirect(route('admin.leads.show', $lead));

        $lead->refresh();
        $this->assertSame('Original Name', $lead->full_name);
        $this->assertSame('09124444444', $lead->phone_normalized);
        $this->assertSame('phone', $lead->source);
        $this->assertSame('needs_follow_up', $lead->crm_stage);
        $this->assertSame('verified', $lead->status);
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
            'status' => 'verified',
            'crm_stage' => 'new_lead',
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
            'https://edge.ippanel.com/v1/api/send' => Http::response(['ok' => true]),
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
