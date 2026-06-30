<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\PaymentGateway;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Upload;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Billing\BillingService;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Sms\SmsGatewayService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_super_admin_has_all_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
        $this->assertTrue($superAdmin->hasPermission('payment_gateways.manage'));
        $this->assertSame(Permission::query()->count(), Role::query()->where('code', 'super_admin')->firstOrFail()->permissions()->count());
        $this->assertSame(1, SmsGateway::query()->system()->where('is_default', true)->count());
        $this->assertSame(1, PaymentGateway::query()->where('is_default', true)->count());
        $this->assertGreaterThan(0, SystemSetting::query()->count());
        $this->assertGreaterThan(0, Plan::query()->count());
    }

    public function test_system_management_routes_and_menu_are_permission_protected(): void
    {
        $this->seed(DatabaseSeeder::class);

        $viewer = User::query()->where('role', 'viewer')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/settings')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('مدیریت سیستم');

        $this->actingAs($superAdmin)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/system')
            ->assertOk()
            ->assertSee('مدیریت سیستم')
            ->assertSee('درگاه‌های پرداخت');
    }

    public function test_role_permission_assignment_changes_backend_access(): void
    {
        $this->seed(DatabaseSeeder::class);

        $role = Role::query()->where('code', 'viewer')->firstOrFail();
        $permission = Permission::query()->where('code', 'settings.manage')->firstOrFail();
        $viewer = User::query()->where('role', 'viewer')->firstOrFail();

        $this->assertFalse($viewer->hasPermission('settings.manage'));

        $role->permissions()->attach($permission);
        app(PermissionService::class)->forgetRole($role->code);

        $this->assertTrue($viewer->hasPermission('settings.manage'));
        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/settings')
            ->assertOk();
    }

    public function test_system_settings_are_validated_updated_and_cached(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/system/settings', [
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
    }

    public function test_lead_json_pagination_supports_standard_parameters_only(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $user = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $this->activateSubscription($user);
        $this->createLead($tenant, '09120000001', 'Alpha Lead');
        $this->createLead($tenant, '09120000002', 'Beta Lead');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->getJson('/admin/leads?per_page=1&search=Alpha&sort=full_name&direction=asc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);

        $response = $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->getJson('/admin/leads?per_page=1&page=2&search=Lead&sort=full_name&direction=desc')
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

        $tenant = Tenant::query()->where('slug', 'clinic1')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/users')
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
            ->get('/admin/users?search=09129999999')
            ->assertOk()
            ->assertSee('09129999999');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/users/create')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/users', [
                'name' => 'کاربر تست مدیریت',
                'email' => 'managed-user@example.com',
                'phone_normalized' => '09127770000',
                'role' => 'viewer',
                'status' => 'active',
                'tenant_ids' => [$tenant->id],
                'tenant_roles' => [$tenant->id => 'viewer'],
                'tenant_statuses' => [$tenant->id => 'active'],
            ])
            ->assertRedirect();

        $managedUser = User::query()->where('email', 'managed-user@example.com')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/users/'.$managedUser->id)
            ->assertOk()
            ->assertSee('کاربر تست مدیریت')
            ->assertSee(__('system.roles.viewer'))
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/users/'.$managedUser->id.'/edit')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/users/'.$managedUser->id, [
                'name' => 'کاربر تست ویرایش شده',
                'email' => 'managed-user@example.com',
                'phone_normalized' => '09127770001',
                'role' => 'editor',
                'status' => 'blocked',
                'tenant_ids' => [$tenant->id],
                'tenant_roles' => [$tenant->id => 'editor'],
                'tenant_statuses' => [$tenant->id => 'active'],
            ])
            ->assertRedirect(route('admin.users.show', $managedUser));

        $this->assertDatabaseHas('users', [
            'id' => $managedUser->id,
            'name' => 'کاربر تست ویرایش شده',
            'role' => 'editor',
            'status' => 'blocked',
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/users/'.$managedUser->id)
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);
    }

    public function test_users_without_permissions_cannot_access_user_management_actions(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $viewer = $this->createUserForTenant($tenant, 'viewer', '09121111111');
        $target = $this->createUserForTenant($tenant, 'editor', '09122222222');
        $this->activateSubscription($viewer);

        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/users')->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/users/create')->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/users/'.$target->id)->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->get('/admin/users/'.$target->id.'/edit')->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->put('/admin/users/'.$target->id, [])->assertForbidden();
        $this->actingAs($viewer)->withSession(['admin_active_tenant_id' => $tenant->id])->forHost('hairvision-ai.test')->delete('/admin/users/'.$target->id)->assertForbidden();
    }

    public function test_gateway_selection_separates_system_and_user_private_sms(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $systemGateway = SmsGateway::query()->system()->firstOrFail();
        $privateGateway = SmsGateway::create([
            'user_id' => $user->id,
            'name' => 'Private KaveNegar',
            'provider_code' => 'kavenegar',
            'owner_scope' => 'user',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'secret'],
        ]);

        $service = app(SmsGatewayService::class);

        $this->assertTrue($service->systemDefault()->is($systemGateway));
        $this->assertTrue($service->userDefault($user)->is($privateGateway));
        $this->assertTrue($service->sendSystemMessage('09120000000', 'system')->is($systemGateway));
        $this->assertTrue($service->sendUserCustomerMessage($user, '09125550000', 'customer')->is($privateGateway));
        $this->assertArrayNotHasKey('encrypted_credentials', $privateGateway->toArray());
    }

    public function test_sms_gateway_crud_masks_credentials_and_enforces_authorization(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $viewer = User::query()->where('role', 'viewer')->firstOrFail();
        $this->activateSubscription($viewer);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/sms-gateways', [
                'name' => 'درگاه تست کاوه',
                'provider_code' => 'kavenegar',
                'owner_scope' => 'system',
                'status' => 'enabled',
                'is_default' => '1',
                'api_key' => 'super-secret-api-key',
                'sender' => 'HV',
            ])
            ->assertRedirect();

        $gateway = SmsGateway::query()->where('provider_code', 'kavenegar')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/sms-gateways?search=kavenegar')
            ->assertOk()
            ->assertSee('درگاه تست کاوه')
            ->assertSee('create-system-sms-gateway-modal-toggle', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('super-secret-api-key');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/sms-gateways/'.$gateway->id)
            ->assertOk()
            ->assertSee($gateway->maskedCredential('api_key'))
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('super-secret-api-key');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/system/sms-gateways/'.$gateway->id, [
                'name' => 'درگاه تست کاوه ویرایش',
                'provider_code' => 'kavenegar',
                'owner_scope' => 'system',
                'status' => 'disabled',
                'sender' => 'HV2',
            ])
            ->assertRedirect();

        $gateway->refresh();
        $this->assertSame('disabled', $gateway->status);
        $this->assertSame('super-secret-api-key', $gateway->encrypted_credentials['api_key']);

        $this->actingAs($viewer)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/sms-gateways')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/system/sms-gateways/'.$gateway->id)
            ->assertRedirect(route('admin.system.sms-gateways.index'));

        $this->assertDatabaseMissing('sms_gateways', ['id' => $gateway->id]);
    }

    public function test_private_sms_gateway_crud_is_scoped_to_owner(): void
    {
        $tenant = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $owner = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $other = $this->createUserForTenant($tenant, 'clinic_admin', '09122222222');
        $this->activateSubscription($owner);
        $this->activateSubscription($other);

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/my-sms-gateways', [
                'name' => 'درگاه خصوصی',
                'provider_code' => 'melipayamak',
                'status' => 'enabled',
                'is_default' => '1',
                'api_key' => 'private-secret-key',
                'sender' => 'HV',
            ])
            ->assertRedirect();

        $gateway = SmsGateway::query()->where('user_id', $owner->id)->where('provider_code', 'melipayamak')->firstOrFail();

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/my-sms-gateways/'.$gateway->id)
            ->assertOk()
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee('private-secret-key');

        $this->actingAs($other)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/my-sms-gateways/'.$gateway->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/my-sms-gateways/'.$gateway->id, [
                'name' => 'درگاه خصوصی ویرایش',
                'provider_code' => 'melipayamak',
                'status' => 'disabled',
                'sender' => 'HV2',
            ])
            ->assertRedirect(route('admin.sms-gateways.show', $gateway));

        $gateway->refresh();
        $this->assertSame('disabled', $gateway->status);
        $this->assertSame('private-secret-key', $gateway->encrypted_credentials['api_key']);

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->delete('/admin/my-sms-gateways/'.$gateway->id)
            ->assertRedirect(route('admin.sms-gateways.index'));

        $this->assertDatabaseMissing('sms_gateways', ['id' => $gateway->id]);
    }

    public function test_payment_default_gateway_invoice_payment_and_plan_upgrade_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $starter = Plan::query()->firstOrFail();
        $upgrade = Plan::create([
            'name' => 'پلن ارتقا',
            'description' => 'Upgrade',
            'price' => 19900000,
            'currency' => 'IRR',
            'duration_days' => 60,
            'usage_limit' => 500,
            'status' => 'active',
        ]);

        $gatewayService = app(PaymentGatewayService::class);
        $this->assertSame('local', $gatewayService->default()?->provider_code);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/payment-gateways?search=local')
            ->assertOk()
            ->assertSee('local')
            ->assertSee('create-payment-gateway-modal-toggle', false)
            ->assertSee('data-admin-table-container', false)
            ->assertSee('mt-5 overflow-visible rounded-lg border border-zinc-100', false)
            ->assertDontSee('mt-5 overflow-x-auto rounded-lg border border-zinc-100', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('open:z-[80]', false)
            ->assertSee('z-[90]', false)
            ->assertSee('admin-confirm-modal-toggle', false)
            ->assertSee('data-admin-modal-backdrop="admin-confirm-modal"', false);

        $billing = app(BillingService::class);
        $invoice = $billing->invoiceForPlan($user, $starter);
        $payment = $billing->initiatePayment($invoice);
        $billing->verifyPayment($payment);
        $billing->verifyPayment($payment->refresh());

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->where('status', 'active')->count());

        $active = Subscription::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $active->forceFill(['usage_used' => 80])->save();

        $upgradeInvoice = $billing->invoiceForPlan($user, $upgrade);
        $upgradePayment = $billing->initiatePayment($upgradeInvoice);
        $billing->verifyPayment($upgradePayment);

        $this->assertSame('replaced', $active->refresh()->status);
        $newActive = Subscription::query()->where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $this->assertSame($upgrade->id, $newActive->plan_id);
        $this->assertSame(0, $newActive->usage_used);
        $this->assertSame(500, $newActive->usage_limit);
    }

    public function test_invoices_are_user_scoped_unless_user_can_manage_billing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $plan = Plan::query()->firstOrFail();
        $userA = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $userB = User::query()->where('role', 'editor')->firstOrFail();
        $billing = app(BillingService::class);
        $invoiceA = $billing->invoiceForPlan($userA, $plan);
        $invoiceB = $billing->invoiceForPlan($userB, $plan);

        $this->actingAs($userA)
            ->forHost('hairvision-ai.test')
            ->get('/admin/billing')
            ->assertOk()
            ->assertSee($invoiceA->number)
            ->assertDontSee($invoiceB->number);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/billing')
            ->assertOk()
            ->assertSee($invoiceA->number)
            ->assertSee($invoiceB->number);
    }

    public function test_subscriptions_expire_by_usage_or_time(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->firstOrFail();
        $billing = app(BillingService::class);
        $invoice = $billing->invoiceForPlan($user, $plan);
        $subscription = $billing->activatePlan($user, $plan, $invoice);

        $subscription->forceFill(['usage_limit' => 2, 'usage_used' => 1])->save();
        $billing->consumeUsage($user);

        $this->assertSame('expired', $subscription->refresh()->status);

        $timeExpired = $billing->activatePlan($user, $plan, $invoice);
        $timeExpired->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->assertSame(1, $billing->expireEndedSubscriptions());
        $this->assertSame('expired', $timeExpired->refresh()->status);
    }

    public function test_sidebar_icons_and_locked_menu_state_when_user_has_no_active_plan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-app-shell h-screen overflow-hidden', false)
            ->assertSee('admin-main-panel flex h-screen min-h-0 min-w-0 flex-col', false)
            ->assertSee('admin-content min-h-0 flex-1 overflow-y-auto', false)
            ->assertSee('data-admin-menu-scroll', false)
            ->assertSee('data-admin-user-menu', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('data-admin-theme-script', false)
            ->assertSee('admin-logo-light', false)
            ->assertSee('admin-logo-dark', false)
            ->assertSee('/theme/admin-logo-light.svg', false)
            ->assertSee('/theme/admin-logo-dark.svg', false)
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
            ->assertSee('data-menu-icon="package"', false)
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
            ->assertSee('data-menu-icon="package"', false)
            ->assertSee('data-menu-icon="sparkles"', false)
            ->assertSee('data-menu-item="admin.system.roles.index"', false)
            ->assertDontSee('name="tenant_id"', false);
    }

    public function test_inactive_plan_users_can_access_allowed_pages_and_restricted_routes_are_blocked(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/plans')->assertOk();
        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/billing')->assertOk();
        $this->actingAs($user)->forHost('hairvision-ai.test')->get('/admin/settings')->assertOk();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads')
            ->assertRedirect(route('admin.plans.index'));

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->getJson('/admin/leads')
            ->assertForbidden()
            ->assertSee(__('permissions.plan_required'), false);
    }

    public function test_super_admin_can_manage_plan_definitions_and_normal_user_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $normalUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($normalUser);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/plans', [
                'name' => 'پلن مدیریتی',
                'description' => 'برای تست مدیریت پلن',
                'price' => 4500000,
                'currency' => 'IRR',
                'duration_days' => 30,
                'usage_limit' => 50,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plans', ['name' => 'پلن مدیریتی']);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/plans?'.http_build_query(['search' => 'پلن مدیریتی', 'status' => 'active', 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('پلن مدیریتی')
            ->assertSee('create-plan-modal-toggle', false)
            ->assertSee('data-admin-table-container', false)
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false);

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->get('/admin/plans')
            ->assertOk()
            ->assertSee(__('plans.current_plan'))
            ->assertSee(__('plans.same_active_plan_blocked'))
            ->assertDontSee('data-admin-table-container', false);

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.plan.upgrade_plan'));

        $this->actingAs($normalUser)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/plans', [
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

    public function test_user_can_purchase_pay_and_upgrade_active_plan_without_carrying_credit(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $starter = Plan::query()->where('status', 'active')->firstOrFail();
        $upgrade = Plan::query()->create([
            'name' => 'پلن مسیر رشد',
            'description' => 'پلن ارتقا برای تست مسیر پرداخت',
            'price' => 14900000,
            'currency' => 'IRR',
            'duration_days' => 60,
            'usage_limit' => 300,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$starter->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $invoice = $user->invoices()->where('plan_id', $starter->id)->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/billing/invoices/'.$invoice->id.'/pay')
            ->assertRedirect();

        $active = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $active->forceFill(['usage_used' => 80])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$upgrade->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $upgradeInvoice = $user->invoices()->where('plan_id', $upgrade->id)->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/billing/invoices/'.$upgradeInvoice->id.'/pay')
            ->assertRedirect();

        $newActive = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $this->assertSame('replaced', $active->refresh()->status);
        $this->assertSame($upgrade->id, $newActive->plan_id);
        $this->assertSame(0, $newActive->usage_used);
        $this->assertSame(300, $newActive->usage_limit);
    }

    public function test_same_active_plan_cannot_be_purchased_until_expired_or_credit_finished(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('status', 'active')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$plan->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $invoice = $user->invoices()->where('plan_id', $plan->id)->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/billing/invoices/'.$invoice->id.'/pay')
            ->assertRedirect();

        $active = $user->subscriptions()->where('status', 'active')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$plan->id.'/purchase')
            ->assertSessionHasErrors('plan');

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/plans')
            ->assertOk()
            ->assertSee(__('plans.same_active_plan_blocked'));

        $active->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$plan->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $renewedInvoice = $user->invoices()->where('plan_id', $plan->id)->where('status', 'pending')->latest('id')->firstOrFail();
        $renewedInvoice->forceFill(['status' => 'paid'])->save();
        $active->forceFill(['status' => 'expired'])->save();
        $renewed = Subscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'invoice_id' => $renewedInvoice->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => 10,
            'usage_used' => 10,
        ]);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$plan->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $this->assertSame('expired', $renewed->refresh()->status);
    }

    public function test_crm_follow_up_creation_timeline_visibility_and_tenant_scope(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
        $tenantB = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $userA = $this->createUserForTenant($tenantA, 'clinic_admin', '09121111111');
        $userB = $this->createUserForTenant($tenantB, 'clinic_admin', '09122222222');
        $this->activateSubscription($userA);
        $this->activateSubscription($userB);
        $leadA = $this->createLead($tenantA, '09120000001', 'Tenant A Lead');
        $leadB = $this->createLead($tenantB, '09120000002', 'Tenant B Lead');

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/leads/'.$leadA->id.'/follow-ups', [
                'status' => 'contacted',
                'communication_channel' => 'phone',
                'note' => 'تماس اولیه انجام شد و نیاز به پیگیری دارد.',
                'next_follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lead_notes', [
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'user_id' => $userA->id,
            'status' => 'contacted',
            'communication_channel' => 'phone',
        ]);
        $this->assertSame('contacted', $leadA->refresh()->crm_stage);

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$leadA->id)
            ->assertOk()
            ->assertSee('تماس اولیه انجام شد')
            ->assertSee(__('crm.timeline_title'));

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$leadB->id)
            ->assertForbidden();

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$leadA->id)
            ->assertForbidden();
    }

    public function test_lead_uploaded_image_displays_and_streams_only_for_authorized_user(): void
    {
        Storage::fake('local');

        $tenantA = $this->createTenant('Clinic One', 'clinic1', 'clinic1.hairvision-ai.test');
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

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id)
            ->assertOk()
            ->assertSee(route('admin.leads.uploads.show', [$lead, $upload]))
            ->assertSee(__('leads.uploads.title'));

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/uploads/'.$upload->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/uploads/'.$upload->id)
            ->assertForbidden();
    }

    public function test_super_admin_dashboard_starts_on_management_overview_and_can_select_one(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'clinic1')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.dashboard'))
            ->assertSee(__('system.metrics.users'))
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
            ->assertSee(__('system.metrics.plans'));
    }

    public function test_normal_user_dashboard_is_scoped_to_own_tenant_data(): void
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
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee($leadA->full_name)
            ->assertSee('data-table-action-menu', false)
            ->assertSee(__('leads.details'))
            ->assertDontSee($leadB->full_name);
    }

    public function test_persian_language_files_contain_required_keys(): void
    {
        foreach (['dashboard', 'menu', 'auth', 'plans', 'payments', 'leads', 'crm', 'sms', 'settings', 'system', 'permissions', 'reports', 'messages', 'ai_models', 'validation'] as $file) {
            $this->assertFileExists(base_path('lang/fa/'.$file.'.php'));
        }

        $this->assertSame('داشبورد', __('menu.groups.dashboard'));
        $this->assertSame('پیشخوان', __('menu.items.dashboard'));
        $this->assertSame('حالت شب', __('dashboard.theme.menu_item'));
        $this->assertSame('پلن‌ها و پرداخت‌ها', __('menu.groups.plans_payments'));
        $this->assertSame('نیاز به پیگیری', __('leads.crm_stages.needs_follow_up'));
        $this->assertSame('تماس تلفنی', __('crm.channels.phone'));
        $this->assertSame('مدیریت سیستم', __('system.dashboard_title'));
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
    }

    public function test_admin_javascript_is_split_into_focused_modules(): void
    {
        $entrypoint = resource_path('js/app.js');
        $adminIndex = resource_path('js/admin/index.js');
        $actionMenus = resource_path('js/admin/action-menus.js');
        $modals = resource_path('js/admin/modals.js');
        $confirmationModal = resource_path('js/admin/confirmation-modal.js');
        $theme = resource_path('js/admin/theme.js');

        foreach ([$entrypoint, $adminIndex, $actionMenus, $modals, $confirmationModal, $theme] as $path) {
            $this->assertFileExists($path);
        }

        $this->assertStringContainsString("import { bootAdmin } from './admin/index.js';", file_get_contents($entrypoint));
        $this->assertStringContainsString("import { bootActionMenus, closeActionMenus } from './action-menus.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootAdminTheme } from './theme.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString('export const closeActionMenus', file_get_contents($actionMenus));
        $this->assertStringContainsString('export const closeModal', file_get_contents($modals));
        $this->assertStringContainsString('preserveScrollPosition', file_get_contents($modals));
        $this->assertStringContainsString('export const bootConfirmationModal', file_get_contents($confirmationModal));
        $this->assertStringContainsString('localStorage.setItem', file_get_contents($theme));
    }

    public function test_shared_table_filter_sort_and_pagination_components_render_consistently(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/users?status=active&sort=name&direction=asc&per_page=100')
            ->assertOk()
            ->assertSee(__('messages.search'))
            ->assertSee('data-menu-icon="search"', false)
            ->assertSee('data-menu-icon="filter"', false)
            ->assertSee(__('messages.edit_filters'))
            ->assertSee(__('messages.active_filters'))
            ->assertSee(__('messages.remove_filters'))
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('data-menu-icon="arrow-up"', false)
            ->assertSee('data-menu-icon="chevron-right"', false)
            ->assertSee('data-menu-icon="chevron-left"', false)
            ->assertSee(__('messages.pagination_summary', ['from' => '1', 'to' => '4', 'total' => '4']))
            ->assertSee('aria-disabled="true"', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/payment-gateways?status=enabled&sort=provider_code&direction=asc')
            ->assertOk()
            ->assertSee(__('messages.edit_filters'))
            ->assertSee(__('system.status').': '.__('system.statuses.enabled'))
            ->assertSee('data-menu-icon="arrow-up"', false);
    }

    public function test_admin_logos_have_light_and_dark_variants(): void
    {
        $lightLogo = public_path('theme/admin-logo-light.svg');
        $darkLogo = public_path('theme/admin-logo-dark.svg');

        $this->assertFileExists($lightLogo);
        $this->assertFileExists($darkLogo);
        $this->assertStringContainsString('#132232', file_get_contents($lightLogo));
        $this->assertStringContainsString('#FFFFFF', file_get_contents($darkLogo));
        $this->assertStringNotContainsString('#132232', file_get_contents($darkLogo));
        $dashboardCss = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('--admin-header: #0f172a;', $dashboardCss);
        $this->assertStringContainsString('--admin-user-menu-bg: #111625;', $dashboardCss);
        $this->assertStringContainsString('background: var(--admin-user-menu-bg);', $dashboardCss);
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
}
