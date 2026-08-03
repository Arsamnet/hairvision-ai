<?php

namespace Tests\Feature;

use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\AiProvider;
use App\Models\ClinicUser;
use App\Models\CreditTransaction;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\GenerationRequest;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadProfile;
use App\Models\LeadSourceImage;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Upload;
use App\Models\User;
use App\Services\Admin\SmsGatewayManagementService;
use App\Services\AI\ClinicAiModelProvisioningService;
use App\Services\AI\ExecutableAiModelSyncService;
use App\Services\Auth\PermissionService;
use App\Services\Billing\BillingService;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Settings\SystemSettingService;
use App\Services\Sms\SmsGatewayService;
use App\Services\Visitor\PublicApplicationAvailabilityService;
use App\Support\JalaliDate;
use App\Support\StateCatalog;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verifies system-management, billing, CRM, and dashboard contracts.
 *
 * @since 1.0.0
 * @since 2.1.0 Coverage expanded for reports, lead state, and SMS safety.
 */
class SystemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_super_admin_has_all_permissions(): void
    {
        config(['hairvision.sms.ippanel.authorization_token' => null]);

        $this->seed(DatabaseSeeder::class);
        $preservedMerchantId = '33333333-3333-4333-8333-333333333333';
        PaymentGateway::query()
            ->where('provider_code', 'zarinpal')
            ->firstOrFail()
            ->forceFill(['encrypted_credentials' => ['merchant_id' => $preservedMerchantId]])
            ->save();
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $localGateway = SmsGateway::query()->system()->where('provider_code', 'local')->firstOrFail();
        $ippanelGateway = SmsGateway::query()->system()->where('provider_code', 'ippanel')->firstOrFail();
        $zarinpalGateway = PaymentGateway::query()->where('provider_code', 'zarinpal')->firstOrFail();

        $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
        $this->assertSame('system', $superAdmin->access_scope);
        $this->assertTrue($superAdmin->hasPermission('payment_gateways.manage'));
        $this->assertSame(Permission::query()->count('*'), Role::query()->where('code', 'super_admin')->firstOrFail()->permissions()->count('*'));
        $this->assertSame(1, SmsGateway::query()->system()->where('is_default', true)->count());
        $this->assertSame(1, SmsGateway::query()->system()->where('status', 'enabled')->count());
        $this->assertDatabaseHas('sms_gateways', [
            'owner_scope' => 'system',
            'provider_code' => 'local',
            'status' => 'enabled',
            'is_default' => true,
            'is_protected' => true,
        ]);
        $this->assertTrue($localGateway->is_default);
        $this->assertTrue($localGateway->is_protected);
        $this->assertSame('enabled', $localGateway->status);
        $this->assertDatabaseHas('sms_gateways', [
            'owner_scope' => 'system',
            'provider_code' => 'ippanel',
            'status' => 'disabled',
            'is_default' => false,
        ]);
        $this->assertFalse($ippanelGateway->is_default);
        $this->assertSame('disabled', $ippanelGateway->status);
        $this->assertSame(
            'YTIxMzVhYmMtZGRkYS00OWJhLWE2ZTItNjUzYjhkYTg5M2U0OGRiZjZjMzZiNjBkNjgxYmUwZTAwM2IwZDc1MzczMTU=',
            $ippanelGateway->encrypted_credentials['api_key'] ?? null
        );
        $this->assertSame(1, PaymentGateway::query()->where('is_default', true)->count());
        $this->assertTrue($zarinpalGateway->is_default);
        $this->assertSame('enabled', $zarinpalGateway->status);
        $this->assertSame('sandbox', $zarinpalGateway->settings_json['mode'] ?? null);
        $this->assertSame('https://sandbox.zarinpal.com', $zarinpalGateway->settings_json['sandbox_base_url'] ?? null);
        $this->assertSame($preservedMerchantId, $zarinpalGateway->encrypted_credentials['merchant_id'] ?? null);
        $this->assertGreaterThan(0, SystemSetting::query()->count('*'));
        $this->assertSame(7, Plan::query()->count('*'));

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $nojaTenant = Tenant::query()->where('slug', 'noja')->firstOrFail();
        $demoTenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        $silverSandsTenant = Tenant::query()->where('slug', 'silversands')->firstOrFail();
        $demoAdmin = User::query()->where('phone_normalized', '09004666673')->firstOrFail();
        $kamranDemoAdmin = User::query()->where('phone_normalized', '09127072327')->firstOrFail();
        $silverSandsAdmin = User::query()->where('phone_normalized', '09121012193')->firstOrFail();
        $activeSubscription = Subscription::query()->where('tenant_id', $tenant->id)->where('status', 'active')->firstOrFail();
        $nojaSubscription = Subscription::query()->where('tenant_id', $nojaTenant->id)->where('status', 'active')->firstOrFail();
        $demoSubscription = Subscription::query()->where('tenant_id', $demoTenant->id)->where('status', 'active')->firstOrFail();
        $silverSandsSubscription = Subscription::query()->where('tenant_id', $silverSandsTenant->id)->where('status', 'active')->firstOrFail();
        $seededPlans = Plan::query()->with('serviceEntitlements')->get()->keyBy('name');
        $combinedStarter = $seededPlans->get('پلن شروع');
        $hairStarter = $seededPlans->get('پلن شروع کاشت مو');
        $silverSandsPlan = $seededPlans->get('پلن دمو برای تست');
        $eyebrowAddOn = $seededPlans->get('افزودن کاشت ابرو');
        $hairTopUp = $seededPlans->get('بسته افزایش اعتبار کاشت مو');
        $eyebrowTopUp = $seededPlans->get('بسته افزایش اعتبار کاشت ابرو');
        $combinedTopUp = $seededPlans->get('بسته افزایش اعتبار کاشت مو و ابرو');

        $this->assertSame(['ریال'], $seededPlans->pluck('currency')->unique()->values()->all());
        $this->assertSame('پلن آغازین کاشت مو و ابرو', $combinedStarter?->description);
        $this->assertSame(4000000000, $combinedStarter?->price);
        $this->assertEquals(['hair_transplant' => 600, 'eyebrow_transplant' => 600], $combinedStarter?->serviceCredits());
        $this->assertSame('پلن آغازین کاشت مو', $hairStarter?->description);
        $this->assertSame(3500000000, $hairStarter?->price);
        $this->assertSame(['hair_transplant' => 600], $hairStarter?->serviceCredits());
        $this->assertSame('base_subscription', $silverSandsPlan?->plan_type->value);
        $this->assertSame(1, $silverSandsPlan?->price);
        $this->assertSame(30, $silverSandsPlan?->duration_days);
        $this->assertSame(10, $silverSandsPlan?->usage_limit);
        $this->assertFalse((bool) $silverSandsPlan?->is_public);
        $this->assertSame(['hair_transplant' => 10], $silverSandsPlan?->serviceCredits());
        $this->assertSame('service_add_on', $eyebrowAddOn?->plan_type->value);
        $this->assertSame(500000000, $eyebrowAddOn?->price);
        $this->assertSame(['eyebrow_transplant' => 600], $eyebrowAddOn?->serviceCredits());
        $this->assertSame('credit_top_up', $hairTopUp?->plan_type->value);
        $this->assertSame(500000000, $hairTopUp?->price);
        $this->assertSame(['hair_transplant' => 600], $hairTopUp?->serviceCredits());
        $this->assertSame('credit_top_up', $eyebrowTopUp?->plan_type->value);
        $this->assertSame(500000000, $eyebrowTopUp?->price);
        $this->assertSame(['eyebrow_transplant' => 600], $eyebrowTopUp?->serviceCredits());
        $this->assertSame('credit_top_up', $combinedTopUp?->plan_type->value);
        $this->assertSame(1000000000, $combinedTopUp?->price);
        $this->assertEquals(['hair_transplant' => 600, 'eyebrow_transplant' => 600], $combinedTopUp?->serviceCredits());

        $this->assertSame('پلن شروع', $activeSubscription->plan->name);
        $this->assertSame(365, $activeSubscription->plan->duration_days);
        $this->assertSame(1200, $activeSubscription->usage_limit);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count());
        $this->assertSame(1, Invoice::query()->where('tenant_id', $tenant->id)->where('status', 'paid')->where('number', 'BS-1')->count());
        $this->assertSame(1, Payment::query()->where('tenant_id', $tenant->id)->where('status', 'paid')->where('idempotency_key', 'seed-arya-starter-plan-paid')->count());
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $tenant->id,
            'domain' => 'arya.hairvision-ai.test',
            'status' => 'active',
        ]);
        $this->assertDatabaseMissing('tenant_domains', [
            'domain' => 'aria.hairvision-ai.test',
        ]);
        $this->assertSame(2, $activeSubscription->serviceEntitlements()->where('is_active', true)->count());
        $this->assertSame(1200, (int) $activeSubscription->serviceEntitlements()->sum('included_credit'));
        $this->assertSame(600, (int) $activeSubscription->serviceEntitlements()->where('service_key', 'hair_transplant')->value('included_credit'));
        $this->assertSame(600, (int) $activeSubscription->serviceEntitlements()->where('service_key', 'eyebrow_transplant')->value('included_credit'));
        $this->assertSame('پلن شروع کاشت مو', $nojaSubscription->plan->name);
        $this->assertNull($nojaSubscription->user_id);
        $this->assertSame(600, $nojaSubscription->usage_limit);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $nojaTenant->id)->where('status', 'active')->count());
        $this->assertSame(1, Invoice::query()->where('tenant_id', $nojaTenant->id)->where('status', 'paid')->where('number', 'BS-2')->count());
        $this->assertSame(1, Payment::query()->where('tenant_id', $nojaTenant->id)->where('status', 'paid')->where('idempotency_key', 'seed-noja-hair-starter-plan-paid')->count());
        $this->assertSame(1, $nojaSubscription->serviceEntitlements()->where('is_active', true)->count());
        $this->assertSame(600, (int) $nojaSubscription->serviceEntitlements()->sum('included_credit'));
        $this->assertSame(600, (int) $nojaSubscription->serviceEntitlements()->where('service_key', 'hair_transplant')->value('included_credit'));
        $this->assertNull($nojaSubscription->serviceEntitlements()->where('service_key', 'eyebrow_transplant')->value('included_credit'));
        $this->assertSame('کلینیک دمو', $demoTenant->name);
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $demoTenant->id,
            'domain' => 'demo.hairvision-ai.test',
            'status' => 'active',
        ]);
        $this->assertSame('مهسا فرهمند', $demoAdmin->name);
        $this->assertSame('clinic_admin', $demoTenant->users()->whereKey($demoAdmin->id)->firstOrFail()->pivot->role);
        $this->assertSame('کامران فضلی', $kamranDemoAdmin->name);
        $this->assertSame('clinic_admin', $kamranDemoAdmin->role);
        $this->assertSame('clinic', $kamranDemoAdmin->access_scope);
        $this->assertSame('clinic_admin', $demoTenant->users()->whereKey($kamranDemoAdmin->id)->firstOrFail()->pivot->role);
        $this->assertSame('active', $demoTenant->users()->whereKey($kamranDemoAdmin->id)->firstOrFail()->pivot->status);
        $this->assertSame('پلن شروع', $demoSubscription->plan->name);
        $this->assertSame($demoAdmin->id, $demoSubscription->user_id);
        $this->assertSame(1200, $demoSubscription->usage_limit);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $demoTenant->id)->where('status', 'active')->count());
        $this->assertSame(1, Invoice::query()->where('tenant_id', $demoTenant->id)->where('status', 'paid')->where('number', 'BS-3')->count());
        $this->assertSame(1, Payment::query()->where('tenant_id', $demoTenant->id)->where('status', 'paid')->where('idempotency_key', 'seed-demo-starter-plan-paid')->count());
        $this->assertSame(2, $demoSubscription->serviceEntitlements()->where('is_active', true)->count());
        $this->assertSame(600, (int) $demoSubscription->serviceEntitlements()->where('service_key', 'hair_transplant')->value('included_credit'));
        $this->assertSame(600, (int) $demoSubscription->serviceEntitlements()->where('service_key', 'eyebrow_transplant')->value('included_credit'));

        $silverSandsInvoice = Invoice::query()
            ->where('tenant_id', $silverSandsTenant->id)
            ->where('number', 'BS-4')
            ->firstOrFail();
        $silverSandsPayment = Payment::query()
            ->where('tenant_id', $silverSandsTenant->id)
            ->where('idempotency_key', 'seed-silversands-openai-hair-plan-paid')
            ->firstOrFail();
        $silverSandsOpenAiAccess = AiModelAccess::query()
            ->where('model_key', 'openai')
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();

        $this->assertSame($silverSandsPlan?->id, $silverSandsSubscription->plan_id);
        $this->assertSame($silverSandsAdmin->id, $silverSandsSubscription->user_id);
        $this->assertSame(10, $silverSandsSubscription->usage_limit);
        $this->assertSame('paid', $silverSandsInvoice->status);
        $this->assertSame(1, $silverSandsInvoice->amount);
        $this->assertNotNull($silverSandsInvoice->activated_at);
        $this->assertSame([$silverSandsOpenAiAccess->id], $silverSandsInvoice->aiModelAccessIdsSnapshot());
        $this->assertSame('paid', $silverSandsPayment->status);
        $this->assertSame(1, $silverSandsPayment->amount);
        $this->assertSame(1, $silverSandsSubscription->serviceEntitlements()->where('is_active', true)->count());
        $this->assertSame(10, (int) $silverSandsSubscription->serviceEntitlements()->where('service_key', 'hair_transplant')->value('included_credit'));
        $this->assertTrue($silverSandsPlan?->aiModelAccesses()->whereKey($silverSandsOpenAiAccess->id)->exists() ?? false);
        $this->assertTrue($silverSandsTenant->aiModelAccesses()->whereKey($silverSandsOpenAiAccess->id)->wherePivot('is_active', true)->exists());
        $this->assertSame(1, CreditTransaction::query()
            ->where('tenant_id', $silverSandsTenant->id)
            ->where('subscription_id', $silverSandsSubscription->id)
            ->where('type', 'grant')
            ->where('amount', 10)
            ->count());

        $silverSandsCategories = collect($this->forHost('silversands.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code')
            ->values()
            ->all();
        $this->assertSame(['hair_transplant'], $silverSandsCategories);

        $demoGapGptAccesses = AiModelAccess::query()
            ->where('model_key', 'gapgpt')
            ->orderBy('usage_category_key')
            ->get();
        $this->assertSame([
            'eyebrow_transplant' => 'مدل واقعی GapGPT کاشت ابرو',
            'hair_transplant' => 'مدل GapGPT کاشت مو',
        ], $demoGapGptAccesses->pluck('name', 'usage_category_key')->all());
        $demoGapGptAccesses->each(function (AiModelAccess $access) use ($demoTenant): void {
            $this->assertFalse($access->is_active);
            $this->assertNotEmpty($access->credentials['api_key'] ?? null);
            $this->assertFalse($demoTenant->aiModelAccesses()->whereKey($access->id)->exists());
        });

        $demoOpenAiAccesses = AiModelAccess::query()
            ->where('model_key', 'openai')
            ->orderBy('usage_category_key')
            ->get();
        $demoOpenAiAccesses->each(function (AiModelAccess $access) use ($demoTenant): void {
            $this->assertTrue($access->is_active);
            $this->assertTrue($demoTenant->aiModelAccesses()->whereKey($access->id)->wherePivot('is_active', true)->exists());
        });

        $demoCategories = collect($this->forHost('demo.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code')
            ->sort()
            ->values()
            ->all();
        $this->assertSame(['eyebrow_transplant', 'hair_transplant'], $demoCategories);
        $this->assertDatabaseCount('tenant_ai_settings', 0);

        $this->assertSame(0, SmsGateway::query()
            ->where('tenant_id', $demoTenant->id)
            ->where('owner_scope', 'tenant')
            ->count());

        $this->assertDatabaseMissing('system_settings', ['group' => 'catalog']);

        Cache::put('hairvision:system-settings', [
            'system.name' => 'HairVision Enterprise',
            'catalog.states.leads.crm_stages' => ['legacy' => 'قدیمی'],
        ], 300);

        $this->assertFalse(app(SystemSettingService::class)->all()->has('catalog.states.leads.crm_stages'));
        $this->assertDatabaseMissing('system_settings', ['key' => 'catalog.states.leads.crm_stages']);
        $this->assertSame(__('leads.statuses'), StateCatalog::options('leads.statuses'));
        $this->assertSame(__('plans.types'), StateCatalog::options('plans.types'));
    }

    public function test_logging_config_falls_back_when_log_channels_are_empty(): void
    {
        $environment = Env::getRepository();
        $hadLogChannel = $environment->has('LOG_CHANNEL');
        $originalLogChannel = $environment->get('LOG_CHANNEL');
        $hadLogStack = $environment->has('LOG_STACK');
        $originalLogStack = $environment->get('LOG_STACK');

        try {
            $environment->set('LOG_CHANNEL', '');
            $environment->set('LOG_STACK', '');

            $logging = require config_path('logging.php');

            $this->assertSame('stack', $logging['default']);
            $this->assertSame(['single'], $logging['channels']['stack']['channels']);
        } finally {
            if ($hadLogChannel) {
                $environment->set('LOG_CHANNEL', (string) $originalLogChannel);
            } else {
                $environment->clear('LOG_CHANNEL');
            }

            if ($hadLogStack) {
                $environment->set('LOG_STACK', (string) $originalLogStack);
            } else {
                $environment->clear('LOG_STACK');
            }
        }
    }

    public function test_payment_gateway_seeder_removes_legacy_test_gateways_outside_test_environments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $zarinpalGateway = PaymentGateway::query()->where('provider_code', 'zarinpal')->firstOrFail();
        $localGateway = PaymentGateway::query()->where('provider_code', 'local')->firstOrFail();
        $zarinpalGateway->forceFill([
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => [
                'merchant_id' => '11111111-1111-4111-8111-111111111111',
            ],
        ])->save();
        $localGateway->forceFill([
            'status' => 'enabled',
            'is_default' => false,
        ])->save();

        $originalEnvironment = app()->environment();
        app()->instance('env', 'production');

        try {
            app(PaymentGatewaySeeder::class)->run();
        } finally {
            app()->instance('env', $originalEnvironment);
        }

        $zarinpalGateway->refresh();
        $localGateway->refresh();
        $this->assertSame('disabled', $zarinpalGateway->status);
        $this->assertFalse($zarinpalGateway->is_default);
        $this->assertSame([], $zarinpalGateway->encrypted_credentials);
        $this->assertSame('disabled', $localGateway->status);
        $this->assertFalse($localGateway->is_default);
    }

    public function test_system_management_routes_and_menu_are_permission_protected(): void
    {
        $this->seed(DatabaseSeeder::class);

        $viewer = User::query()->where('role', 'viewer')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/general')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('مدیریت سیستم');

        $this->actingAs($superAdmin)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/overview')
            ->assertOk()
            ->assertSee(__('system.dashboard_title'))
            ->assertSee('درگاه‌های پرداخت');

        $this->actingAs($superAdmin)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('data-menu-group="'.__('menu.groups.settings').'"', false)
            ->assertDontSee('data-menu-group="مدیریت سیستم"', false)
            ->assertSee('data-menu-item="admin.clinics.index"', false)
            ->assertSee('data-menu-item="admin.system.landing-templates.index"', false)
            ->assertSee('data-menu-item="admin.system.notifications.index"', false)
            ->assertSee('data-menu-item="admin.system.ai-model-categories.index"', false)
            ->assertSee('data-menu-item="admin.ai-models.index"', false)
            ->assertSee('data-menu-item="admin.system.sms-gateways.index"', false)
            ->assertSee('data-menu-item="admin.system.settings.edit"', false)
            ->assertDontSee('data-menu-item="admin.system.access.index"', false)
            ->assertDontSee('پنل پیامک اختصاصی');
    }

    public function test_clinic_role_permissions_are_predefined_and_do_not_accept_system_permission_overrides(): void
    {
        $this->seed(DatabaseSeeder::class);

        $role = Role::query()->where('code', 'viewer')->firstOrFail();
        $permission = Permission::query()->where('code', 'settings.manage')->firstOrFail();
        $viewer = User::query()->where('role', 'viewer')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->assertSame('clinic', $role->scope);
        $this->assertFalse($viewer->hasPermission('settings.manage'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/roles')
            ->assertOk()
            ->assertSee(__('system.roles.super_admin'))
            ->assertDontSee(__('system.roles.viewer'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/security/roles/'.$role->id, [
                'name' => $role->name,
                'description' => $role->description,
                'status' => 'active',
                'permissions' => [$permission->id],
            ])
            ->assertSessionHasErrors('role');

        $role->permissions()->attach($permission);
        app(PermissionService::class)->forgetRole($role->code);

        $this->assertFalse($viewer->hasPermission('settings.manage'));
        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/general')
            ->assertForbidden();
    }

    public function test_platform_and_clinic_user_tables_are_separate_identity_boundaries(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $otherTenant = $this->createTenant('Clinic Two', 'clinic-two', 'clinic-two.hairvision-ai.test');

        $platformUser = PlatformUser::factory()->create([
            'phone_normalized' => '09125550000',
            'role' => 'super_admin',
        ]);

        $clinicUser = ClinicUser::factory()->create([
            'tenant_id' => $tenant->id,
            'phone_normalized' => '09125550001',
            'role' => 'clinic_admin',
        ]);

        $this->assertDatabaseHas('platform_users', [
            'id' => $platformUser->id,
            'phone_normalized' => '09125550000',
            'role' => 'super_admin',
        ]);
        $this->assertDatabaseHas('clinic_users', [
            'id' => $clinicUser->id,
            'tenant_id' => $tenant->id,
            'phone_normalized' => '09125550001',
            'role' => 'clinic_admin',
        ]);
        $this->assertDatabaseMissing('clinic_users', [
            'phone_normalized' => '09125550000',
        ]);
        $this->assertTrue($clinicUser->canAccessTenant($tenant->id));
        $this->assertFalse($clinicUser->canAccessTenant($otherTenant->id));
    }

    public function test_system_settings_are_validated_updated_and_cached(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/general')
            ->assertOk()
            ->assertDontSee('name="settings[catalog.states.leads.crm_stages]"', false)
            ->assertDontSee('کاتالوگ وضعیت‌ها')
            ->assertDontSee('&quot;new_lead&quot;', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/general', [
                'settings' => [
                    'system.name' => 'HairVision Enterprise',
                    'support.phone' => '09120001111',
                    'notifications.global_enabled' => '1',
                    'billing.invoice_due_days' => '14',
                    'api.pagination.default_per_page' => '25',
                    'api.pagination.max_per_page' => '75',
                ],
            ])
            ->assertRedirect();

        $this->assertSame('HairVision Enterprise', SystemSetting::query()->where('key', 'system.name')->firstOrFail()->value_json['value']);

        $this->assertDatabaseMissing('system_settings', ['group' => 'catalog']);
    }

    public function test_lead_json_pagination_supports_standard_parameters_only(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $this->createLead($tenant, '09120000001', 'Alpha Lead');
        $this->createLead($tenant, '09120000002', 'Beta Lead');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->getJson('/admin/crm/leads?per_page=1&search=Alpha&sort=full_name&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->getJson('/admin/crm/leads?per_page=1&page=2&search=Lead&sort=full_name&direction=desc')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        $this->assertArrayNotHasKey('draw', $response->json());
        $this->assertArrayNotHasKey('recordsTotal', $response->json());
        $this->assertArrayNotHasKey('recordsFiltered', $response->json());
    }

    public function test_super_admin_can_fully_manage_users_and_search_user_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users')
            ->assertOk()
            ->assertSee(__('system.roles.super_admin'))
            ->assertSee('data-menu-item="admin.users.index"', false)
            ->assertSee('create-user-modal-toggle', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('data-table-action-item', false)
            ->assertSee('admin-confirm-modal-toggle', false)
            ->assertSee('data-admin-confirm-modal', false)
            ->assertSee('data-admin-modal-backdrop="admin-confirm-modal"', false)
            ->assertSee('data-admin-modal-close="admin-confirm-modal"', false)
            ->assertDontSee('<label for="admin-confirm-modal-toggle"', false)
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users?search=09124248164')
            ->assertOk()
            ->assertSee('09124248164');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users/create')
            ->assertOk()
            ->assertSee(__('system.access_scope'))
            ->assertSee(__('system.last_name'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/security/users', [
                'name' => 'کاربر تست مدیریت',
                'email' => 'managed-user@example.com',
                'phone_normalized' => '09127770000',
                'access_scope' => 'clinic',
                'role' => 'viewer',
                'status' => 'active',
                'tenant_id' => $tenant->id,
            ])
            ->assertRedirect();

        $managedUser = User::query()->where('email', 'managed-user@example.com')->firstOrFail();
        $this->assertSame('clinic', $managedUser->access_scope);
        $this->assertSame(1, $managedUser->tenants()->count());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users/'.$managedUser->id)
            ->assertOk()
            ->assertSee('کاربر تست مدیریت')
            ->assertSee(__('system.roles.viewer'))
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users/'.$managedUser->id.'/edit')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/security/users/'.$managedUser->id, [
                'name' => 'کاربر تست ویرایش شده',
                'email' => 'managed-user@example.com',
                'phone_normalized' => '09127770001',
                'access_scope' => 'clinic',
                'role' => 'editor',
                'status' => 'inactive',
                'tenant_id' => $tenant->id,
            ])
            ->assertRedirect(route('admin.users.show', $managedUser));

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'کاربر تست ویرایش شده',
            'access_scope' => 'clinic',
            'role' => 'editor',
            'status' => 'inactive',
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/security/users/'.$managedUser->id)
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);
    }

    public function test_duplicate_user_phone_validation_message_is_persian(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/security/users', [
                'name' => 'کاربر با شماره تکراری',
                'email' => 'duplicate-phone@example.com',
                'phone_normalized' => $superAdmin->phone_normalized,
                'access_scope' => 'clinic',
                'role' => 'viewer',
                'status' => 'active',
                'tenant_id' => $tenant->id,
            ])
            ->assertSessionHasErrors([
                'phone_normalized' => 'این شماره موبایل قبلاً ثبت شده است.',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'duplicate-phone@example.com',
        ]);
    }

    public function test_users_without_permissions_cannot_access_user_management_actions(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $viewer = $this->createUserForTenant($tenant, 'viewer', '09121111111');
        $target = $this->createUserForTenant($tenant, 'editor', '09122222222');
        $this->activateSubscription($viewer);

        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/security/users')->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/security/users/create')->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/security/users/'.$target->id)->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/security/users/'.$target->id.'/edit')->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->put('/admin/security/users/'.$target->id, [])->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->delete('/admin/security/users/'.$target->id)->assertForbidden();
    }

    public function test_user_edit_assigns_exactly_one_access_scope_and_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $systemRole = Role::query()->create([
            'code' => 'support_operator',
            'scope' => 'platform',
            'name' => 'اپراتور پشتیبانی',
            'description' => 'Support access',
            'is_system' => false,
            'status' => 'active',
        ]);
        $systemRole->permissions()->attach(Permission::query()->where('code', 'users.list')->firstOrFail());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/security/users', [
                'name' => 'کاربر سیستم تست',
                'email' => 'system-scope-user@example.com',
                'phone_normalized' => '09127770010',
                'access_scope' => 'system',
                'role' => 'support_operator',
                'status' => 'active',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'system-scope-user@example.com')->firstOrFail();
        $this->assertSame('system', $user->access_scope);
        $this->assertSame('support_operator', $user->role);
        $this->assertSame(0, $user->tenants()->count());
        $this->assertTrue($user->hasPermission('users.list'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/security/users/'.$user->id, [
                'name' => 'کاربر سیستم تست',
                'email' => 'system-scope-user@example.com',
                'phone_normalized' => '09127770010',
                'access_scope' => 'clinic',
                'role' => 'viewer',
                'status' => 'active',
                'tenant_id' => $tenant->id,
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $user->refresh();
        $this->assertSame('clinic', $user->access_scope);
        $this->assertSame('viewer', $user->role);
        $this->assertSame([$tenant->id], $user->tenants()->pluck('tenants.id')->all());
    }

    public function test_clinic_user_form_hides_access_scope_and_limits_user_statuses(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $clinicManager = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($clinicManager);

        $this->actingAs($clinicManager)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users/create')
            ->assertOk()
            ->assertDontSee(__('system.access_scope'))
            ->assertSee(__('system.role'))
            ->assertSee(__('system.statuses.active'))
            ->assertSee(__('system.statuses.inactive'))
            ->assertDontSee(__('system.statuses.blocked'));

        $this->actingAs($clinicManager)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/security/users', [
                'first_name' => 'کاربر',
                'last_name' => 'کلینیک',
                'email' => 'clinic-status-user@example.com',
                'phone_normalized' => '09124448888',
                'role' => 'viewer',
                'status' => 'blocked',
                'tenant_id' => $tenant->id,
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_gateway_selection_separates_system_and_clinic_sms(): void
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $systemGateway = SmsGateway::query()->system()->where('is_default', true)->firstOrFail();
        $clinicGateway = SmsGateway::create([
            'tenant_id' => $tenant->id,
            'name' => 'Clinic KaveNegar',
            'provider_code' => 'kavenegar',
            'owner_scope' => 'tenant',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'secret'],
        ]);

        $service = app(SmsGatewayService::class);
        $manager = app(SmsGatewayManagementService::class);

        $this->assertTrue($service->systemDefault()->is($systemGateway));
        $this->assertTrue($manager->clinicSettings($tenant)->is($clinicGateway));
        $this->assertTrue($service->sendSystemMessage('09217359491', 'system')->is($systemGateway));
        $this->assertArrayNotHasKey('encrypted_credentials', $clinicGateway->toArray());
    }

    public function test_ippanel_gateway_sends_otp_patterns_and_regular_messages(): void
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        $gateway = SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);

        $service = app(SmsGatewayService::class);

        $this->assertTrue($service->sendSystemOtp('09124248164', '385107')->is($gateway));
        $this->assertTrue($service->sendSystemMessage('09124248164', 'متن پیام')->is($gateway));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://edge.ippanel.com/v1/api/send'
                && $request->header('Authorization') === ['system-token']
                && $request['sending_type'] === 'pattern'
                && $request['from_number'] === '+983000505'
                && $request['code'] === 's5jh64gwzlk0jf3'
                && $request['recipients'] === ['989124248164']
                && $request['params']['verification-code'] === '385107';
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://edge.ippanel.com/v1/api/send'
                && $request->header('Authorization') === ['system-token']
                && $request['sending_type'] === 'webservice'
                && $request['from_number'] === '+983000505'
                && $request['params']['message'] === 'متن پیام'
                && $request['params']['recipients'] === ['989124248164'];
        });
    }

    public function test_ippanel_gateway_normalizes_authorization_recipient_and_pattern_variable(): void
    {
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'Bearer system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
                'otp_pattern_variable' => 'code',
            ],
        ]);

        app(SmsGatewayService::class)->sendSystemOtp('+989124248164', '385107');

        Http::assertSent(function ($request): bool {
            return $request->header('Authorization') === ['system-token']
                && $request['recipients'] === ['989124248164']
                && $request['params']['code'] === '385107';
        });
    }

    public function test_ippanel_gateway_retries_until_the_third_attempt_succeeds(): void
    {
        config(['hairvision.sms.ippanel.retry_delay_milliseconds' => 0]);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return $attempts < 3
                ? Http::response(['meta' => ['status' => false]], 503)
                : Http::response(['meta' => ['status' => true]]);
        });

        $gateway = SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);

        $this->assertTrue(app(SmsGatewayService::class)->sendSystemOtp('09124248164', '385107')->is($gateway));
        $this->assertSame(3, $attempts);
    }

    public function test_ippanel_gateway_timeout_retries_three_times_and_returns_a_generic_error(): void
    {
        Log::spy();
        config(['hairvision.sms.ippanel.retry_delay_milliseconds' => 0]);

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('Operation timed out for 09124248164.');
        });

        SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);

        try {
            app(SmsGatewayService::class)->sendSystemOtp('09124248164', '385107');
            $this->fail('Expected the exhausted SMS delivery to throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('sms.delivery_failed_generic'), $exception->errors()['request'][0] ?? null);
        }

        $this->assertSame(3, $attempts);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'IPPanel SMS gateway delivery failed after retries.'
                && ($context['attempts'] ?? null) === 3
                && ($context['failure_type'] ?? null) === 'request_exception'
                && ($context['exception_class'] ?? null) === ConnectionException::class
                && ($context['operation'] ?? null) === 'sms.adapter.ippanel.send_otp'
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Operation timed out')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '09124248164')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '385107')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'system-token'))
            ->once();
    }

    public function test_ippanel_gateway_failed_response_logs_safe_context(): void
    {
        Log::spy();
        config(['hairvision.sms.ippanel.retry_delay_milliseconds' => 0]);

        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::response([
                'meta' => [
                    'status' => false,
                    'message' => 'Invalid recipient 989124248164 for code 385107',
                ],
                'data' => [
                    'recipient' => '989124248164',
                    'verification-code' => '385107',
                ],
            ]);
        });

        SmsGateway::create([
            'name' => 'IPPanel System',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'system-token'],
            'settings_json' => [
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ],
        ]);

        try {
            app(SmsGatewayService::class)->sendSystemOtp('09124248164', '385107');
            $this->fail('Expected IPPanel failed response to throw a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(__('sms.delivery_failed_generic'), $exception->errors()['request'][0] ?? null);
        }

        $this->assertSame(3, $attempts);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'IPPanel SMS gateway delivery failed after retries.'
                && ($context['http_status'] ?? null) === 200
                && ($context['ippanel_status'] ?? null) === false
                && ($context['sending_type'] ?? null) === 'pattern'
                && ($context['operation'] ?? null) === 'sms.adapter.ippanel.send_otp'
                && ($context['attempts'] ?? null) === 3
                && ($context['failure_type'] ?? null) === 'rejected_response'
                && ! array_key_exists('payload', $context)
                && ! array_key_exists('ippanel_message', $context)
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '385107')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), '989124248164')
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'system-token'))
            ->once();
    }

    public function test_sms_gateway_crud_masks_credentials_and_enforces_authorization(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $viewer = User::query()->where('role', 'viewer')->firstOrFail();
        $this->activateSubscription($viewer);
        $longToken = 'super-secret-api-key-'.str_repeat('x', 80);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/sms-gateways', [
                'name' => 'درگاه تست IPPanel',
                'provider_code' => 'ippanel',
                'owner_scope' => 'system',
                'status' => 'enabled',
                'is_default' => '1',
                'api_key' => $longToken,
                'from_number' => '+983000505',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ])
            ->assertRedirect();

        $gateway = SmsGateway::query()->where('name', 'درگاه تست IPPanel')->firstOrFail();
        $this->assertSame('supe...xxxx', $gateway->maskedCredential('api_key'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/sms-gateways?search=IPPanel')
            ->assertOk()
            ->assertSee('درگاه تست IPPanel')
            ->assertSee('درگاه پیامک IPPanel')
            ->assertSee('create-system-sms-gateway-modal-toggle', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee($longToken);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/sms-gateways/'.$gateway->id)
            ->assertOk()
            ->assertSee($gateway->maskedCredential('api_key'))
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee($longToken);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/sms-gateways/'.$gateway->id, [
                'name' => 'درگاه تست IPPanel ویرایش',
                'provider_code' => 'ippanel',
                'owner_scope' => 'system',
                'status' => 'disabled',
                'from_number' => '+983000506',
                'otp_pattern_code' => 's5jh64gwzlk0jf3',
            ])
            ->assertRedirect();

        $gateway->refresh();
        $this->assertSame('disabled', $gateway->status);
        $this->assertSame($longToken, $gateway->encrypted_credentials['api_key']);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $otherTenant = Tenant::query()->where('slug', 'noja')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/sms-gateways', [
                'name' => 'درگاه کلینیک نوژا',
                'provider_code' => 'ippanel',
                'owner_scope' => 'tenant',
                'tenant_id' => $otherTenant->id,
                'status' => 'enabled',
                'is_default' => '1',
                'from_number' => '10002000',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sms_gateways', [
            'tenant_id' => $otherTenant->id,
            'owner_scope' => 'tenant',
            'provider_code' => 'ippanel',
            'status' => 'enabled',
            'is_default' => true,
        ]);

        SmsGateway::create([
            'tenant_id' => $tenant->id,
            'name' => 'درگاه کلینیک آریا',
            'provider_code' => 'ippanel',
            'owner_scope' => 'tenant',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'clinic-secret-token'],
            'settings_json' => ['from_number' => '+983000505'],
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/sms-gateways?owner_scope=tenant')
            ->assertOk()
            ->assertSee('درگاه کلینیک آریا')
            ->assertSee($tenant->name)
            ->assertDontSee('clinic-secret-token');

        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/sms-gateways')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/settings/sms-gateways/'.$gateway->id)
            ->assertRedirect(route('admin.system.sms-gateways.index'));

        $this->assertDatabaseMissing('sms_gateways', ['id' => $gateway->id]);
    }

    public function test_clinic_sms_gateway_settings_are_single_panel_and_scoped_to_tenant(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $owner = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $other = $this->createUserForTenant($tenant, 'clinic_admin', '09122222222');
        $this->activateSubscription($owner);
        $this->activateSubscription($other);

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('پنل پیامک اختصاصی');

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/my-sms-gateways')
            ->assertNotFound();

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$tenant->id.'/sms-gateway', [
                '_form' => 'clinic_sms_gateway',
                'provider_code' => 'ippanel',
                'api_key' => 'private-secret-key',
                'from_number' => '+983000505',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.clinics.show', $tenant));

        $gateway = SmsGateway::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame('درگاه پیامک کلینیک', $gateway->name);
        $this->assertSame('ippanel', $gateway->provider_code);
        $this->assertSame('tenant', $gateway->owner_scope);
        $this->assertSame('enabled', $gateway->status);
        $this->assertTrue($gateway->is_default);
        $this->assertSame('+983000505', $gateway->settings_json['from_number']);

        $this->assertSame('priv...-key', $gateway->maskedCredential('api_key'));

        $this->actingAs($other)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$tenant->id.'/sms-gateway', [
                '_form' => 'clinic_sms_gateway',
                'provider_code' => 'ippanel',
                'from_number' => '+983000506',
                'is_active' => '0',
            ])
            ->assertRedirect(route('admin.clinics.show', $tenant));

        $gateway = SmsGateway::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('disabled', $gateway->status);
        $this->assertSame('private-secret-key', $gateway->encrypted_credentials['api_key']);
        $this->assertSame('+983000506', $gateway->settings_json['from_number']);
        $this->assertSame(1, SmsGateway::query()->where('tenant_id', $tenant->id)->where('owner_scope', 'tenant')->count());
    }

    public function test_payment_default_gateway_invoice_payment_and_plan_upgrade_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $tenant = $user->tenants()->firstOrFail();
        $tenant->aiModelAccesses()->detach();
        $starter = Plan::query()->where('name', 'پلن شروع')->firstOrFail();
        $selectedPlanModelIds = $starter->aiModelAccesses()
            ->pluck('ai_model_accesses.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $upgrade = Plan::create([
            'name' => 'پلن ارتقا',
            'description' => 'Upgrade',
            'price' => 4000000000,
            'currency' => 'IRR',
            'duration_days' => 60,
            'usage_limit' => 1800,
            'status' => 'active',
        ]);

        $gatewayService = app(PaymentGatewayService::class);
        $this->assertSame('zarinpal', $gatewayService->default()?->provider_code);
        $this->fakeZarinpalPayments('A000000000000000000000000000000000001', 'A000000000000000000000000000000000002');

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/payment-gateways?search=zarinpal')
            ->assertOk()
            ->assertSee('zarinpal')
            ->assertSee(__('messages.zarinpal_test_payment_gateway'))
            ->assertSee('create-payment-gateway-modal-toggle', false)
            ->assertSee('data-admin-table-container', false)
            ->assertSee('mt-5 hidden overflow-x-auto overflow-y-hidden rounded-lg border border-zinc-100 lg:block', false)
            ->assertSee('admin-mobile-list', false)
            ->assertDontSee('mt-5 overflow-x-auto rounded-lg border border-zinc-100', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('open:z-80', false)
            ->assertSee('z-90', false)
            ->assertSee('admin-confirm-modal-toggle', false)
            ->assertSee('data-admin-modal-backdrop="admin-confirm-modal"', false);

        $billing = app(BillingService::class);
        $invoice = $billing->invoiceForPlan($user, $starter);
        $payment = $billing->initiatePayment($invoice);
        $this->assertSame('zarinpal', $payment->gateway->provider_code);
        $this->assertSame('A000000000000000000000000000000000001', $payment->authority);
        $this->assertSame('https://sandbox.zarinpal.com/pg/StartPay/A000000000000000000000000000000000001', data_get($payment->gateway_response_json, 'redirect_url'));
        $billing->verifyPayment($payment);
        $billing->verifyPayment($payment->refresh());

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->where('status', 'active')->count());
        $this->assertEqualsCanonicalizing(
            $starter->serviceKeys(),
            $tenant->aiModelAccesses()
                ->wherePivot('is_active', true)
                ->pluck('usage_category_key')
                ->all()
        );
        $this->assertEqualsCanonicalizing(
            $selectedPlanModelIds,
            $tenant->aiModelAccesses()
                ->wherePivot('is_active', true)
                ->pluck('ai_model_accesses.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all()
        );
        $this->assertSame(2, DB::table('clinic_ai_model_access')
            ->where('clinic_id', $tenant->id)
            ->where('is_active', true)
            ->count());
        $this->assertSame(2, DB::table('clinic_ai_model_access')
            ->where('clinic_id', $tenant->id)
            ->where('assigned_by', $user->id)
            ->count());

        $availableApplications = app(PublicApplicationAvailabilityService::class)->categoriesForTenant($tenant);
        $this->assertEqualsCanonicalizing($starter->serviceKeys(), $availableApplications->pluck('code')->all());
        $availableApplications->each(function (AiModelCategory $category): void {
            $this->assertSame(
                $category->outputs()->active()->count(),
                $category->outputs->count()
            );
        });

        $active = Subscription::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $active->forceFill(['usage_used' => 80])->save();

        $upgradeInvoice = $billing->invoiceForPlan($user, $upgrade);
        $upgradePayment = $billing->initiatePayment($upgradeInvoice);
        $this->assertSame('A000000000000000000000000000000000002', $upgradePayment->authority);
        $billing->verifyPayment($upgradePayment);

        $this->assertSame('replaced', $active->refresh()->status);
        $newActive = Subscription::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $this->assertSame($upgrade->id, $newActive->plan_id);
        $this->assertSame(0, $newActive->usage_used);
        $this->assertSame(1800, $newActive->usage_limit);
    }

    public function test_expired_zarinpal_authority_is_replaced_before_gateway_redirect(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        $firstAuthority = 'A000000000000000000000000000000000011';
        $secondAuthority = 'A000000000000000000000000000000000012';
        $this->fakeZarinpalPayments($firstAuthority, $secondAuthority);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $billing = app(BillingService::class);
        $invoice = $billing->invoiceForPlan($user, $plan);

        $firstPayment = $billing->initiatePayment($invoice);
        $reusedPayment = $billing->initiatePayment($invoice->refresh());

        $this->assertSame($firstPayment->id, $reusedPayment->id);
        Http::assertSentCount(1);

        try {
            $this->travel(6)->minutes();
            $secondPayment = $billing->initiatePayment($invoice->refresh());
        } finally {
            $this->travelBack();
        }

        $this->assertNotSame($firstPayment->id, $secondPayment->id);
        $this->assertSame($secondAuthority, $secondPayment->authority);
        $this->assertSame('pending', $secondPayment->status);
        $this->assertSame('failed', $firstPayment->refresh()->status);
        $this->assertSame('expired', data_get($firstPayment->gateway_response_json, 'status'));
        $this->assertSame(
            'authority_reuse_window_elapsed',
            data_get($firstPayment->gateway_response_json, 'retry_reason')
        );
        $this->assertNull(data_get($firstPayment->gateway_response_json, 'redirect_url'));
        $this->assertSame('pending', $invoice->refresh()->status);
        Http::assertSentCount(2);
    }

    public function test_payment_callback_uses_an_authorized_refresh_safe_result_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        $failedAuthority = 'A000000000000000000000000000000000021';
        $successfulAuthority = 'A000000000000000000000000000000000022';
        $this->fakeZarinpalPayments($failedAuthority, $successfulAuthority);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $tenant = $user->tenants()->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $invoice = app(BillingService::class)->invoiceForPlan($user, $plan, $tenant);
        $payment = app(BillingService::class)->initiatePayment($invoice);
        $resultUrl = route('admin.billing.result', $payment);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get($resultUrl)
            ->assertOk()
            ->assertSee('data-payment-result="pending"', false)
            ->assertSee('data-payment-result-header="compact"', false)
            ->assertSee('data-payment-details="receipt"', false)
            ->assertSee('data-payment-receipt-summary', false)
            ->assertSee('data-payment-amount', false)
            ->assertSee(__('payments.result.pending.title'))
            ->assertSee('•••• '.substr($failedAuthority, -8), false)
            ->assertDontSee($failedAuthority, false)
            ->assertDontSee('data-payment-retry-form', false);

        $otherUser = $this->createUserForTenant($tenant, 'clinic_admin', '09121114444');
        $this->actingAs($otherUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get($resultUrl)
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/payments/'.$payment->id.'/verify?Authority='.$failedAuthority.'&Status=NOK')
            ->assertStatus(303)
            ->assertRedirect($resultUrl);

        $this->assertSame('failed', $payment->refresh()->status);
        $this->assertSame('failed', $invoice->refresh()->status);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get($resultUrl)
            ->assertOk()
            ->assertSee('data-payment-result="failed"', false)
            ->assertSee('border-rose-200 bg-rose-50', false)
            ->assertSee(__('payments.result.failed.title'))
            ->assertSee(__('payments.result.deducted_hint'))
            ->assertSee('data-payment-retry-form', false)
            ->assertDontSee('data-payment-reference', false);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertStatus(303)
            ->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$successfulAuthority);

        $successfulPayment = $invoice->payments()->latest('id')->firstOrFail();
        $successfulResultUrl = route('admin.billing.result', $successfulPayment);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/payments/'.$successfulPayment->id.'/verify?Authority='.$successfulAuthority.'&Status=OK')
            ->assertStatus(303)
            ->assertRedirect($successfulResultUrl);

        $this->assertSame('paid', $successfulPayment->refresh()->status);
        $this->assertSame('paid', $invoice->refresh()->status);

        foreach (range(1, 2) as $_) {
            $this->actingAs($user)
                ->withSession(['admin_active_tenant_id' => $tenant->id])
                ->forHost('hairvision-ai.test')
                ->get($successfulResultUrl)
                ->assertOk()
                ->assertSee('data-payment-result="success"', false)
                ->assertSee('border-emerald-200 bg-emerald-50', false)
                ->assertSee(__('payments.result.success.title'))
                ->assertSee('REF-000001')
                ->assertSee('data-payment-reference', false)
                ->assertDontSee($successfulAuthority, false)
                ->assertDontSee('data-payment-retry-form', false);
        }

        Http::assertSentCount(3);
    }

    public function test_system_admin_can_configure_zarinpal_without_exposing_or_overwriting_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $gateway = PaymentGateway::query()->where('provider_code', 'zarinpal')->firstOrFail();
        $seededMerchantId = data_get($gateway->encrypted_credentials, 'merchant_id');
        $requestUrl = data_get($gateway->settings_json, 'request_url');
        $merchantId = '44444444-4444-4444-8444-444444444444';
        $legacyCallbackUrl = 'https://hairvision.ir/admin/dashboard';
        $gateway->forceFill([
            'settings_json' => array_replace($gateway->settings_json ?? [], [
                'callback_url' => $legacyCallbackUrl,
            ]),
        ])->save();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/payment-gateways?search=zarinpal')
            ->assertOk()
            ->assertSee('data-admin-modal-trigger="edit-payment-gateway-'.$gateway->id.'"', false)
            ->assertSee('name="merchant_id"', false)
            ->assertSee('data-automatic-zarinpal-callback', false)
            ->assertSee('https://hairvision-ai.test/admin/finance/billing/payments/{payment}/verify', false)
            ->assertDontSee($legacyCallbackUrl, false)
            ->assertDontSee((string) $seededMerchantId, false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/finance/payment-gateways/'.$gateway->id, [
                '_form' => 'edit_payment_gateway_'.$gateway->id,
                'name' => $gateway->name,
                'provider_code' => 'zarinpal',
                'merchant_id' => $merchantId,
                'callback_url' => $legacyCallbackUrl,
                'status' => 'enabled',
                'is_default' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $gateway->refresh();
        $this->assertSame($merchantId, data_get($gateway->encrypted_credentials, 'merchant_id'));
        $this->assertNull(data_get($gateway->settings_json, 'callback_url'));
        $this->assertSame($requestUrl, data_get($gateway->settings_json, 'request_url'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/finance/payment-gateways/'.$gateway->id, [
                '_form' => 'edit_payment_gateway_'.$gateway->id,
                'name' => 'زرین‌پال ویرایش‌شده',
                'provider_code' => 'zarinpal',
                'merchant_id' => '',
                'status' => 'enabled',
                'is_default' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($merchantId, data_get($gateway->refresh()->encrypted_credentials, 'merchant_id'));
        $this->assertNull(data_get($gateway->settings_json, 'callback_url'));
        $this->assertSame($requestUrl, data_get($gateway->settings_json, 'request_url'));
    }

    public function test_zarinpal_callback_returns_to_the_host_that_started_payment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        $authority = 'A000000000000000000000000000000000031';
        $this->fakeZarinpalPayments($authority);

        $gateway = PaymentGateway::query()->where('provider_code', 'zarinpal')->firstOrFail();
        $gateway->forceFill([
            'settings_json' => array_replace($gateway->settings_json ?? [], [
                'callback_url' => 'https://hairvision.ir/admin/dashboard',
            ]),
        ])->save();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $tenant = $user->tenants()->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $invoice = app(BillingService::class)->invoiceForPlan($user, $plan, $tenant);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('ai.arsamnet.com')
            ->post('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertStatus(303)
            ->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);

        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $expectedCallback = 'https://ai.arsamnet.com/admin/finance/billing/payments/'.$payment->id.'/verify';

        Http::assertSent(fn ($request): bool => $request->url() === 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            && $request['callback_url'] === $expectedCallback
            && $request['callback_url'] !== 'https://hairvision.ir/admin/dashboard');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('ai.arsamnet.com')
            ->get('/admin/finance/billing/payments/'.$payment->id.'/verify?Authority='.$authority.'&Status=NOK')
            ->assertStatus(303)
            ->assertRedirect('https://ai.arsamnet.com/admin/finance/billing/payments/'.$payment->id.'/result');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('ai.arsamnet.com')
            ->get('/admin/finance/billing/payments/'.$payment->id.'/result')
            ->assertOk()
            ->assertSee('data-payment-result="failed"', false)
            ->assertSee(__('payments.result.failed.title'));
    }

    public function test_seeded_zarinpal_placeholder_is_rejected_before_an_external_request(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        Http::fake();

        PaymentGateway::query()
            ->where('provider_code', 'zarinpal')
            ->firstOrFail()
            ->forceFill([
                'status' => 'enabled',
                'is_default' => true,
                'encrypted_credentials' => [
                    'merchant_id' => '11111111-1111-4111-8111-111111111111',
                ],
            ])
            ->save();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $invoice = app(BillingService::class)->invoiceForPlan($user, $plan);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertRedirect(route('admin.billing.index'))
            ->assertSessionHasErrors([
                'payment' => __('payments.gateway_not_configured'),
            ]);

        Http::assertNothingSent();
    }

    public function test_zarinpal_validation_response_logs_only_safe_diagnostics(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        Log::spy();

        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => [],
                'errors' => [
                    'code' => -9,
                    'message' => 'Merchant secret response',
                    'validations' => [
                        'merchant_id' => ['The merchant is invalid.'],
                    ],
                ],
            ], 422),
        ]);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $invoice = app(BillingService::class)->invoiceForPlan($user, $plan);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertRedirect(route('admin.billing.index'))
            ->assertSessionHasErrors([
                'payment' => __('payments.payment_failed_generic'),
            ]);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Zarinpal payment gateway returned an invalid response.'
                && ($context['status'] ?? null) === 422
                && ($context['provider_code'] ?? null) === -9
                && ($context['provider_validation_fields'] ?? null) === ['merchant_id']
                && ! array_key_exists('payload', $context)
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'Merchant secret response'))
            ->once();
    }

    public function test_invoices_are_user_scoped_unless_user_can_manage_billing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        $plan = Plan::query()->where('name', 'پلن شروع')->firstOrFail();
        $userA = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $userB = User::query()->where('role', 'editor')->firstOrFail();
        $billing = app(BillingService::class);
        $invoiceA = $billing->invoiceForPlan($userA, $plan);
        $invoiceB = $billing->invoiceForPlan($userB, $plan);

        $this->actingAs($userA)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing')
            ->assertOk()
            ->assertSee($invoiceA->number)
            ->assertDontSee($invoiceB->number);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing')
            ->assertOk()
            ->assertSee($invoiceA->number)
            ->assertSee($invoiceB->number);
    }

    public function test_billing_invoices_show_direct_pay_button_and_details_modal(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $hairOnlyPlan = Plan::query()->where('name', 'پلن شروع کاشت مو')->where('status', 'active')->firstOrFail();
        $gateway = PaymentGateway::query()->where('provider_code', 'zarinpal')->firstOrFail();
        $invoice = app(BillingService::class)->invoiceForPlan($user, $plan);
        $authority = 'A000000000000000000000000000000999';

        Payment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'user_id' => $invoice->user_id,
            'invoice_id' => $invoice->id,
            'payment_gateway_id' => $gateway->id,
            'status' => 'pending',
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'authority' => $authority,
            'idempotency_key' => 'billing-ui-details-test',
        ]);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing')
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('data-invoice-direct-pay="'.$invoice->id.'"', false)
            ->assertSee('invoice-details-'.$invoice->id.'-toggle', false)
            ->assertSee('data-table-row-view-action', false)
            ->assertSee('data-admin-modal-trigger="invoice-details-'.$invoice->id.'"', false)
            ->assertSee(__('payments.view'))
            ->assertSee(__('payments.invoice_information'))
            ->assertSee(__('payments.payment_information'))
            ->assertSee('•••• '.substr($authority, -8), false)
            ->assertDontSee($authority, false);
    }

    public function test_active_subscription_box_is_visible_only_to_clinic_scoped_users(): void
    {
        $this->seed(DatabaseSeeder::class);

        $systemUser = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();

        foreach (['hairvision-ai.test', 'arya.hairvision-ai.test'] as $host) {
            $this->actingAs($systemUser)
                ->forHost($host)
                ->get('/admin/finance/billing')
                ->assertOk()
                ->assertDontSee('data-active-subscription-card', false)
                ->assertDontSee(__('payments.active_subscription'));

            $this->actingAs($clinicUser)
                ->forHost($host)
                ->get('/admin/finance/billing')
                ->assertOk()
                ->assertSee('data-active-subscription-card', false)
                ->assertSee(__('payments.active_subscription'));
        }
    }

    public function test_subscriptions_expire_by_time_not_service_usage(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->firstOrFail();
        $billing = app(BillingService::class);
        $invoice = $billing->invoiceForPlan($user, $plan);
        $subscription = $billing->activatePlan($user, $plan, $invoice);

        $subscription->forceFill(['usage_limit' => 2, 'usage_used' => 1])->save();
        $billing->consumeUsage($user);

        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertSame(2, $subscription->usage_used);

        $timeExpired = $billing->activatePlan($user, $plan, $invoice);
        $timeExpired->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->assertSame(1, $billing->expireEndedSubscriptions());
        $this->assertSame('expired', $timeExpired->refresh()->status);
    }

    public function test_sidebar_icons_and_locked_menu_state_when_user_has_no_active_plan(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan('cancelled');

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-app-shell h-screen overflow-hidden', false)
            ->assertSee('admin-main-panel flex h-screen min-h-0 min-w-0 flex-col', false)
            ->assertSee('admin-content min-h-0 flex-1 overflow-y-auto', false)
            ->assertSee('data-admin-breadcrumbs', false)
            ->assertSee('class="admin-breadcrumbs"', false)
            ->assertSee('admin-breadcrumbs-current', false)
            ->assertSee('aria-label="'.__('dashboard.breadcrumbs.label').'"', false)
            ->assertDontSee(__('dashboard.breadcrumbs.scope').':')
            ->assertSee('aria-current="page"', false)
            ->assertSee('data-admin-menu-scroll', false)
            ->assertSee('data-admin-mobile-menu-open', false)
            ->assertSee('data-admin-mobile-drawer', false)
            ->assertSee('data-admin-mobile-menu-close', false)
            ->assertSee('admin-mobile-brand-center', false)
            ->assertSee('admin-user-menu-avatar-trigger', false)
            ->assertSee('admin-user-menu-trigger-avatar', false)
            ->assertSee('admin-user-avatar-image', false)
            ->assertSee(__('dashboard.mobile_menu.open'))
            ->assertSee('data-admin-user-menu', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-admin-theme-script', false)
            ->assertSee('admin-logo-light', false)
            ->assertSee('admin-logo-dark', false)
            ->assertSee('/theme/admin-logo-light.svg', false)
            ->assertSee('/theme/admin-logo-dark.svg', false)
            ->assertSee('/theme/admin-user-avatar-neutral.svg', false)
            ->assertSee($user->name)
            ->assertSee(__('system.roles.'.$user->role))
            ->assertDontSee($user->phone_normalized)
            ->assertSee(__('dashboard.theme.menu_item'))
            ->assertSee('data-menu-icon="menu-dots-vertical"', false)
            ->assertSee('data-menu-icon="moon"', false)
            ->assertSee('data-menu-icon="sun"', false)
            ->assertDontSee('admin-svg-icon', false)
            ->assertSee('/admin/logout', false)
            ->assertSee('data-menu-icon="layout-dashboard"', false)
            ->assertSee('data-menu-item="admin.dashboard"', false)
            ->assertDontSee('data-menu-item="admin.system.dashboard"', false)
            ->assertDontSee('data-menu-group="'.__('menu.groups.dashboard').'"', false)
            ->assertDontSee('data-menu-group="'.__('menu.groups.crm').'"', false)
            ->assertSee('data-menu-group="'.__('menu.groups.plans_payments').'"', false)
            ->assertSee('data-menu-icon="finance"', false)
            ->assertSee('data-menu-icon="package"', false)
            ->assertSee('data-menu-icon="report-user"', false)
            ->assertSee('data-menu-icon="report-credit"', false)
            ->assertSee('data-menu-icon="lock"', false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('لیدها')
            ->assertSee(__('dashboard.plan.view_plans'));

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.dashboard'))
            ->assertSee('admin-menu-caret', false)
            ->assertDontSee('data-menu-group="'.__('menu.groups.dashboard').'"', false)
            ->assertDontSee('data-menu-item="admin.system.dashboard"', false)
            ->assertSee('data-menu-group="'.__('menu.groups.users_access').'"', false)
            ->assertSee('data-menu-group="'.__('menu.groups.settings').'"', false)
            ->assertDontSee('data-menu-group="مدیریت سیستم"', false)
            ->assertSee('data-menu-icon="security"', false)
            ->assertSee('data-menu-icon="role"', false)
            ->assertSee('data-menu-icon="permission"', false)
            ->assertSee('data-menu-icon="package"', false)
            ->assertSee('data-menu-icon="ai-applications"', false)
            ->assertSee('data-menu-icon="ai-model"', false)
            ->assertSee('data-menu-icon="report-user"', false)
            ->assertSee('data-menu-icon="report-management"', false)
            ->assertSee('data-menu-icon="template"', false)
            ->assertSee('data-menu-item="admin.system.roles.index"', false)
            ->assertSee('data-menu-item="admin.system.permissions.index"', false)
            ->assertDontSee('name="tenant_id"', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/overview')
            ->assertOk()
            ->assertSee('data-admin-breadcrumbs', false)
            ->assertDontSee(__('dashboard.breadcrumbs.scope').':')
            ->assertSee(__('dashboard.dashboard'))
            ->assertSee(__('system.dashboard_title'))
            ->assertSee('aria-current="page"', false);
    }

    public function test_navigation_paths_breadcrumbs_permissions_and_role_labels_share_one_contract(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinicAdmin = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->assertSame('/admin/crm/leads', parse_url(route('admin.leads.index'), PHP_URL_PATH));
        $this->assertSame('/admin/security/users', parse_url(route('admin.users.index'), PHP_URL_PATH));
        $this->assertSame('/admin/security/roles', parse_url(route('admin.system.roles.index'), PHP_URL_PATH));
        $this->assertSame('/admin/security/permissions', parse_url(route('admin.system.permissions.index'), PHP_URL_PATH));
        $this->assertSame('/admin/finance/plans', parse_url(route('admin.plans.index'), PHP_URL_PATH));
        $this->assertSame('/admin/finance/billing', parse_url(route('admin.billing.index'), PHP_URL_PATH));
        $this->assertSame('/admin/finance/payment-gateways', parse_url(route('admin.system.payment-gateways.index'), PHP_URL_PATH));
        $this->assertSame('/admin/reports/performance', parse_url(route('admin.reports'), PHP_URL_PATH));
        $this->assertSame('/admin/reports/management', parse_url(route('admin.system.reports.index'), PHP_URL_PATH));
        $this->assertSame('/admin/settings/templates', parse_url(route('admin.system.landing-templates.index'), PHP_URL_PATH));
        $this->assertSame('/admin/settings/ai-applications', parse_url(route('admin.system.ai-model-categories.index'), PHP_URL_PATH));
        $this->assertSame('/admin/settings/ai-models', parse_url(route('admin.ai-models.index'), PHP_URL_PATH));
        $this->assertSame('/admin/settings/general', parse_url(route('admin.system.settings.edit'), PHP_URL_PATH));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/users')
            ->assertStatus(301)
            ->assertRedirect('/admin/security/users');

        $rolesResponse = $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/roles')
            ->assertOk()
            ->assertSee(__('system.roles_title'))
            ->assertSee(__('system.roles_subtitle'));

        $this->assertMatchesRegularExpression(
            '/<p\b[^>]*data-admin-topbar-section[^>]*>\s*'.preg_quote(__('menu.groups.users_access'), '/').'\s*<\/p>/u',
            $rolesResponse->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<h1\b[^>]*data-admin-topbar-title[^>]*>\s*'.preg_quote(__('system.roles_title'), '/').'\s*<\/h1>/u',
            $rolesResponse->getContent()
        );

        preg_match('/<nav\b[^>]*data-admin-breadcrumbs[^>]*>.*?<\/nav>/s', $rolesResponse->getContent(), $breadcrumbMatch);
        $this->assertNotEmpty($breadcrumbMatch);
        $this->assertStringContainsString('data-menu-icon="security"', $breadcrumbMatch[0]);
        $this->assertStringContainsString('data-menu-icon="role"', $breadcrumbMatch[0]);
        $this->assertStringContainsString(__('menu.groups.users_access'), $breadcrumbMatch[0]);
        $this->assertStringContainsString(__('menu.items.roles'), $breadcrumbMatch[0]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/permissions')
            ->assertOk()
            ->assertSee('<h3 class="font-bold">'.__('menu.groups.users_access').'</h3>', false)
            ->assertSee('<h3 class="font-bold">'.__('menu.groups.plans_payments').'</h3>', false)
            ->assertSee('مدیریت نقش‌های مدیریتی')
            ->assertSee('roles.manage');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users/'.$clinicAdmin->id.'/edit')
            ->assertOk()
            ->assertSee(__('system.roles.super_admin').' — super_admin')
            ->assertSee(__('system.roles.clinic_admin').' — clinic_admin')
            ->assertSee(__('system.roles.viewer').' — viewer');
    }

    public function test_inactive_plan_users_can_access_allowed_pages_and_restricted_routes_are_blocked(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan('cancelled');

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/finance/plans')->assertOk();
        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/finance/billing')->assertOk();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads')
            ->assertRedirect(route('admin.plans.index'));

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->getJson('/admin/crm/leads')
            ->assertForbidden()
            ->assertSee(__('permissions.plan_required'), false);
    }

    public function test_super_admin_can_manage_plan_definitions_and_normal_user_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $normalUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $planModel = AiModelAccess::query()
            ->where('model_key', 'openai')
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans', [
                'name' => 'پلن مدیریتی',
                'description' => 'برای تست مدیریت پلن',
                'price' => 4500000,
                'currency' => 'IRR',
                'duration_days' => 30,
                'usage_limit' => 50,
                'status' => 'active',
                'ai_model_access_ids' => [$planModel->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', ['name' => 'پلن مدیریتی', 'is_public' => false]);
        $managedPlan = Plan::query()->where('name', 'پلن مدیریتی')->firstOrFail();
        $this->assertDatabaseHas('plan_ai_model_access', [
            'plan_id' => $managedPlan->id,
            'ai_model_access_id' => $planModel->id,
        ]);

        $higherPriorityModel = AiModelAccess::query()
            ->where('model_key', 'gapgpt')
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();
        $higherPriorityModel->update(['is_active' => true, 'priority' => 1]);
        $planModel->update(['priority' => 10]);
        app(ExecutableAiModelSyncService::class)->sync($higherPriorityModel, $superAdmin);
        $planModel->update(['priority' => 20]);

        $plansResponse = $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/plans?'.http_build_query(['search' => 'پلن مدیریتی', 'status' => 'active', 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('پلن مدیریتی')
            ->assertSee(__('plans.statuses.active'))
            ->assertSee('create-plan-modal-toggle', false)
            ->assertSee('data-admin-modal-trigger="edit-plan-', false)
            ->assertSee('data-admin-table-container', false)
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false);

        $this->assertMatchesRegularExpression(
            '/<details[^>]*data-table-action-menu[^>]*>(?:(?!<\/details>).)*data-admin-modal-trigger="edit-plan-\d+"(?:(?!<\/details>).)*<\/details>/s',
            $plansResponse->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<input name="service_keys\[\]" type="checkbox" value="[^"]+" class="(?![^"]*\bsr-only\b)[^"]*"/',
            $plansResponse->getContent()
        );
        $this->assertSame(1, preg_match('/<table\b[^>]*>.*?<\/table>/s', $plansResponse->getContent(), $planTableMatches));
        $this->assertStringNotContainsString(__('plans.assigned_services'), $planTableMatches[0]);
        $this->assertStringNotContainsString(__('ai_models.usage_categories.hair_transplant'), $planTableMatches[0]);
        $plansResponse
            ->assertSee(__('plans.assigned_services'))
            ->assertSee('name="ai_model_access_ids[]"', false)
            ->assertSee('data-plan-model-picker', false)
            ->assertSee($planModel->name);
        $this->assertMatchesRegularExpression(
            '/<select\s+name="ai_model_access_ids\[\]"[^>]*data-plan-model/s',
            $plansResponse->getContent()
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*name="ai_model_access_ids\[\]"[^>]*type="checkbox"/s',
            $plansResponse->getContent()
        );
        $higherPriorityLabel = $higherPriorityModel->name.' ('.$higherPriorityModel->model_key.')';
        $lowerPriorityLabel = $planModel->name.' ('.$planModel->model_key.')';
        $this->assertMatchesRegularExpression(
            '/<option value="'.$higherPriorityModel->id.'"[^>]*>\s*'.preg_quote($higherPriorityLabel, '/').'\s*<\/option>/',
            $plansResponse->getContent()
        );
        $this->assertMatchesRegularExpression(
            '/<option value="'.$planModel->id.'"[^>]*>\s*'.preg_quote($lowerPriorityLabel, '/').'\s*<\/option>/',
            $plansResponse->getContent()
        );
        $this->assertTrue(
            strpos($plansResponse->getContent(), $higherPriorityLabel)
                < strpos($plansResponse->getContent(), $lowerPriorityLabel)
        );

        $eyebrowModel = AiModelAccess::query()
            ->where('model_key', 'openai')
            ->where('usage_category_key', 'eyebrow_transplant')
            ->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans', [
                'name' => 'پلن با مدل نامرتبط',
                'plan_type' => 'base_subscription',
                'price' => 1000,
                'currency' => 'ریال',
                'duration_days' => 30,
                'usage_limit' => 10,
                'status' => 'active',
                'service_keys' => ['eyebrow_transplant'],
                'ai_model_access_ids' => [$planModel->id],
            ])
            ->assertSessionHasErrors('ai_model_access_ids');

        $this->assertDatabaseMissing('plans', ['name' => 'پلن با مدل نامرتبط']);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/finance/plans/'.$managedPlan->id, [
                '_form' => 'edit_plan_'.$managedPlan->id,
                'name' => $managedPlan->name,
                'description' => $managedPlan->description,
                'plan_type' => 'base_subscription',
                'price' => $managedPlan->price,
                'currency' => $managedPlan->currency,
                'duration_days' => $managedPlan->duration_days,
                'usage_limit' => $managedPlan->usage_limit,
                'status' => 'active',
                'service_keys' => ['eyebrow_transplant'],
                'ai_model_access_ids' => [$eyebrowModel->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('plan_ai_model_access', [
            'plan_id' => $managedPlan->id,
            'ai_model_access_id' => $eyebrowModel->id,
        ]);
        $this->assertDatabaseMissing('plan_ai_model_access', [
            'plan_id' => $managedPlan->id,
            'ai_model_access_id' => $planModel->id,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/plans/'.$managedPlan->id)
            ->assertOk()
            ->assertSee(__('plans.assigned_services'))
            ->assertSee(__('ai_models.usage_categories.eyebrow_transplant'));

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/plans')
            ->assertOk()
            ->assertSee(__('plans.current_plan'))
            ->assertSee('data-current-plan="true"', false)
            ->assertSee(__('plans.same_active_plan_blocked'))
            ->assertSee(__('plans.fields.plan_type'))
            ->assertDontSee('پلن مدیریتی')
            ->assertDontSee('data-admin-table-container', false);

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/plans?plan_type=service_add_on')
            ->assertOk()
            ->assertSee(__('plans.types.service_add_on'))
            ->assertSee(__('plans.not_purchasable'))
            ->assertSee(__('plans.purchase_disabled.already_available'))
            ->assertDontSee('<h2 class="text-lg font-bold">پلن شروع کاشت مو</h2>', false);

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.plan.upgrade_plan'));

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans', [
                'name' => 'پلن غیرمجاز',
                'description' => 'نباید ساخته شود',
                'price' => 1000,
                'currency' => 'IRR',
                'duration_days' => 10,
                'usage_limit' => 5,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_plan_create_and_update_require_exactly_one_complete_model_per_application(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $secondHairModel = AiModelAccess::query()
            ->where('model_key', 'gapgpt')
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();
        $secondHairModel->update(['is_active' => true]);
        app(ExecutableAiModelSyncService::class)->sync($secondHairModel, $superAdmin);
        $models = app(ClinicAiModelProvisioningService::class)
            ->assignableAccesses(['hair_transplant'])
            ->values();

        $this->assertGreaterThanOrEqual(2, $models->count());

        $payload = [
            'name' => 'پلن انتخاب تکی مدل',
            'plan_type' => 'base_subscription',
            'price' => 1000,
            'currency' => 'ریال',
            'duration_days' => 30,
            'usage_limit' => 10,
            'status' => 'active',
            'service_keys' => ['hair_transplant'],
        ];

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans', $payload + [
                'ai_model_access_ids' => $models->pluck('id')->all(),
            ])
            ->assertSessionHasErrors([
                'ai_model_access_ids' => __('plans.validation.one_model_per_service'),
            ]);

        $this->assertDatabaseMissing('plans', ['name' => $payload['name']]);

        $this->assertTrue(
            app(ClinicAiModelProvisioningService::class)
                ->assignableAccesses(['eyebrow_transplant'])
                ->isNotEmpty()
        );

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans', array_replace($payload, [
                'name' => 'پلن با کاربرد بدون مدل',
                'service_keys' => ['hair_transplant', 'eyebrow_transplant'],
                'ai_model_access_ids' => [$models->first()->id],
            ]))
            ->assertSessionHasErrors([
                'ai_model_access_ids' => __('plans.validation.one_model_per_service'),
            ]);

        $this->assertDatabaseMissing('plans', ['name' => 'پلن با کاربرد بدون مدل']);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans', $payload + [
                'ai_model_access_ids' => [$models->first()->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = Plan::query()->where('name', $payload['name'])->firstOrFail();
        $this->assertSame(1, $plan->aiModelAccesses()->count());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/finance/plans/'.$plan->id, $payload + [
                '_form' => 'edit_plan_'.$plan->id,
                'ai_model_access_ids' => $models->pluck('id')->all(),
            ])
            ->assertSessionHasErrors([
                'ai_model_access_ids' => __('plans.validation.one_model_per_service'),
            ]);

        $this->assertSame([$models->first()->id], $plan->aiModelAccesses()->pluck('ai_model_accesses.id')->all());

        $partialModel = $models->last();
        $partialModel->executableModel->outputConfigs()->where('is_active', true)->firstOrFail()->update(['is_active' => false]);

        $availableIds = app(ClinicAiModelProvisioningService::class)
            ->assignableAccesses(['hair_transplant'])
            ->pluck('id');

        $this->assertNotContains($partialModel->id, $availableIds);
        $this->assertContains($models->first()->id, $availableIds);
    }

    public function test_super_admin_can_manage_clinics_and_normal_user_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $normalUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $landingTemplate = Template::query()->where('is_active', true)->orderByDesc('is_default')->firstOrFail();
        $this->activateSubscription($normalUser);

        $this->assertTrue($superAdmin->hasPermission('tenants.list'));
        $this->assertTrue($superAdmin->hasPermission('tenants.create'));
        $this->assertTrue($superAdmin->hasPermission('tenants.edit'));
        $this->assertTrue($superAdmin->hasPermission('tenants.delete'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics')
            ->assertOk()
            ->assertSee(__('clinics.create'))
            ->assertDontSee(__('clinics.fields.landing_template'))
            ->assertDontSee($landingTemplate->name)
            ->assertSee(__('menu.items.clinics'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics', [
                '_form' => 'create_clinic',
                'name' => 'کلینیک تست',
                'slug' => 'test-clinic',
                'status' => 'active',
                'domain' => 'test-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'active',
            ])
            ->assertRedirect();

        $clinic = Tenant::query()->where('slug', 'test-clinic')->firstOrFail();
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $clinic->id,
            'domain' => 'test-clinic.hairvision-ai.test',
            'status' => 'active',
        ]);
        $this->assertNull($clinic->theme()->first());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$clinic->id.'/edit')
            ->assertOk()
            ->assertSee('test-clinic.hairvision-ai.test')
            ->assertSee(__('clinics.fields.landing_template'))
            ->assertSee($landingTemplate->name)
            ->assertDontSee('value="'.$landingTemplate->id.'" selected', false)
            ->assertDontSee(__('clinics.manage_models'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$clinic->id)
            ->assertOk()
            ->assertSee('test-clinic.hairvision-ai.test')
            ->assertSee(__('clinics.manage_models'))
            ->assertSee(__('clinics.assign_plan'))
            ->assertSee(__('clinics.leads_count'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/clinics/'.$clinic->id, [
                '_form' => 'edit_clinic',
                'name' => 'کلینیک تست ویرایش',
                'slug' => 'test-clinic-updated',
                'status' => 'inactive',
                'domain' => 'updated-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'inactive',
                'template_id' => $landingTemplate->id,
            ])
            ->assertRedirect(route('admin.clinics.edit', $clinic));

        $this->assertDatabaseHas('tenants', [
            'id' => $clinic->id,
            'name' => 'کلینیک تست ویرایش',
            'slug' => 'test-clinic-updated',
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('tenant_domains', [
            'tenant_id' => $clinic->id,
            'domain' => 'updated-clinic.hairvision-ai.test',
            'status' => 'inactive',
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics', [
                '_form' => 'create_clinic',
                'name' => 'کلینیک تکراری',
                'slug' => 'duplicate-clinic',
                'status' => 'active',
                'domain' => 'updated-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'active',
            ])
            ->assertSessionHasErrors('domain');

        $this->actingAs($normalUser)
            ->withSession(['admin_active_tenant_id' => $normalUser->tenants()->firstOrFail()->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/settings/clinics/'.$clinic->id)
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertDatabaseMissing('tenants', ['id' => $clinic->id]);
    }

    public function test_super_admin_can_view_and_assign_clinic_plans_from_clinic_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinic = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $clinicUser = $clinic->users()->wherePivot('status', 'active')->where('users.role', 'clinic_admin')->firstOrFail();
        $currentSubscription = Subscription::query()
            ->with('plan')
            ->where('tenant_id', $clinic->id)
            ->where('status', 'active')
            ->firstOrFail();

        $upgrade = Plan::query()->create([
            'name' => 'پلن ویژه کلینیک‌ها',
            'description' => 'پلن اختصاصی برای تست انتساب مستقیم',
            'plan_type' => 'base_subscription',
            'service_key' => 'hair_transplant',
            'price' => 25000000,
            'currency' => 'IRR',
            'duration_days' => 45,
            'usage_limit' => 1200,
            'is_public' => false,
            'status' => 'active',
        ]);
        $upgrade->serviceEntitlements()->create([
            'service_key' => 'hair_transplant',
            'credit_amount' => 1200,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics')
            ->assertOk()
            ->assertSee(__('clinics.plan_status'))
            ->assertSee($currentSubscription->plan->name)
            ->assertSee(__('subscriptions.remaining_credit'))
            ->assertDontSee(__('clinics.assign_plan'))
            ->assertDontSee(__('clinics.manage_models'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$clinic->id)
            ->assertOk()
            ->assertSee(__('clinics.assign_plan'))
            ->assertSee('پلن ویژه کلینیک‌ها')
            ->assertSee(__('clinics.manage_models'))
            ->assertSee(__('clinics.leads_count'))
            ->assertSee(__('clinics.models_count'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$clinic->id.'/plans', [
                '_form' => 'assign_clinic_plan_'.$clinic->id,
                'plan_id' => $upgrade->id,
                'discount_amount' => 5000000,
            ])
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHasNoErrors();

        $this->assertSame('active', $currentSubscription->refresh()->status);
        $invoice = Invoice::query()
            ->where('tenant_id', $clinic->id)
            ->whereNull('user_id')
            ->where('plan_id', $upgrade->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $this->assertMatchesRegularExpression('/^BS-\d+$/', $invoice->number);
        $this->assertSame(20000000, $invoice->amount);
        $this->assertSame(25000000, data_get($invoice->metadata_json, 'original_amount'));
        $this->assertSame(5000000, data_get($invoice->metadata_json, 'discount_amount'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/settings/clinics/'.$clinic->id.'/invoices/'.$invoice->id.'/approve')
            ->assertRedirect(route('admin.clinics.show', $clinic));

        $this->assertSame('replaced', $currentSubscription->refresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $clinic->id,
            'user_id' => null,
            'plan_id' => $upgrade->id,
            'status' => 'active',
            'usage_limit' => 1200,
        ]);
        $this->assertNotNull($invoice->refresh()->activated_at);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$clinic->id.'/invoices', [
                '_form' => 'create_clinic_invoice',
                'plan_id' => $upgrade->id,
                'amount' => 990000,
                'currency' => 'IRR',
                'status' => 'pending',
                'note' => 'فاکتور دستی تست',
            ])
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHasNoErrors();

        $manualInvoice = Invoice::query()
            ->where('tenant_id', $clinic->id)
            ->where('plan_id', $upgrade->id)
            ->where('amount', 990000)
            ->firstOrFail();
        $this->assertMatchesRegularExpression('/^BS-\d+$/', $manualInvoice->number);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/clinics/'.$clinic->id.'/invoices/'.$manualInvoice->id, [
                '_form' => 'edit_clinic_invoice_'.$manualInvoice->id,
                'plan_id' => $upgrade->id,
                'user_id' => $clinicUser->id,
                'amount' => 1290000,
                'currency' => 'IRR',
                'status' => 'failed',
                'note' => 'ویرایش دستی تست',
            ])
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', [
            'id' => $manualInvoice->id,
            'tenant_id' => $clinic->id,
            'user_id' => $clinicUser->id,
            'plan_id' => $upgrade->id,
            'amount' => 1290000,
            'status' => 'failed',
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$clinic->id)
            ->assertOk()
            ->assertSee('data-admin-modal-trigger="clinic-invoice-details-'.$manualInvoice->id.'"', false)
            ->assertSee('data-table-row-view-action', false)
            ->assertSee(__('payments.view'))
            ->assertSee(__('payments.invoice_information'))
            ->assertSee(__('payments.payment_information'))
            ->assertSee($manualInvoice->number);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$clinic->id.'/sms-gateway', [
                '_form' => 'clinic_sms_gateway',
                'provider_code' => 'ippanel',
                'api_key' => 'clinic-secret-key',
                'from_number' => '10002000',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sms_gateways', [
            'tenant_id' => $clinic->id,
            'owner_scope' => 'tenant',
            'provider_code' => 'ippanel',
            'status' => 'enabled',
            'is_default' => true,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$clinic->id)
            ->assertOk()
            ->assertSee(route('admin.clinics.sms-gateway.destroy', $clinic), false)
            ->assertSee(__('sms.delete'))
            ->assertSee(__('sms.confirm_private_delete'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/settings/clinics/'.$clinic->id.'/sms-gateway')
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHas('status', __('sms.private_deleted'));

        $this->assertDatabaseMissing('sms_gateways', [
            'tenant_id' => $clinic->id,
            'owner_scope' => 'tenant',
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics')
            ->assertOk()
            ->assertSee('پلن ویژه کلینیک‌ها')
            ->assertSee(__('payments.subscription_statuses.active'));

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $clinic->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$clinic->id.'/plans', [
                '_form' => 'assign_clinic_plan_'.$clinic->id,
                'plan_id' => $upgrade->id,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_approve_clinic_invoice_when_tenant_id_is_a_numeric_string(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinic = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $invoice = Invoice::query()->create([
            'tenant_id' => $clinic->id,
            'number' => 'MI-NUMERIC-TENANT-ID',
            'status' => 'pending',
            'amount' => 1000000,
            'currency' => 'IRR',
        ]);
        $attributes = $invoice->getAttributes();
        $attributes['tenant_id'] = (string) $clinic->id;
        $invoice->setRawAttributes($attributes, true);
        Route::bind('invoice', fn (): Invoice => $invoice);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/settings/clinics/'.$clinic->id.'/invoices/'.$invoice->id.'/approve')
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'tenant_id' => $clinic->id,
            'status' => 'paid',
        ]);
    }

    public function test_user_can_purchase_pay_and_upgrade_active_plan_without_carrying_credit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        $this->fakeZarinpalPayments('A000000000000000000000000000000000101', 'A000000000000000000000000000000000102');

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $starter = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $upgrade = Plan::query()->create([
            'name' => 'پلن مسیر رشد',
            'description' => 'پلن ارتقا برای تست مسیر پرداخت',
            'price' => 14900000,
            'currency' => 'IRR',
            'duration_days' => 60,
            'usage_limit' => 1800,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$starter->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $invoice = $user->invoices()->where('plan_id', $starter->id)->where('status', 'pending')->latest('id')->firstOrFail();
        $payment = $this->completeInvoicePayment($user, $invoice, 'A000000000000000000000000000000000101', 'get');
        $this->assertSame('paid', $payment->refresh()->status);

        $active = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $active->forceFill(['usage_used' => 80])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$upgrade->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $upgradeInvoice = $user->invoices()->where('plan_id', $upgrade->id)->firstOrFail();
        $this->completeInvoicePayment($user, $upgradeInvoice, 'A000000000000000000000000000000000102');

        $newActive = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $this->assertSame('replaced', $active->refresh()->status);
        $this->assertSame($upgrade->id, $newActive->plan_id);
        $this->assertSame(0, $newActive->usage_used);
        $this->assertSame(1800, $newActive->usage_limit);
    }

    public function test_plan_purchase_recovers_from_legacy_object_cached_system_settings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        Cache::setDefaultDriver('database');
        Cache::forgetDriver('database');
        Cache::put('hairvision:system-settings', collect([
            'billing.invoice_due_days' => 7,
        ]), 300);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$plan->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invoices', [
            'tenant_id' => $user->tenants()->firstOrFail()->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
        ]);
        $this->assertIsArray(Cache::get('hairvision:system-settings'));
    }

    public function test_clinic_user_can_pay_owned_invoice_when_database_ids_are_numeric_strings(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        $authority = 'A000000000000000000000000000000000301';
        $this->fakeZarinpalPayments($authority);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $tenant = $user->tenants()->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $invoice = app(BillingService::class)->invoiceForPlan($user, $plan, $tenant);
        $attributes = $invoice->getAttributes();
        $attributes['tenant_id'] = (string) $tenant->id;
        $attributes['user_id'] = (string) $user->id;
        $invoice->setRawAttributes($attributes, true);
        Route::bind('invoice', fn (): Invoice => $invoice);

        $otherUser = $this->createUserForTenant($tenant, 'clinic_admin', '09121112222');
        $this->actingAs($otherUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertForbidden();

        $otherTenant = $this->createTenant('Clinic Two', 'clinic-two', 'clinic-two.hairvision-ai.test');
        $otherTenantUser = $this->createUserForTenant($otherTenant, 'clinic_admin', '09121113333');
        $this->actingAs($otherTenantUser)
            ->withSession(['admin_active_tenant_id' => $otherTenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertForbidden();

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/invoices/'.$invoice->id.'/pay')
            ->assertStatus(303)
            ->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);
    }

    public function test_same_active_base_plan_cannot_be_purchased_until_expired(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->fakeZarinpalPayments('A000000000000000000000000000000000201', 'A000000000000000000000000000000000202');

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('name', 'پلن شروع')->where('status', 'active')->firstOrFail();
        $hairOnlyPlan = Plan::query()->where('name', 'پلن شروع کاشت مو')->where('status', 'active')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$plan->id.'/purchase')
            ->assertSessionHasErrors('plan');

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$hairOnlyPlan->id.'/purchase')
            ->assertSessionHasErrors('plan');

        $active = $user->subscriptions()->where('status', 'active')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$plan->id.'/purchase')
            ->assertSessionHasErrors('plan');

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/plans')
            ->assertOk()
            ->assertSee(__('plans.same_active_plan_blocked'));

        $active->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$plan->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $renewedInvoice = $user->invoices()->where('plan_id', $plan->id)->where('status', 'pending')->latest('id')->firstOrFail();
        $this->completeInvoicePayment($user, $renewedInvoice, 'A000000000000000000000000000000000201');

        $renewed = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $renewed->serviceEntitlements()->firstOrFail()->forceFill(['used_credit' => $renewed->serviceEntitlements()->firstOrFail()->totalCredit()])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/finance/plans/'.$plan->id.'/purchase')
            ->assertSessionHasErrors('plan');

        $this->assertSame('active', $renewed->refresh()->status);
    }

    public function test_crm_follow_up_creation_timeline_visibility_and_tenant_scope(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $userA = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $userB = $this->createUserForTenant($tenantB, 'clinic_admin', '09122222222');
        $this->activateSubscription($userA);
        $this->activateSubscription($userB);
        $leadA = $this->createLead($tenantA, '09120000001', 'Tenant A Lead');
        $leadB = $this->createLead($tenantB, '09120000002', 'Tenant B Lead');
        $nextFollowUpAt = JalaliDate::toJalaliDateTime(now()->addDay());

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/crm/leads/'.$leadA->id.'/follow-ups', [
                'status' => 'needs_follow_up',
                'communication_channel' => 'phone',
                'note' => 'تماس اولیه انجام شد و نیاز به پیگیری دارد.',
                'next_follow_up_at' => $nextFollowUpAt,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lead_notes', [
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'user_id' => $userA->id,
            'status' => 'needs_follow_up',
            'communication_channel' => 'phone',
        ]);
        $this->assertSame('needs_follow_up', $leadA->refresh()->status);
        $this->assertDatabaseHas('lead_notes', [
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'next_follow_up_at' => $nextFollowUpAt,
        ]);

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$leadA->id)
            ->assertOk()
            ->assertSee('تماس اولیه انجام شد')
            ->assertSee(__('crm.timeline_title'))
            ->assertSee('data-jalali-datetime', false)
            ->assertSee('data-menu-icon="calendar"', false);

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$leadB->id)
            ->assertForbidden();

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$leadA->id)
            ->assertForbidden();
    }

    public function test_lead_uploaded_image_displays_and_streams_only_for_authorized_user(): void
    {
        Storage::fake('local');

        $tenantA = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $userA = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $userB = $this->createUserForTenant($tenantB, 'clinic_admin', '09122222222');
        $this->activateSubscription($userA);
        $this->activateSubscription($userB);
        $lead = $this->createLead($tenantA, '09120000001', 'Tenant A Lead');
        $path = 'uploads/'.$lead->uuid.'/photo.jpg';
        Storage::disk('local')->put($path, 'fake-image-bytes');

        $upload = Upload::query()->create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'original_disk' => 'local',
            'original_path' => $path,
            'mime' => 'image/jpeg',
            'size' => 16,
            'status' => 'validated',
        ]);
        $provider = AiProvider::query()->create([
            'code' => 'sample_image',
            'name' => 'Sample Image',
            'type' => 'local',
            'is_active' => true,
        ]);
        $group = GenerationGroup::query()->create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'upload_id' => $upload->id,
            'status' => 'completed',
        ]);
        $job = GenerationJob::query()->create([
            'generation_group_id' => $group->id,
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'upload_id' => $upload->id,
            'provider_id' => $provider->id,
            'period' => 'two_weeks',
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $assetPath = 'tenants/'.$tenantA->uuid.'/leads/'.$lead->uuid.'/results/'.$group->uuid.'/two_weeks.jpg';
        Storage::disk('local')->put($assetPath, 'fake-generated-image-bytes');
        $asset = GeneratedAsset::query()->create([
            'generation_group_id' => $group->id,
            'generation_job_id' => $job->id,
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'period' => 'two_weeks',
            'disk' => 'local',
            'path' => $assetPath,
            'mime' => 'image/jpeg',
            'size' => 26,
        ]);

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee(route('admin.leads.uploads.show', [$lead, $upload]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $asset]))
            ->assertSee('data-processing-upload="'.$upload->uuid.'"', false)
            ->assertSee('data-processing-media-grid', false)
            ->assertSee('data-processing-source-tile="upload-'.$upload->uuid.'"', false)
            ->assertSee('data-processing-result-tile="asset-'.$asset->uuid.'"', false)
            ->assertSee('data-generation-result-upload="'.$upload->uuid.'"', false)
            ->assertSee('data-lead-gallery-trigger="upload-'.$upload->uuid.'"', false)
            ->assertSee('data-gallery-key="upload-'.$upload->uuid.'"', false)
            ->assertSee('data-gallery-key="asset-'.$asset->uuid.'"', false)
            ->assertSee('data-lead-gallery-modal', false)
            ->assertSee(__('leads.gallery.legacy_source_title'))
            ->assertSee(__('leads.uploads.title'));

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/uploads/'.$upload->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/assets/'.$asset->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/uploads/'.$upload->id)
            ->assertForbidden();

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/assets/'.$asset->id)
            ->assertForbidden();
    }

    public function test_v2_source_and_generated_images_display_for_clinic_and_system_admins(): void
    {
        Storage::fake('local');

        $tenantA = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $clinicAdmin = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $otherClinicAdmin = $this->createUserForTenant($tenantB, 'clinic_admin', '09122222222');
        $this->activateSubscription($clinicAdmin);
        $this->activateSubscription($otherClinicAdmin);

        $lead = $this->createLead($tenantA, '09120000001', 'Version Two Lead');
        $profile = LeadProfile::query()->create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
        ]);
        $sourcePath = 'tenants/'.$tenantA->uuid.'/leads/'.$lead->uuid.'/source-images/current.jpg';
        Storage::disk('local')->put($sourcePath, 'v2-source-image');
        $source = LeadSourceImage::query()->create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'lead_profile_id' => $profile->id,
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => $sourcePath,
            'mime' => 'image/jpeg',
            'size' => 15,
            'status' => 'validated',
        ]);

        $category = AiModelCategory::query()->create([
            'code' => 'dashboard_preview',
            'name' => 'پیش‌نمایش داشبورد',
            'status' => 'active',
        ]);
        $output = AiModelCategoryOutput::query()->create([
            'ai_model_category_id' => $category->id,
            'code' => 'final_result',
            'name' => 'نتیجه نهایی داشبورد',
            'prompt' => 'محرمانه',
            'credit_cost' => 1,
            'is_active' => true,
        ]);
        $generation = GenerationRequest::query()->create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $source->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'status' => 'completed',
            'idempotency_key' => 'v2-dashboard-result',
            'requested_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
        $assetPath = 'tenants/'.$tenantA->uuid.'/leads/'.$lead->uuid.'/results/final-result.jpg';
        Storage::disk('local')->put($assetPath, 'v2-generated-image');
        $asset = GeneratedAsset::query()->create([
            'tenant_id' => $tenantA->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $source->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'generation_request_id' => $generation->id,
            'period' => $output->code,
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => $assetPath,
            'mime' => 'image/jpeg',
            'size' => 18,
        ]);
        $generation->update(['current_generated_asset_id' => $asset->id]);

        $sourceRoute = route('admin.leads.source-images.show', [$lead, $source]);
        $assetRoute = route('admin.leads.assets.show', [$lead, $asset]);

        $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee($sourceRoute)
            ->assertSee($assetRoute)
            ->assertSee($category->name)
            ->assertSee($output->name)
            ->assertSee('data-processing-source-tile="source-'.$source->uuid.'"', false)
            ->assertSee('data-processing-result-tile="asset-'.$asset->uuid.'"', false)
            ->assertSee('data-lead-gallery-trigger="source-'.$source->uuid.'"', false)
            ->assertSee('data-gallery-key="source-'.$source->uuid.'"', false)
            ->assertSee('data-gallery-key="asset-'.$asset->uuid.'"', false)
            ->assertSee(__('leads.gallery.current_source_title'))
            ->assertSee(JalaliDate::toJalaliDateTime($asset->created_at));

        $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/source-images/'.$source->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/assets/'.$asset->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($otherClinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id.'/source-images/'.$source->id)
            ->assertForbidden();

        $systemAdmin = User::factory()->create([
            'role' => 'super_admin',
            'access_scope' => 'system',
            'status' => 'active',
        ]);

        $this->actingAs($systemAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee($sourceRoute)
            ->assertSee($assetRoute);
    }

    public function test_lead_processing_history_groups_each_source_image_with_its_result_versions(): void
    {
        Storage::fake('local');

        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $clinicAdmin = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($clinicAdmin);
        $lead = $this->createLead($tenant, '09120000001', 'History Lead');
        $profile = LeadProfile::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
        ]);
        $category = AiModelCategory::query()->create([
            'code' => 'history_preview',
            'name' => 'پیش‌نمایش تاریخچه',
            'status' => 'active',
        ]);
        $output = AiModelCategoryOutput::query()->create([
            'ai_model_category_id' => $category->id,
            'code' => 'final_result',
            'name' => 'نتیجه تاریخچه',
            'description' => 'توضیح قابل مشاهده نتیجه تاریخچه',
            'prompt' => 'محرمانه',
            'credit_cost' => 1,
            'is_active' => true,
        ]);

        $firstSourcePath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/source-images/first.jpg';
        $latestSourcePath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/source-images/latest.jpg';
        Storage::disk('local')->put($firstSourcePath, 'first-source');
        Storage::disk('local')->put($latestSourcePath, 'latest-source');

        $firstSource = LeadSourceImage::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_profile_id' => $profile->id,
            'version' => 1,
            'is_current' => false,
            'replaced_at' => now()->subHour(),
            'disk' => 'local',
            'path' => $firstSourcePath,
            'mime' => 'image/jpeg',
            'size' => 12,
            'status' => 'validated',
        ]);
        $latestSource = LeadSourceImage::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_profile_id' => $profile->id,
            'version' => 2,
            'is_current' => true,
            'disk' => 'local',
            'path' => $latestSourcePath,
            'mime' => 'image/jpeg',
            'size' => 13,
            'status' => 'validated',
        ]);

        $firstGeneration = GenerationRequest::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $firstSource->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'status' => 'completed',
            'idempotency_key' => 'history-first-source',
            'requested_at' => now()->subHours(2),
            'completed_at' => now()->subHours(2),
        ]);
        $latestGeneration = GenerationRequest::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $latestSource->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'status' => 'completed',
            'idempotency_key' => 'history-latest-source',
            'requested_at' => now()->subHour(),
            'completed_at' => now()->subHour(),
        ]);

        $firstAssetPath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/results/first.jpg';
        $archivedAssetPath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/results/latest-v1.jpg';
        $currentAssetPath = 'tenants/'.$tenant->uuid.'/leads/'.$lead->uuid.'/results/latest-v2.jpg';
        Storage::disk('local')->put($firstAssetPath, 'first-result');
        Storage::disk('local')->put($archivedAssetPath, 'archived-result');
        Storage::disk('local')->put($currentAssetPath, 'current-result');

        $firstAsset = GeneratedAsset::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $firstSource->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'generation_request_id' => $firstGeneration->id,
            'period' => $output->code,
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => $firstAssetPath,
            'mime' => 'image/jpeg',
            'size' => 12,
        ]);
        $archivedAsset = GeneratedAsset::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $latestSource->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'generation_request_id' => $latestGeneration->id,
            'period' => $output->code,
            'version' => 1,
            'is_current' => false,
            'disk' => 'local',
            'path' => $archivedAssetPath,
            'mime' => 'image/jpeg',
            'size' => 15,
            'archived_at' => now()->subMinutes(30),
        ]);
        $currentAsset = GeneratedAsset::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $latestSource->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $output->id,
            'generation_request_id' => $latestGeneration->id,
            'period' => $output->code,
            'version' => 2,
            'is_current' => true,
            'disk' => 'local',
            'path' => $currentAssetPath,
            'mime' => 'image/jpeg',
            'size' => 14,
        ]);
        $firstGeneration->update(['current_generated_asset_id' => $firstAsset->id]);
        $latestGeneration->update(['current_generated_asset_id' => $currentAsset->id]);

        $response = $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee('data-processing-source-image="'.$latestSource->uuid.'"', false)
            ->assertSee('data-processing-source-image="'.$firstSource->uuid.'"', false)
            ->assertSee('data-generation-result-source-image="'.$latestSource->uuid.'"', false)
            ->assertSee('data-generation-result-source-image="'.$firstSource->uuid.'"', false)
            ->assertSee(route('admin.leads.source-images.show', [$lead, $latestSource]))
            ->assertSee(route('admin.leads.source-images.show', [$lead, $firstSource]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $firstAsset]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $archivedAsset]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $currentAsset]))
            ->assertSee('data-gallery-key="source-'.$latestSource->uuid.'"', false)
            ->assertSee('data-gallery-key="source-'.$firstSource->uuid.'"', false)
            ->assertSee('data-gallery-key="asset-'.$firstAsset->uuid.'"', false)
            ->assertSee('data-gallery-key="asset-'.$archivedAsset->uuid.'"', false)
            ->assertSee('data-gallery-key="asset-'.$currentAsset->uuid.'"', false)
            ->assertSee('data-processing-media-grid', false)
            ->assertSee('data-processing-source-tile="source-'.$latestSource->uuid.'"', false)
            ->assertSee('data-processing-result-tile="asset-'.$archivedAsset->uuid.'"', false)
            ->assertSee('data-processing-result-tile="asset-'.$currentAsset->uuid.'"', false)
            ->assertDontSee(__('leads.generations.description'))
            ->assertSee(__('leads.gallery.title'))
            ->assertSee(__('leads.source_images.current'))
            ->assertSee(__('leads.source_images.archived'))
            ->assertSee(__('leads.generations.current_result'))
            ->assertSee(__('leads.generations.archived_result'));

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'data-processing-source-image="'.$firstSource->uuid.'"'),
            strpos($html, 'data-processing-source-image="'.$latestSource->uuid.'"'),
        );
        $this->assertLessThan(
            strpos($html, 'data-processing-result-tile="asset-'.$archivedAsset->uuid.'"'),
            strpos($html, 'data-processing-source-tile="source-'.$latestSource->uuid.'"'),
        );
        $this->assertLessThan(
            strpos($html, 'data-processing-result-tile="asset-'.$currentAsset->uuid.'"'),
            strpos($html, 'data-processing-result-tile="asset-'.$archivedAsset->uuid.'"'),
        );
        $this->assertLessThan(
            strpos($html, 'data-gallery-key="asset-'.$currentAsset->uuid.'"'),
            strpos($html, 'data-gallery-key="source-'.$latestSource->uuid.'"'),
        );
        $this->assertLessThan(
            strpos($html, 'data-gallery-key="source-'.$firstSource->uuid.'"'),
            strpos($html, 'data-gallery-key="asset-'.$currentAsset->uuid.'"'),
        );
        $this->assertLessThan(
            strpos($html, 'data-gallery-key="asset-'.$firstAsset->uuid.'"'),
            strpos($html, 'data-gallery-key="source-'.$firstSource->uuid.'"'),
        );
        $this->assertLessThan(
            strpos($html, route('admin.leads.source-images.show', [$lead, $firstSource])),
            strpos($html, route('admin.leads.source-images.show', [$lead, $latestSource])),
        );

        $systemAdmin = User::factory()->create([
            'role' => 'super_admin',
            'access_scope' => 'system',
            'status' => 'active',
        ]);

        $this->actingAs($systemAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee('data-processing-source-image="'.$latestSource->uuid.'"', false)
            ->assertSee('data-processing-source-image="'.$firstSource->uuid.'"', false)
            ->assertSee(route('admin.leads.assets.show', [$lead, $firstAsset]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $archivedAsset]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $currentAsset]));
    }

    public function test_lead_details_leave_unrequested_generation_outputs_without_status_or_date(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $clinicAdmin = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($clinicAdmin);
        $lead = $this->createLead($tenant, '09120000001', 'Independent Lead');
        $profile = LeadProfile::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
        ]);
        $source = LeadSourceImage::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_profile_id' => $profile->id,
            'version' => 1,
            'is_current' => true,
            'disk' => 'local',
            'path' => 'private/source.jpg',
            'mime' => 'image/jpeg',
            'size' => 15,
            'status' => 'validated',
        ]);
        $category = AiModelCategory::query()->create([
            'code' => 'independent_dashboard_preview',
            'name' => 'پیش‌نمایش مستقل داشبورد',
            'status' => 'active',
        ]);
        $requestedOutput = AiModelCategoryOutput::query()->create([
            'ai_model_category_id' => $category->id,
            'code' => 'requested_stage',
            'name' => 'مرحله درخواست‌شده',
            'prompt' => 'محرمانه',
            'credit_cost' => 1,
            'is_active' => true,
        ]);
        $unrequestedOutput = AiModelCategoryOutput::query()->create([
            'ai_model_category_id' => $category->id,
            'code' => 'unrequested_stage',
            'name' => 'مرحله درخواست‌نشده',
            'prompt' => 'محرمانه',
            'credit_cost' => 1,
            'is_active' => true,
        ]);
        $requestedAt = now()->subDays(2)->setTime(9, 10, 11);
        GenerationRequest::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $source->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $requestedOutput->id,
            'status' => 'queued',
            'idempotency_key' => 'requested-dashboard-stage',
            'requested_at' => $requestedAt,
        ]);
        $unrequested = GenerationRequest::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_source_image_id' => $source->id,
            'ai_model_category_id' => $category->id,
            'ai_model_category_output_id' => $unrequestedOutput->id,
            'status' => 'failed',
            'idempotency_key' => 'unrequested-dashboard-stage',
            'requested_at' => null,
        ]);
        $unrequestedCreatedAt = now()->subDays(10)->setTime(12, 13, 14);
        $unrequested->timestamps = false;
        $unrequested->forceFill([
            'created_at' => $unrequestedCreatedAt,
            'updated_at' => $unrequestedCreatedAt,
        ])->saveQuietly();

        $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee($requestedOutput->name)
            ->assertSee($unrequestedOutput->name)
            ->assertSee(__('ai.generation.statuses.queued'))
            ->assertSee(JalaliDate::toJalaliDateTime($requestedAt))
            ->assertDontSee(__('ai.generation.statuses.failed'))
            ->assertDontSee(JalaliDate::toJalaliDateTime($unrequestedCreatedAt));

        $failedRequestedAt = now()->subDay()->setTime(15, 16, 17);
        $unrequested->timestamps = true;
        $unrequested->forceFill(['requested_at' => $failedRequestedAt])->save();

        $this->actingAs($clinicAdmin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/crm/leads/'.$lead->id)
            ->assertOk()
            ->assertSee(__('ai.generation.statuses.failed'))
            ->assertSee(JalaliDate::toJalaliDateTime($failedRequestedAt));
    }

    public function test_super_admin_dashboard_starts_on_management_overview_and_can_select_one(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.dashboard'))
            ->assertSee(__('system.metrics.users'))
            ->assertDontSee(__('payments.active_subscription'))
            ->assertSessionMissing('admin_active_tenant_id');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/tenant', ['tenant_id' => $tenant->id])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.dashboard'))
            ->assertSee(__('system.metrics.plans'))
            ->assertDontSee(__('payments.active_subscription'));
    }

    public function test_normal_user_dashboard_is_scoped_to_own_tenant_data(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $user = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $leadA = $this->createLead($tenantA, '09120000001', 'Tenant A Lead');
        $leadB = $this->createLead($tenantB, '09120000002', 'Tenant B Lead');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee($leadA->full_name)
            ->assertSee('data-table-action-menu', false)
            ->assertSee(__('leads.details'))
            ->assertDontSee($leadB->full_name);
    }

    public function test_persian_language_files_contain_required_keys(): void
    {
        foreach (['dashboard', 'menu', 'auth', 'plans', 'payments', 'subscriptions', 'credits', 'services', 'leads', 'crm', 'sms', 'settings', 'system', 'permissions', 'reports', 'messages', 'ai_models', 'ai', 'validation'] as $file) {
            $this->assertFileExists(base_path('lang/fa/'.$file.'.php'));
        }

        $this->assertSame('داشبورد', __('menu.groups.dashboard'));
        $this->assertSame('پیشخوان', __('menu.items.dashboard'));
        $this->assertSame('حالت شب', __('dashboard.theme.menu_item'));
        $this->assertSame('باز کردن منوی پنل', __('dashboard.mobile_menu.open'));
        $this->assertSame('امنیت', __('menu.groups.users_access'));
        $this->assertSame('مالی', __('menu.groups.plans_payments'));
        $this->assertSame('قالب‌ها', __('menu.items.landing_templates'));
        $this->assertSame('کاربردهای هوش مصنوعی', __('menu.items.ai_model_categories'));
        $this->assertSame('نقش‌های مدیریتی', __('menu.items.roles'));
        $this->assertSame('نقش‌های مدیریتی', __('system.roles_title'));
        $this->assertSame('گزارش مصرف اعتبار', __('menu.items.credit_usage_report'));
        $this->assertSame(__('menu.items.billing'), __('payments.title'));
        $this->assertSame(__('menu.items.payment_gateways'), __('system.payment_gateways_title'));
        $this->assertSame(__('menu.items.user_reports'), __('reports.title'));
        $this->assertSame(__('menu.items.management_reports'), __('reports.management_title'));
        $this->assertSame(__('menu.items.landing_templates'), __('landing_templates.title'));
        $this->assertSame(__('menu.items.system_notifications'), __('notifications.title'));
        $this->assertSame(__('menu.items.ai_model_categories'), __('ai_models.categories.title'));
        $this->assertSame(__('menu.items.ai_models'), __('ai_models.title'));
        $this->assertSame(__('menu.items.system_sms_gateways'), __('system.sms_gateways_title'));
        $this->assertSame('نیاز به پیگیری', __('leads.statuses.needs_follow_up'));
        $this->assertSame('لغو شده', __('leads.statuses.canceled'));
        $this->assertSame('تماس تلفنی', __('crm.channels.phone'));
        $this->assertSame('نمای کلی سیستم', __('system.dashboard_title'));
        $this->assertSame('سوپر ادمین', __('system.roles.super_admin'));
        $this->assertSame('مسدود', __('system.statuses.blocked'));
        $this->assertSame('برای دسترسی به این بخش باید پلن فعال داشته باشید.', __('permissions.plan_required'));
        $this->assertSame('تایید عملیات', __('messages.confirm_action'));
        $this->assertSame('حذف', __('messages.delete'));
        $this->assertSame('ویرایش فیلتر', __('messages.edit_filters'));
        $this->assertSame('فیلترهای فعال', __('messages.active_filters'));
        $this->assertSame('شماره موبایل باید فقط شامل ۱۱ رقم باشد.', __('auth.invalid_phone'));
        $this->assertSame('کد تایید باید فقط شامل ۶ رقم باشد.', __('auth.invalid_code'));
        $this->assertSame('جستجوی نام، توضیح یا واحد پلن', __('plans.search_placeholder'));
        $this->assertSame('مدل‌های هوش مصنوعی', __('ai_models.title'));
        $this->assertSame('کاشت مو', __('ai_models.usage_categories.hair_transplant'));
        $this->assertSame('کاشت ابرو', __('services.keys.eyebrow_transplant'));
        $this->assertSame('اعتبار سرویس :service به پایان رسیده است. برای ادامه استفاده، بسته افزایش اعتبار تهیه کنید.', __('credits.errors.finished'));
    }

    public function test_admin_javascript_is_split_into_focused_modules(): void
    {
        $entrypoint = resource_path('js/app.js');
        $adminIndex = resource_path('js/admin/index.js');
        $actionMenus = resource_path('js/admin/action-menus.js');
        $modals = resource_path('js/admin/modals.js');
        $confirmationModal = resource_path('js/admin/confirmation-modal.js');
        $jalaliDatepickers = resource_path('js/admin/jalali-datepickers.js');
        $mobileMenu = resource_path('js/admin/mobile-menu.js');
        $responsiveControls = resource_path('js/admin/responsive-controls.js');
        $theme = resource_path('js/admin/theme.js');
        $leadImageGallery = resource_path('js/admin/lead-image-gallery.js');
        $leadProcessingStyles = resource_path('css/components/lead-processing.css');
        $leadProcessingView = resource_path('views/admin/leads/partials/processing-history.blade.php');

        foreach ([$entrypoint, $adminIndex, $actionMenus, $modals, $confirmationModal, $jalaliDatepickers, $mobileMenu, $responsiveControls, $theme, $leadImageGallery, $leadProcessingStyles, $leadProcessingView] as $path) {
            $this->assertFileExists($path);
        }

        $this->assertStringContainsString("import { bootAdmin } from './admin/index.js';", file_get_contents($entrypoint));
        $this->assertStringContainsString("import { bootActionMenus, closeActionMenus } from './action-menus.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootAdminTheme } from './theme.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootMobileMenu, closeMobileMenu } from './mobile-menu.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootResponsiveControls } from './responsive-controls.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootLeadImageGallery, closeLeadImageGallery } from './lead-image-gallery.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString('export const closeActionMenus', file_get_contents($actionMenus));
        $this->assertStringContainsString('export const closeModal', file_get_contents($modals));
        $this->assertStringContainsString('preserveScrollPosition', file_get_contents($modals));
        $this->assertStringContainsString('const modalTriggerSelector', file_get_contents($modals));
        $this->assertStringContainsString('event.preventDefault();'.PHP_EOL.'        openModal(trigger.dataset.adminModalTrigger);', file_get_contents($modals));
        $this->assertStringContainsString('export const bootConfirmationModal', file_get_contents($confirmationModal));
        $this->assertStringContainsString('data-jdp-init-date', file_get_contents($jalaliDatepickers));
        $this->assertStringNotContainsString("initDate: 'attr'", file_get_contents($jalaliDatepickers));
        $this->assertStringContainsString('plusIconHtml', file_get_contents($jalaliDatepickers));
        $this->assertStringContainsString('minusIconHtml', file_get_contents($jalaliDatepickers));
        $this->assertStringContainsString('export const bootMobileMenu', file_get_contents($mobileMenu));
        $this->assertStringContainsString('export const bootResponsiveControls', file_get_contents($responsiveControls));
        $this->assertStringContainsString('localStorage.setItem', file_get_contents($theme));
        $this->assertStringContainsString('export const bootLeadImageGallery', file_get_contents($leadImageGallery));
        $this->assertStringContainsString("event.key === 'ArrowRight'", file_get_contents($leadImageGallery));
        $this->assertStringContainsString("event.key === 'ArrowLeft'", file_get_contents($leadImageGallery));
        $this->assertStringContainsString("modal.addEventListener('touchstart'", file_get_contents($leadImageGallery));
        $this->assertStringContainsString('grid-template-columns: repeat(4, minmax(0, 1fr));', file_get_contents($leadProcessingStyles));
        $this->assertStringContainsString('data-processing-source-tile', file_get_contents($leadProcessingView));
        $this->assertStringContainsString('data-processing-result-tile', file_get_contents($leadProcessingView));
        $this->assertStringNotContainsString('$generation->output?->description', file_get_contents($leadProcessingView));
    }

    public function test_shared_table_filter_sort_and_pagination_components_render_consistently(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/security/users?status=active&sort=name&direction=asc&per_page=100')
            ->assertOk()
            ->assertSee(__('messages.search'))
            ->assertSee('admin-list-filters-bar', false)
            ->assertSee('admin-list-primary-filter', false)
            ->assertSee('data-menu-icon="search"', false)
            ->assertSee('data-menu-icon="filter"', false)
            ->assertSee(__('messages.edit_filters'))
            ->assertSee(__('messages.active_filters'))
            ->assertSee(__('messages.remove_filters'))
            ->assertSee('data-jalali-date', false)
            ->assertSee('data-menu-icon="calendar"', false)
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('data-menu-icon="arrow-up"', false)
            ->assertSee('h-3.5 w-3.5', false)
            ->assertSee('data-menu-icon="chevron-right"', false)
            ->assertSee('data-menu-icon="chevron-left"', false)
            ->assertSee(__('messages.pagination_summary', ['from' => '1', 'to' => '7', 'total' => '7']))
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('admin-mobile-list', false)
            ->assertSee('admin-mobile-list-card', false)
            ->assertSee('left-0 z-90', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/payment-gateways?status=enabled&sort=provider_code&direction=asc')
            ->assertOk()
            ->assertSee(__('messages.edit_filters'))
            ->assertSee(__('system.status').': '.__('system.statuses.enabled'))
            ->assertSee('data-menu-icon="arrow-up"', false);

        $this->assertStringContainsString(
            'window.jalaliDatepicker.startWatch',
            file_get_contents(resource_path('js/admin/jalali-datepickers.js'))
        );
    }

    public function test_admin_logos_have_light_and_dark_variants(): void
    {
        $lightLogo = public_path('theme/admin-logo-light.svg');
        $darkLogo = public_path('theme/admin-logo-dark.svg');
        $neutralAvatar = public_path('theme/admin-user-avatar-neutral.svg');

        $this->assertFileExists($lightLogo);
        $this->assertFileExists($darkLogo);
        $this->assertFileExists($neutralAvatar);
        $lightLogoSvg = file_get_contents($lightLogo);
        $darkLogoSvg = file_get_contents($darkLogo);

        $this->assertStringContainsString('#0F172A', $lightLogoSvg);
        $this->assertStringContainsString('#14B8A6', $lightLogoSvg);
        $this->assertStringContainsString('#8B5CF6', $lightLogoSvg);
        $this->assertStringContainsString('#FFFFFF', $darkLogoSvg);
        $this->assertStringContainsString('#14B8A6', $darkLogoSvg);
        $this->assertStringContainsString('#8B5CF6', $darkLogoSvg);
        $this->assertStringContainsString('#A78BFA', $darkLogoSvg);
        $this->assertStringNotContainsString('#0F172A', $darkLogoSvg);
        $neutralAvatarSvg = file_get_contents($neutralAvatar);
        $this->assertStringContainsString('<svg', $neutralAvatarSvg);
        $this->assertStringContainsString('fill="#e4e6e9"', $neutralAvatarSvg);
        $dashboardCss = file_get_contents(resource_path('css/app.css'));
        $mobileCss = file_get_contents(resource_path('css/layouts/admin-mobile.css'));

        $this->assertStringContainsString('--admin-brand-start: #8b5cf6;', $dashboardCss);
        $this->assertStringContainsString('--admin-brand-end: #14b8a6;', $dashboardCss);
        $this->assertStringContainsString('--admin-brand-gradient: linear-gradient(135deg, #7c3aed 0%, #0f766e 100%);', $dashboardCss);
        $this->assertStringContainsString('--admin-header: #1a1a1f;', $dashboardCss);
        $this->assertStringContainsString('--admin-user-menu-bg: #1d1d22;', $dashboardCss);
        $this->assertStringContainsString('rgba(139, 92, 246, 0.12)', $dashboardCss);
        $this->assertStringContainsString('rgba(20, 184, 166, 0.08)', $dashboardCss);
        $this->assertStringNotContainsString('rgba(91, 141, 127, 0.11)', $dashboardCss);
        $this->assertStringNotContainsString('rgba(151, 132, 112, 0.05)', $dashboardCss);
        $this->assertStringNotContainsString('--admin-header: #0f172a;', $dashboardCss);
        $this->assertStringContainsString('border: 0;', $dashboardCss);
        $this->assertStringContainsString('border-radius: 999px;', $dashboardCss);
        $this->assertStringContainsString('background: transparent;', $dashboardCss);
        $this->assertStringNotContainsString('admin-user-menu-trigger-hover-bg', $dashboardCss);
        $this->assertStringNotContainsString('.admin-user-menu-trigger:hover', $dashboardCss);
        $this->assertStringContainsString('background: var(--admin-user-menu-bg);', $dashboardCss);
        $this->assertStringContainsString('.admin-breadcrumbs', $dashboardCss);
        $this->assertStringNotContainsString('.admin-breadcrumbs-inner', $dashboardCss);
        $this->assertStringNotContainsString('.admin-breadcrumbs-nav', $dashboardCss);
        $this->assertStringNotContainsString('padding-block: 0 0.75rem;', $dashboardCss);
        $this->assertStringContainsString('overflow-x: auto;', $dashboardCss);
        $this->assertStringContainsString('flex-wrap: nowrap;', $dashboardCss);
        $this->assertStringContainsString('min-width: max-content;', $dashboardCss);
        $this->assertStringNotContainsString('.admin-breadcrumbs::before', $dashboardCss);
        $this->assertStringNotContainsString('.admin-breadcrumbs-link::after', $dashboardCss);
        $this->assertStringNotContainsString("html[data-admin-theme='dark'] .admin-breadcrumbs {", $dashboardCss);
        $this->assertStringContainsString('[data-admin-table-container] thead tr:first-child > th:first-child', $dashboardCss);
        $this->assertStringContainsString('border-start-start-radius: calc(0.5rem - 1px);', $dashboardCss);
        $this->assertStringContainsString('[data-admin-table-container] thead tr:first-child > th:last-child', $dashboardCss);
        $this->assertStringContainsString('border-start-end-radius: calc(0.5rem - 1px);', $dashboardCss);
        $this->assertStringContainsString('padding: 1.25rem !important;', $mobileCss);
        $this->assertStringContainsString('jdp-container .jdp-icon-plus', $dashboardCss);
        $this->assertStringContainsString('jdp-container .jdp-icon-minus.not-in-range', $dashboardCss);
        $this->assertStringContainsString('.admin-mobile-menu-button', $mobileCss);
        $this->assertStringContainsString('border: 0;', $mobileCss);
        $this->assertStringContainsString('background: transparent;', $mobileCss);
        $this->assertStringContainsString('color: var(--admin-bar-muted);', $mobileCss);
        $this->assertStringContainsString('.admin-topbar .bg-amber-50', $dashboardCss);
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

    private function deactivateSeededClinicPlan(string $status = 'expired'): void
    {
        $tenant = Tenant::query()->where('slug', 'arya')->first();

        if (! $tenant) {
            return;
        }

        Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->update([
                'status' => $status,
                'ends_at' => now()->subMinute(),
            ]);
    }

    private function activateSubscription(User $user): Subscription
    {
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            $tenant = $this->createTenant('Clinic For Subscription', 'clinic-sub-'.$user->id, 'clinic-sub-'.$user->id.'.hairvision-ai.test');
            $tenant->users()->attach($user->id, ['role' => $user->role, 'status' => 'active']);
        }

        $plan = Plan::query()->create([
            'name' => 'پلن تست',
            'description' => 'پلن تست',
            'plan_type' => 'base_subscription',
            'service_key' => 'hair_transplant',
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => 30,
            'usage_limit' => 100,
            'status' => 'active',
        ]);

        $plan->serviceEntitlements()->create([
            'service_key' => 'hair_transplant',
            'credit_amount' => 100,
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => 100,
            'usage_used' => 0,
        ]);

        $entitlement = SubscriptionServiceEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'service_key' => 'hair_transplant',
            'source_type' => 'base_plan',
            'source_id' => $plan->id,
            'included_credit' => 100,
            'purchased_credit' => 0,
            'used_credit' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        CreditTransaction::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_service_entitlement_id' => $entitlement->id,
            'service_key' => 'hair_transplant',
            'type' => 'grant',
            'amount' => 100,
            'balance_after' => 100,
        ]);

        return $subscription;
    }

    private function fakeZarinpalPayments(string ...$authorities): void
    {
        $requestSequence = Http::sequence();
        $verifySequence = Http::sequence();

        foreach (array_values($authorities) as $index => $authority) {
            $requestSequence->push([
                'data' => [
                    'code' => 100,
                    'message' => 'Success',
                    'authority' => $authority,
                ],
                'errors' => [],
            ]);

            $verifySequence->push([
                'data' => [
                    'code' => 100,
                    'message' => 'Verified',
                    'ref_id' => 'REF-'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT),
                ],
                'errors' => [],
            ]);
        }

        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => $requestSequence,
            'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' => $verifySequence,
        ]);
    }

    private function completeInvoicePayment(User $user, Invoice $invoice, string $authority, string $method = 'post'): Payment
    {
        $payUrl = '/admin/finance/billing/invoices/'.$invoice->id.'/pay';
        $payRequest = $this->actingAs($user)->forHost('hairvision-ai.test');
        $payResponse = strtolower($method) === 'get'
            ? $payRequest->get($payUrl)
            : $payRequest->post($payUrl);

        $payResponse->assertStatus(303);
        $payResponse->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);

        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $this->assertSame($authority, $payment->authority);
        $this->assertSame('pending', $invoice->refresh()->status);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/finance/billing/payments/'.$payment->id.'/verify?Authority='.$authority.'&Status=OK')
            ->assertStatus(303)
            ->assertRedirect(route('admin.billing.result', $payment));

        return $payment->refresh();
    }

    private function forHost(string $host): self
    {
        $this->app['url']->forceRootUrl('http://'.$host);

        return $this->withServerVariables([
            'HTTP_HOST' => $host,
            'SERVER_NAME' => $host,
        ]);
    }
}
