<?php

namespace Tests\Feature;

use App\Actions\Sms\ResolveUserSmsProviderAction;
use App\Exceptions\Sms\SmsProviderNotAvailableException;
use App\Models\SmsDeliveryLog;
use App\Models\SmsProviderAccount;
use App\Models\SmsProviderType;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\Sms\SmsManager;
use Database\Seeders\SmsProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sms_provider_seeder_creates_vendor_types_and_disabled_system_accounts_idempotently(): void
    {
        $this->seed(SmsProviderSeeder::class);
        $this->seed(SmsProviderSeeder::class);

        $this->assertSame(4, SmsProviderType::query()->count('*'));
        $this->assertSame(5, SmsProviderAccount::query()->where('scope', 'system')->count('*'));
        $this->assertSame(0, SmsProviderAccount::query()->where('scope', 'system')->where('is_active', true)->count('*'));

        $this->assertDatabaseHas('sms_provider_types', ['key' => 'ippanel', 'name' => 'IPPanel']);
        $this->assertDatabaseHas('sms_provider_accounts', ['name' => 'IPPanel سیستم - ارسال معمولی']);
        $this->assertDatabaseHas('sms_provider_accounts', ['name' => 'IPPanel سیستم - کد تایید']);
    }

    public function test_super_admin_can_create_system_provider_and_credentials_are_encrypted_and_masked(): void
    {
        [$tenant, $superAdmin] = $this->dashboardUser('super_admin');
        $type = $this->providerType('kavenegar');

        $this->actingAs($superAdmin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/sms/providers', [
                'sms_provider_type_id' => $type->id,
                'scope' => 'system',
                'name' => 'KaveNegar System',
                'credentials' => ['api_key' => 'secret-system-key'],
                'is_active' => '1',
                'is_default' => '1',
            ])
            ->assertRedirect();

        $provider = SmsProviderAccount::query()->where('name', 'KaveNegar System')->firstOrFail();
        $rawCredentials = DB::table('sms_provider_accounts')->where('id', $provider->id)->value('credentials');

        $this->assertSame('secret-system-key', $provider->credentials['api_key']);
        $this->assertStringNotContainsString('secret-system-key', $rawCredentials);
        $this->assertSame(['api_key' => '••••••••'], $provider->maskedCredentials());

        $this->actingAs($superAdmin)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/sms/providers/'.$provider->id.'/edit')
            ->assertOk()
            ->assertDontSee('secret-system-key');
    }

    public function test_normal_user_cannot_create_system_provider_but_can_create_private_provider(): void
    {
        [$tenant, $user] = $this->dashboardUser('clinic_admin');
        $type = $this->providerType('sms_ir');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/sms/providers', [
                'sms_provider_type_id' => $type->id,
                'scope' => 'system',
                'name' => 'Bad System',
                'credentials' => ['api_key' => 'secret'],
            ])
            ->assertSessionHasErrors('scope');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/sms/providers', [
                'sms_provider_type_id' => $type->id,
                'scope' => 'user',
                'name' => 'My SMS.ir',
                'credentials' => ['api_key' => 'private-secret'],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sms_provider_accounts', [
            'name' => 'My SMS.ir',
            'scope' => 'user',
            'owner_user_id' => $user->id,
        ]);
    }

    public function test_user_cannot_edit_another_users_private_provider_or_select_it(): void
    {
        [$tenant, $owner] = $this->dashboardUser('clinic_admin', '09120000001');
        [, $other] = $this->dashboardUser('clinic_admin', '09120000002', $tenant);
        $type = $this->providerType('ippanel');
        $provider = $this->provider($type, 'Owner Provider', 'user', $owner, true);

        $this->actingAs($other)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/sms/providers/'.$provider->id.'/edit')
            ->assertForbidden();

        $this->actingAs($other)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/account/sms-settings', [
                'default_sms_provider_account_id' => $provider->id,
                'fallback_to_system' => '1',
            ])
            ->assertSessionHasErrors('default_sms_provider_account_id');
    }

    public function test_user_can_select_active_system_provider_but_not_inactive_provider(): void
    {
        [$tenant, $user] = $this->dashboardUser('clinic_admin');
        $type = $this->providerType('kavenegar');
        $inactive = $this->provider($type, 'Inactive System', 'system', null, false);
        $active = $this->provider($type, 'Active System', 'system', null, true);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/account/sms-settings', [
                'default_sms_provider_account_id' => $inactive->id,
                'fallback_to_system' => '1',
            ])
            ->assertSessionHasErrors('default_sms_provider_account_id');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/account/sms-settings', [
                'default_sms_provider_account_id' => $active->id,
                'fallback_to_system' => '1',
            ])
            ->assertRedirect(route('admin.account.sms-settings.edit'));

        $this->assertDatabaseHas('user_sms_settings', [
            'user_id' => $user->id,
            'default_sms_provider_account_id' => $active->id,
        ]);
    }

    public function test_sms_manager_falls_back_to_default_system_provider_and_logs_safe_attempt(): void
    {
        config(['sms.fake.enabled' => false]);

        [, $user] = $this->dashboardUser('clinic_admin');
        $type = $this->providerType('kavenegar');
        $provider = $this->provider($type, 'System KaveNegar', 'system', null, true, true);

        Http::fake([
            '*' => Http::response(['entries' => [['messageid' => 'provider-123']]], 200),
        ]);

        $result = app(SmsManager::class)->sendForUser($user, '09120000000', 'کد ورود HairVision: 123456', 'admin_otp');

        $this->assertSame('sent', $result->status->value);
        $this->assertNotNull($provider->refresh()->last_used_at);

        $this->assertDatabaseHas('sms_delivery_logs', [
            'user_id' => $user->id,
            'sms_provider_account_id' => $provider->id,
            'status' => 'sent',
            'message_preview' => 'OTP message',
        ]);

        $this->assertStringNotContainsString('123456', SmsDeliveryLog::query()->firstOrFail()->message_preview);
    }

    public function test_ippanel_otp_system_provider_uses_pattern_payload_and_authorization_header(): void
    {
        config(['sms.fake.enabled' => false]);

        $type = $this->providerType('ippanel');
        $this->provider($type, 'IPPanel Normal', 'system', null, true, true, ['sending_type' => 'normal']);
        $patternProvider = $this->provider($type, 'IPPanel OTP', 'system', null, true, false, [
            'sending_type' => 'pattern',
            'from_number' => '+983000505',
            'code' => 's5jh64gwzlk0jf3',
        ]);

        Http::fake([
            '*' => Http::response(['data' => ['message_id' => 'ippanel-otp-1']], 200),
        ]);

        $result = app(SmsManager::class)->sendSystem('09124248164', 'کد تایید HairVision: 385107', 'lead_otp');

        $this->assertSame('sent', $result->status->value);
        $this->assertNotNull($patternProvider->refresh()->last_used_at);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('Authorization', 'secret-key')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['sending_type'] === 'pattern'
                && $request['from_number'] === '+983000505'
                && $request['code'] === 's5jh64gwzlk0jf3'
                && $request['recipients'] === ['+989124248164']
                && $request['params'] === ['verification-code' => '385107'];
        });
    }

    public function test_provider_resolution_throws_clear_exception_when_no_provider_is_available(): void
    {
        config(['sms.fake.enabled' => false]);

        [, $user] = $this->dashboardUser('clinic_admin');
        $this->expectException(SmsProviderNotAvailableException::class);

        app(ResolveUserSmsProviderAction::class)->execute($user);
    }

    public function test_sms_dashboard_pages_load_and_logs_are_scoped_to_user(): void
    {
        [$tenant, $user] = $this->dashboardUser('clinic_admin', '09120000001');
        [, $other] = $this->dashboardUser('clinic_admin', '09120000002', $tenant);
        $type = $this->providerType('sms_ir');
        $provider = $this->provider($type, 'System SMS.ir', 'system', null, true);
        $otherPrivateProvider = $this->provider($type, 'Other Private SMS.ir', 'user', $other, true);

        $userLog = SmsDeliveryLog::create([
            'user_id' => $user->id,
            'sms_provider_account_id' => $provider->id,
            'sms_provider_type_id' => $type->id,
            'recipient_mobile' => '09120000001',
            'message_preview' => 'User message',
            'message_hash' => hash('sha256', 'User message'),
            'status' => 'sent',
        ]);

        $otherLog = SmsDeliveryLog::create([
            'user_id' => $other->id,
            'sms_provider_account_id' => $provider->id,
            'sms_provider_type_id' => $type->id,
            'recipient_mobile' => '09120000002',
            'message_preview' => 'Other message',
            'message_hash' => hash('sha256', 'Other message'),
            'status' => 'failed',
        ]);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/sms/providers')
            ->assertOk()
            ->assertSee('System SMS.ir');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/account/sms-settings')
            ->assertOk()
            ->assertSee('System SMS.ir');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/sms/logs')
            ->assertOk()
            ->assertSee('data-admin-datatable', false)
            ->assertSee('/admin/sms/logs/'.$userLog->id, false)
            ->assertDontSee($otherPrivateProvider->name)
            ->assertDontSee('Other message');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/sms/logs/'.$userLog->id)
            ->assertOk()
            ->assertSee('User message')
            ->assertDontSee('Other message');

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/sms/logs/'.$otherLog->id)
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/account/sms-settings/test-send')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sms_delivery_logs', [
            'user_id' => $user->id,
            'recipient_mobile' => $user->phone_normalized,
            'purpose' => 'test_sms',
            'status' => 'sent',
        ]);
    }

    public function test_sms_settings_are_available_from_admin_settings_page(): void
    {
        [$tenant, $user] = $this->dashboardUser('clinic_admin', '09120000001');
        $type = $this->providerType('sms_ir');
        $provider = $this->provider($type, 'System SMS.ir', 'system', null, true);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('تنظیمات پیامک')
            ->assertSee('System SMS.ir')
            ->assertSee('data-sms-provider-form', false)
            ->assertSee('admin-select', false)
            ->assertDontSee('data-searchable-select', false);

        $this->actingAs($user)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->put('/admin/account/sms-settings', [
                '_redirect_to' => 'settings',
                'default_sms_provider_account_id' => $provider->id,
                'fallback_to_system' => '1',
            ])
            ->assertRedirect(route('admin.settings'));
    }

    private function dashboardUser(string $role, string $phone = '09120000000', ?Tenant $tenant = null): array
    {
        $tenant ??= $this->createTenant();
        $user = User::factory()->create([
            'phone_normalized' => $phone,
            'role' => $role,
            'status' => 'active',
        ]);

        if (! $user->isSuperAdmin()) {
            $tenant->users()->attach($user->id, [
                'role' => $role,
                'status' => 'active',
            ]);
        }

        return [$tenant, $user];
    }

    private function createTenant(): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Clinic One',
            'slug' => 'clinic1',
            'status' => 'active',
        ]);

        TenantDomain::create([
            'tenant_id' => $tenant->id,
            'domain' => 'hairvision-ai.test',
            'type' => 'platform',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        return $tenant;
    }

    private function providerType(string $key): SmsProviderType
    {
        return SmsProviderType::query()->firstOrCreate(
            ['key' => $key],
            ['name' => $key, 'is_active' => true]
        );
    }

    private function provider(SmsProviderType $type, string $name, string $scope, ?User $owner, bool $active, bool $default = false, array $settings = []): SmsProviderAccount
    {
        return SmsProviderAccount::create([
            'sms_provider_type_id' => $type->id,
            'owner_user_id' => $owner?->id,
            'scope' => $scope,
            'name' => $name,
            'credentials' => ['api_key' => 'secret-key'],
            'settings' => $settings,
            'is_active' => $active,
            'is_default' => $default,
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
