<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\CreditTransaction;
use App\Models\GeneratedAsset;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\ClinicUser;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Role;
use App\Models\SmsGateway;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Upload;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Billing\BillingService;
use App\Services\Payment\PaymentGatewayService;
use App\Services\Sms\SmsGatewayService;
use App\Support\JalaliDate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SystemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_and_super_admin_has_all_permissions(): void
    {
        config(['hairvision.sms.ippanel.authorization_token' => null]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $ippanelGateway = SmsGateway::query()->system()->where('provider_code', 'ippanel')->firstOrFail();
        $zarinpalGateway = PaymentGateway::query()->where('provider_code', 'zarinpal')->firstOrFail();

        $this->assertSame(1, User::query()->where('role', 'super_admin')->count());
        $this->assertTrue($superAdmin->hasPermission('payment_gateways.manage'));
        $this->assertSame(Permission::query()->count('*'), Role::query()->where('code', 'super_admin')->firstOrFail()->permissions()->count('*'));
        $this->assertSame(1, SmsGateway::query()->system()->where('is_default', true)->count());
        $this->assertDatabaseHas('sms_gateways', [
            'owner_scope' => 'system',
            'provider_code' => 'ippanel',
            'status' => 'enabled',
            'is_default' => true,
        ]);
        $this->assertSame(
            'YTIxMzVhYmMtZGRkYS00OWJhLWE2ZTItNjUzYjhkYTg5M2U0OGRiZjZjMzZiNjBkNjgxYmUwZTAwM2IwZDc1MzczMTU=',
            $ippanelGateway->encrypted_credentials['api_key'] ?? null
        );
        $this->assertSame(1, PaymentGateway::query()->where('is_default', true)->count());
        $this->assertTrue($zarinpalGateway->is_default);
        $this->assertSame('enabled', $zarinpalGateway->status);
        $this->assertSame('sandbox', $zarinpalGateway->settings_json['mode'] ?? null);
        $this->assertSame('https://sandbox.zarinpal.com', $zarinpalGateway->settings_json['sandbox_base_url'] ?? null);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $zarinpalGateway->encrypted_credentials['merchant_id'] ?? null);
        $this->assertGreaterThan(0, SystemSetting::query()->count('*'));
        $this->assertGreaterThan(0, Plan::query()->count('*'));

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $activeSubscription = Subscription::query()->where('tenant_id', $tenant->id)->where('status', 'active')->firstOrFail();
        $seededServiceCount = count(config('hairvision.ai_services', [])) ?: 1;

        $this->assertSame('پلن شروع', $activeSubscription->plan->name);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count());
        $this->assertSame(1, Invoice::query()->where('tenant_id', $tenant->id)->where('status', 'paid')->where('number', 'HV-SEED-ARIA-STARTER')->count());
        $this->assertSame(1, Payment::query()->where('tenant_id', $tenant->id)->where('status', 'paid')->where('idempotency_key', 'seed-aria-starter-plan-paid')->count());
        $this->assertSame($seededServiceCount, $activeSubscription->serviceEntitlements()->where('is_active', true)->count());
        $this->assertSame($seededServiceCount * 100, $activeSubscription->serviceEntitlements()->sum('included_credit'));
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

        $this->actingAs($superAdmin)
            ->withSession(['admin_all_tenants' => true])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
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
            ->put('/admin/system/roles/'.$role->id, [
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
            ->get('/admin/system/settings')
            ->assertForbidden();
    }

    public function test_platform_and_clinic_user_tables_are_separate_identity_boundaries(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
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
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
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

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
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
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
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
        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response(['meta' => ['status' => true]]),
        ]);

        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $systemGateway = SmsGateway::query()->system()->where('is_default', true)->firstOrFail();
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
        $this->assertTrue($service->sendSystemMessage('09217359491', 'system')->is($systemGateway));
        $this->assertTrue($service->sendUserCustomerMessage($user, '09125550000', 'customer')->is($privateGateway));

        $privateGateway->update(['status' => 'disabled']);

        $this->assertTrue($service->sendUserCustomerMessage($user, '09125550000', 'customer')->is($systemGateway));
        $this->assertArrayNotHasKey('encrypted_credentials', $privateGateway->toArray());
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

    public function test_ippanel_gateway_timeout_returns_localized_validation_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('Operation timed out.'));

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

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('sms.ippanel_timeout'));

        app(SmsGatewayService::class)->sendSystemOtp('09124248164', '385107');
    }

    public function test_ippanel_gateway_failed_response_logs_safe_context(): void
    {
        Log::spy();

        Http::fake([
            'https://edge.ippanel.com/v1/api/send' => Http::response([
                'meta' => [
                    'status' => false,
                    'message' => 'Invalid recipient 989124248164 for code 385107',
                ],
                'data' => [
                    'recipient' => '989124248164',
                    'verification-code' => '385107',
                ],
            ]),
        ]);

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
            $this->assertSame(__('sms.ippanel_send_failed'), $exception->errors()['sms_gateway'][0] ?? null);
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'IPPanel SMS gateway request failed.'
                && ($context['http_status'] ?? null) === 200
                && ($context['ippanel_status'] ?? null) === false
                && ($context['ippanel_message'] ?? null) === 'Invalid recipient [number] for code [number]'
                && ($context['sending_type'] ?? null) === 'pattern'
                && ! array_key_exists('payload', $context)
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
            ->post('/admin/system/sms-gateways', [
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
            ->get('/admin/system/sms-gateways?search=IPPanel')
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
            ->get('/admin/system/sms-gateways/'.$gateway->id)
            ->assertOk()
            ->assertSee($gateway->maskedCredential('api_key'))
            ->assertSee('data-confirm-form', false)
            ->assertDontSee('confirm(', false)
            ->assertDontSee($longToken);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/system/sms-gateways/'.$gateway->id, [
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

        SmsGateway::create([
            'user_id' => $viewer->id,
            'name' => 'پنل اختصاصی Viewer',
            'provider_code' => 'ippanel',
            'owner_scope' => 'user',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => ['api_key' => 'viewer-secret-token'],
            'settings_json' => ['from_number' => '+983000505'],
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/sms-gateways?owner_scope=user')
            ->assertOk()
            ->assertSee('پنل اختصاصی Viewer')
            ->assertSee($viewer->name)
            ->assertDontSee('viewer-secret-token');

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

    public function test_private_sms_gateway_settings_are_single_panel_and_scoped_to_owner(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $owner = $this->createUserForTenant($tenant, 'clinic_admin', '09121111111');
        $other = $this->createUserForTenant($tenant, 'clinic_admin', '09122222222');
        $this->activateSubscription($owner);
        $this->activateSubscription($other);

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('پنل پیامک اختصاصی');

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/my-sms-gateways')
            ->assertOk()
            ->assertSee('پنل پیامک اختصاصی')
            ->assertSee('درگاه پیامک IPPanel')
            ->assertDontSee('create-private-sms-gateway-modal-toggle', false)
            ->assertDontSee('data-table-action-menu', false);

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/my-sms-gateways', [
                'provider_code' => 'ippanel',
                'api_key' => 'private-secret-key',
                'from_number' => '+983000505',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.sms-gateways.index'));

        $gateway = SmsGateway::query()->where('user_id', $owner->id)->firstOrFail();

        $this->assertSame('پنل پیامک اختصاصی', $gateway->name);
        $this->assertSame('ippanel', $gateway->provider_code);
        $this->assertSame('enabled', $gateway->status);
        $this->assertTrue($gateway->is_default);
        $this->assertSame('+983000505', $gateway->settings_json['from_number']);

        $this->actingAs($owner)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/my-sms-gateways')
            ->assertOk()
            ->assertSee($gateway->maskedCredential('api_key'))
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
                'from_number' => '+983000506',
            ])
            ->assertRedirect(route('admin.sms-gateways.index'));

        $gateway->refresh();
        $this->assertSame('disabled', $gateway->status);
        $this->assertSame('private-secret-key', $gateway->encrypted_credentials['api_key']);
        $this->assertSame('+983000506', $gateway->settings_json['from_number']);
        $this->assertSame(1, SmsGateway::query()->where('user_id', $owner->id)->count());
    }

    public function test_payment_default_gateway_invoice_payment_and_plan_upgrade_are_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

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
        $this->assertSame('zarinpal', $gatewayService->default()?->provider_code);
        $this->fakeZarinpalPayments('A000000000000000000000000000000000001', 'A000000000000000000000000000000000002');

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/payment-gateways?search=zarinpal')
            ->assertOk()
            ->assertSee('zarinpal')
            ->assertSee(__('messages.zarinpal_test_payment_gateway'))
            ->assertSee('create-payment-gateway-modal-toggle', false)
            ->assertSee('data-admin-table-container', false)
            ->assertSee('mt-5 hidden overflow-visible rounded-lg border border-zinc-100 lg:block', false)
            ->assertSee('admin-mobile-list', false)
            ->assertDontSee('mt-5 overflow-x-auto rounded-lg border border-zinc-100', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('open:z-[80]', false)
            ->assertSee('z-[90]', false)
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
        $this->assertSame(500, $newActive->usage_limit);
    }

    public function test_invoices_are_user_scoped_unless_user_can_manage_billing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

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

    public function test_billing_invoices_show_direct_pay_button_and_details_modal(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('status', 'active')->firstOrFail();
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
            ->get('/admin/billing')
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('data-invoice-direct-pay="'.$invoice->id.'"', false)
            ->assertSee('invoice-details-'.$invoice->id.'-toggle', false)
            ->assertSee(__('payments.view'))
            ->assertSee(__('payments.invoice_information'))
            ->assertSee(__('payments.payment_information'))
            ->assertSee('•••• '.substr($authority, -8), false)
            ->assertDontSee($authority, false);
    }

    public function test_active_subscription_box_is_visible_only_on_clinic_admin_hosts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/billing')
            ->assertOk()
            ->assertDontSee('data-active-subscription-card', false)
            ->assertDontSee(__('payments.active_subscription'));

        $this->actingAs($user)
            ->forHost('aria.hairvision-ai.test')
            ->get('/admin/billing')
            ->assertOk()
            ->assertSee('data-active-subscription-card', false)
            ->assertSee(__('payments.active_subscription'));
    }

    public function test_subscriptions_expire_by_time_not_service_usage(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->firstOrFail();
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
        $this->deactivateSeededClinicPlan('cancelled');

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

        $plansResponse = $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/plans?'.http_build_query(['search' => 'پلن مدیریتی', 'status' => 'active', 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('پلن مدیریتی')
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

    public function test_super_admin_can_manage_clinics_and_normal_user_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $normalUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($normalUser);

        $this->assertTrue($superAdmin->hasPermission('tenants.list'));
        $this->assertTrue($superAdmin->hasPermission('tenants.create'));
        $this->assertTrue($superAdmin->hasPermission('tenants.edit'));
        $this->assertTrue($superAdmin->hasPermission('tenants.delete'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/clinics')
            ->assertOk()
            ->assertSee(__('clinics.create'))
            ->assertSee(__('menu.items.clinics'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics', [
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

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/clinics/'.$clinic->id.'/edit')
            ->assertOk()
            ->assertSee('test-clinic.hairvision-ai.test')
            ->assertDontSee(__('clinics.manage_models'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/clinics/'.$clinic->id)
            ->assertOk()
            ->assertSee('test-clinic.hairvision-ai.test')
            ->assertSee(__('clinics.manage_models'))
            ->assertSee(__('clinics.assign_plan'))
            ->assertSee(__('clinics.leads_count'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/clinics/'.$clinic->id, [
                '_form' => 'edit_clinic',
                'name' => 'کلینیک تست ویرایش',
                'slug' => 'test-clinic-updated',
                'status' => 'inactive',
                'domain' => 'updated-clinic.hairvision-ai.test',
                'domain_type' => 'tenant_subdomain',
                'domain_status' => 'inactive',
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
            ->post('/admin/clinics', [
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
            ->get('/admin/clinics')
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/clinics/'.$clinic->id)
            ->assertRedirect(route('admin.clinics.index'));

        $this->assertDatabaseMissing('tenants', ['id' => $clinic->id]);
    }

    public function test_super_admin_can_view_and_assign_clinic_plans_from_clinic_list(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinic = Tenant::query()->where('slug', 'aria')->firstOrFail();
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
            'usage_limit' => 250,
            'status' => 'active',
        ]);
        $upgrade->serviceEntitlements()->create([
            'service_key' => 'hair_transplant',
            'credit_amount' => 250,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/clinics')
            ->assertOk()
            ->assertSee(__('clinics.plan_status'))
            ->assertSee($currentSubscription->plan->name)
            ->assertSee(__('subscriptions.remaining_credit'))
            ->assertDontSee(__('clinics.assign_plan'))
            ->assertDontSee(__('clinics.manage_models'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/clinics/'.$clinic->id)
            ->assertOk()
            ->assertSee(__('clinics.assign_plan'))
            ->assertSee(__('clinics.manage_models'))
            ->assertSee(__('clinics.leads_count'))
            ->assertSee(__('clinics.models_count'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$clinic->id.'/plans', [
                '_form' => 'assign_clinic_plan_'.$clinic->id,
                'plan_id' => $upgrade->id,
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

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/clinics/'.$clinic->id.'/invoices/'.$invoice->id.'/approve')
            ->assertRedirect(route('admin.clinics.show', $clinic));

        $this->assertSame('replaced', $currentSubscription->refresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $clinic->id,
            'user_id' => null,
            'plan_id' => $upgrade->id,
            'status' => 'active',
            'usage_limit' => 250,
        ]);
        $this->assertNotNull($invoice->refresh()->activated_at);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$clinic->id.'/invoices', [
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

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/clinics/'.$clinic->id.'/invoices/'.$manualInvoice->id, [
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
            ->post('/admin/clinics/'.$clinic->id.'/sms-gateway', [
                '_form' => 'clinic_sms_gateway',
                'provider_code' => 'local',
                'api_key' => 'clinic-secret-key',
                'from_number' => '10002000',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.clinics.show', $clinic))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sms_gateways', [
            'tenant_id' => $clinic->id,
            'owner_scope' => 'tenant',
            'provider_code' => 'local',
            'status' => 'enabled',
            'is_default' => true,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/clinics')
            ->assertOk()
            ->assertSee('پلن ویژه کلینیک‌ها')
            ->assertSee(__('payments.subscription_statuses.active'));

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $clinic->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$clinic->id.'/plans', [
                '_form' => 'assign_clinic_plan_'.$clinic->id,
                'plan_id' => $upgrade->id,
            ])
            ->assertForbidden();
    }

    public function test_user_can_purchase_pay_and_upgrade_active_plan_without_carrying_credit(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->deactivateSeededClinicPlan();
        $this->fakeZarinpalPayments('A000000000000000000000000000000000101', 'A000000000000000000000000000000000102');

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

        $invoice = $user->invoices()->where('plan_id', $starter->id)->where('status', 'pending')->latest('id')->firstOrFail();
        $payment = $this->completeInvoicePayment($user, $invoice, 'A000000000000000000000000000000000101', 'get');
        $this->assertSame('paid', $payment->refresh()->status);

        $active = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $active->forceFill(['usage_used' => 80])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$upgrade->id.'/purchase')
            ->assertRedirect(route('admin.billing.index'));

        $upgradeInvoice = $user->invoices()->where('plan_id', $upgrade->id)->firstOrFail();
        $this->completeInvoicePayment($user, $upgradeInvoice, 'A000000000000000000000000000000000102');

        $newActive = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $this->assertSame('replaced', $active->refresh()->status);
        $this->assertSame($upgrade->id, $newActive->plan_id);
        $this->assertSame(0, $newActive->usage_used);
        $this->assertSame(300, $newActive->usage_limit);
    }

    public function test_same_active_base_plan_cannot_be_purchased_until_expired(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->fakeZarinpalPayments('A000000000000000000000000000000000201', 'A000000000000000000000000000000000202');

        $user = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $plan = Plan::query()->where('status', 'active')->firstOrFail();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$plan->id.'/purchase')
            ->assertSessionHasErrors('plan');

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
        $this->completeInvoicePayment($user, $renewedInvoice, 'A000000000000000000000000000000000201');

        $renewed = $user->subscriptions()->where('status', 'active')->firstOrFail();
        $renewed->serviceEntitlements()->firstOrFail()->forceFill(['used_credit' => $renewed->serviceEntitlements()->firstOrFail()->totalCredit()])->save();

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->post('/admin/plans/'.$plan->id.'/purchase')
            ->assertSessionHasErrors('plan');

        $this->assertSame('active', $renewed->refresh()->status);
    }

    public function test_crm_follow_up_creation_timeline_visibility_and_tenant_scope(): void
    {
        $tenantA = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
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
            ->post('/admin/leads/'.$leadA->id.'/follow-ups', [
                'status' => 'contacted',
                'communication_channel' => 'phone',
                'note' => 'تماس اولیه انجام شد و نیاز به پیگیری دارد.',
                'next_follow_up_at' => $nextFollowUpAt,
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
        $this->assertDatabaseHas('lead_notes', [
            'tenant_id' => $tenantA->id,
            'lead_id' => $leadA->id,
            'next_follow_up_at' => $nextFollowUpAt,
        ]);

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$leadA->id)
            ->assertOk()
            ->assertSee('تماس اولیه انجام شد')
            ->assertSee(__('crm.timeline_title'))
            ->assertSee('data-jalali-datetime', false)
            ->assertSee('data-menu-icon="calendar"', false);

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

        $tenantA = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
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
            ->get('/admin/leads/'.$lead->id)
            ->assertOk()
            ->assertSee(route('admin.leads.uploads.show', [$lead, $upload]))
            ->assertSee(route('admin.leads.assets.show', [$lead, $asset]))
            ->assertSee(__('leads.uploads.title'));

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/uploads/'.$upload->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($userA)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/assets/'.$asset->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/uploads/'.$upload->id)
            ->assertForbidden();

        $this->actingAs($userB)
            ->withSession(['admin_active_tenant_id' => $tenantB->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/leads/'.$lead->id.'/assets/'.$asset->id)
            ->assertForbidden();
    }

    public function test_super_admin_dashboard_starts_on_management_overview_and_can_select_one(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
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
        $tenantA = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
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

        foreach ([$entrypoint, $adminIndex, $actionMenus, $modals, $confirmationModal, $jalaliDatepickers, $mobileMenu, $responsiveControls, $theme] as $path) {
            $this->assertFileExists($path);
        }

        $this->assertStringContainsString("import { bootAdmin } from './admin/index.js';", file_get_contents($entrypoint));
        $this->assertStringContainsString("import { bootActionMenus, closeActionMenus } from './action-menus.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootAdminTheme } from './theme.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootMobileMenu, closeMobileMenu } from './mobile-menu.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString("import { bootResponsiveControls } from './responsive-controls.js';", file_get_contents($adminIndex));
        $this->assertStringContainsString('export const closeActionMenus', file_get_contents($actionMenus));
        $this->assertStringContainsString('export const closeModal', file_get_contents($modals));
        $this->assertStringContainsString('preserveScrollPosition', file_get_contents($modals));
        $this->assertStringContainsString('export const bootConfirmationModal', file_get_contents($confirmationModal));
        $this->assertStringContainsString('data-jdp-init-date', file_get_contents($jalaliDatepickers));
        $this->assertStringContainsString("initDate: 'attr'", file_get_contents($jalaliDatepickers));
        $this->assertStringContainsString('export const bootMobileMenu', file_get_contents($mobileMenu));
        $this->assertStringContainsString('export const bootResponsiveControls', file_get_contents($responsiveControls));
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
            ->assertSee(__('messages.pagination_summary', ['from' => '1', 'to' => '4', 'total' => '4']))
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('admin-mobile-list', false)
            ->assertSee('admin-mobile-list-card', false)
            ->assertSee('left-0 z-[90]', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/payment-gateways?status=enabled&sort=provider_code&direction=asc')
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
        $this->assertStringContainsString('#132232', file_get_contents($lightLogo));
        $this->assertStringContainsString('#FFFFFF', file_get_contents($darkLogo));
        $this->assertStringNotContainsString('#132232', file_get_contents($darkLogo));
        $neutralAvatarSvg = file_get_contents($neutralAvatar);
        $this->assertStringContainsString('<svg', $neutralAvatarSvg);
        $this->assertStringContainsString('fill="#e4e6e9"', $neutralAvatarSvg);
        $dashboardCss = file_get_contents(resource_path('css/app.css'));
        $mobileCss = file_get_contents(resource_path('css/layouts/admin-mobile.css'));

        $this->assertStringContainsString('--admin-header: #0f172a;', $dashboardCss);
        $this->assertStringContainsString('--admin-user-menu-bg: #111625;', $dashboardCss);
        $this->assertStringContainsString('border: 0;', $dashboardCss);
        $this->assertStringContainsString('border-radius: 999px;', $dashboardCss);
        $this->assertStringContainsString('background: transparent;', $dashboardCss);
        $this->assertStringNotContainsString('admin-user-menu-trigger-hover-bg', $dashboardCss);
        $this->assertStringNotContainsString('.admin-user-menu-trigger:hover', $dashboardCss);
        $this->assertStringContainsString('background: var(--admin-user-menu-bg);', $dashboardCss);
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
            'status' => 'verified',
            'crm_stage' => 'new_lead',
        ]);
    }

    private function deactivateSeededClinicPlan(string $status = 'expired'): void
    {
        $tenant = Tenant::query()->where('slug', 'aria')->first();

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
        $payUrl = '/admin/billing/invoices/'.$invoice->id.'/pay';
        $payRequest = $this->actingAs($user)->forHost('hairvision-ai.test');
        $payResponse = strtolower($method) === 'get'
            ? $payRequest->get($payUrl)
            : $payRequest->post($payUrl);

        $payResponse->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$authority);

        $payment = $invoice->payments()->latest('id')->firstOrFail();
        $this->assertSame($authority, $payment->authority);
        $this->assertSame('pending', $invoice->refresh()->status);

        $this->actingAs($user)
            ->forHost('hairvision-ai.test')
            ->get('/admin/billing/payments/'.$payment->id.'/verify?Authority='.$authority.'&Status=OK')
            ->assertRedirect(route('admin.billing.index'));

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
