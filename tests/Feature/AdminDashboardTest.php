<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\OtpCode;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Support\NumberNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09120000000');

        $response = $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/request', ['phone' => '09120000000'])
            ->assertRedirect(route('admin.otp.form'));

        $debugSms = $response->getSession()->get('debug_sms');
        $code = $this->extractDebugCode($debugSms);

        $otp = OtpCode::query()->where('purpose', 'admin_login')->firstOrFail();
        $this->assertSame($tenant->id, $otp->tenant_id);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));

        $this->forHost('hairvision-ai.test')
            ->post('/admin/otp/verify', [
                'phone' => '09120000000',
                'code' => $code,
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($otp->refresh()->verified_at);
    }

    public function test_database_seeder_creates_super_admin_and_clinic_demo_data(): void
    {
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

    private function extractDebugCode(string $debugSms): string
    {
        preg_match('/([0-9۰-۹٠-٩]{6})/u', $debugSms, $matches);

        return NumberNormalizer::toLatinDigits($matches[1]);
    }
}
