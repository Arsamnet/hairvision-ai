<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Sms\Adapters\LocalSmsGatewayAdapter;
use App\Services\Sms\DebugSmsGatewayAvailability;
use App\Services\Sms\SmsGatewayService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers the protected system debug/test SMS gateway lifecycle.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class SmsGatewayProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure seeding activates only the protected local system gateway and no demo-clinic gateway.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function test_seeder_keeps_only_debug_sms_active_and_omits_demo_clinic_gateway(): void
    {
        $this->seed(DatabaseSeeder::class);

        $demoTenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        $localGateway = SmsGateway::query()
            ->system()
            ->where('provider_code', 'local')
            ->firstOrFail();

        $this->assertSame('enabled', $localGateway->status);
        $this->assertTrue($localGateway->is_default);
        $this->assertTrue($localGateway->is_protected);
        $this->assertSame(['local'], SmsGateway::query()
            ->system()
            ->enabled()
            ->pluck('provider_code')
            ->all());
        $this->assertSame(0, SmsGateway::query()
            ->where('owner_scope', 'tenant')
            ->where('tenant_id', $demoTenant->id)
            ->count());
    }

    /**
     * Ensure every environment safety condition is required before debug SMS is available.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_debug_sms_requires_non_production_debug_mode_and_explicit_env_activation(): void
    {
        $availability = app(DebugSmsGatewayAvailability::class);
        $gateway = new SmsGateway([
            'provider_code' => 'local',
            'status' => 'enabled',
        ]);

        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'hairvision.sms.debug_gateway.enabled' => true,
        ]);

        $this->assertTrue($availability->isEnabled());
        $this->assertSame('enabled', $availability->effectiveStatus($gateway));

        $gateway->status = 'disabled';

        $this->assertSame('enabled', $availability->effectiveStatus($gateway));

        config(['app.env' => 'production']);

        $this->assertFalse($availability->isEnabled());
        $this->assertSame('disabled', $availability->effectiveStatus($gateway));

        config([
            'app.env' => 'testing',
            'app.debug' => false,
        ]);

        $this->assertFalse($availability->isEnabled());

        config([
            'app.debug' => true,
            'hairvision.sms.debug_gateway.enabled' => false,
        ]);

        $this->assertFalse($availability->isEnabled());
    }

    /**
     * Ensure an unavailable debug gateway is never selected when every real gateway is disabled.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_debug_gateway_is_never_used_as_a_production_fallback(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'hairvision.sms.debug_gateway.enabled' => true,
            'hairvision.sms.default_provider' => 'local',
            'hairvision.sms.ippanel.authorization_token' => null,
        ]);

        $localGateway = SmsGateway::create([
            'name' => 'Local System',
            'provider_code' => 'local',
            'owner_scope' => 'system',
            'status' => 'enabled',
            'is_default' => true,
            'encrypted_credentials' => [],
        ]);

        SmsGateway::create([
            'name' => 'Disabled IPPanel',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'disabled',
            'is_default' => false,
            'encrypted_credentials' => ['api_key' => 'unused-token'],
        ]);

        $service = app(SmsGatewayService::class);

        $this->assertNull($service->systemDefault());
        $this->assertNull($service->systemIppanel());
        $this->assertNull($service->lastDebugSms());

        try {
            $service->sendSystemOtp('09124248164', '385107');
            $this->fail('Production must never send through the debug SMS gateway.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sms_gateway', $exception->errors());
            $this->assertNull($service->lastDebugSms());
        }

        try {
            app(LocalSmsGatewayAdapter::class)->sendOtp($localGateway, '09124248164', '385107');
            $this->fail('Production must reject direct debug adapter calls.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('sms.debug_gateway_unavailable'),
                $exception->errors()['sms_gateway'][0] ?? null
            );
        }
    }

    /**
     * Ensure the environment switch is the sole activation source for the protected gateway.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_explicit_env_switch_activates_debug_gateway_only_in_a_safe_runtime(): void
    {
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'hairvision.sms.debug_gateway.enabled' => true,
            'hairvision.sms.default_provider' => 'local',
        ]);

        $localGateway = SmsGateway::create([
            'name' => 'Local System',
            'provider_code' => 'local',
            'owner_scope' => 'system',
            'status' => 'disabled',
            'is_default' => true,
            'is_protected' => true,
            'encrypted_credentials' => [],
        ]);

        $service = app(SmsGatewayService::class);

        $this->assertTrue($service->systemDefault()?->is($localGateway));
        $this->assertTrue($service->sendSystemOtp('09124248164', '385107')->is($localGateway));
        $this->assertSame('385107', $service->lastDebugSms());

        config(['app.debug' => false]);

        $this->assertNull($service->systemDefault());
    }

    /**
     * Ensure management screens render and filter the environment-effective gateway status.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_dashboard_shows_debug_gateway_as_disabled_when_runtime_disallows_it(): void
    {
        config([
            'app.env' => 'production',
            'app.debug' => true,
            'hairvision.sms.debug_gateway.enabled' => true,
        ]);

        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $localGateway = SmsGateway::query()
            ->system()
            ->where('provider_code', 'local')
            ->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.index', absolute: false))
            ->assertOk()
            ->assertSee('data-sms-gateway-id="'.$localGateway->id.'"', false)
            ->assertSee('data-effective-status="disabled"', false)
            ->assertDontSee('<option value="local"', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.index', ['status' => 'enabled'], false))
            ->assertOk()
            ->assertDontSee($localGateway->name);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.index', ['status' => 'disabled'], false))
            ->assertOk()
            ->assertSee($localGateway->name);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.show', $localGateway, false))
            ->assertOk()
            ->assertSee('data-effective-status="disabled"', false);
    }

    /**
     * Ensure default selection follows runtime availability only for the debug gateway.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function test_default_action_is_runtime_gated_only_for_debug_gateway(): void
    {
        config([
            'app.env' => 'testing',
            'app.debug' => true,
            'hairvision.sms.debug_gateway.enabled' => true,
        ]);

        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $localGateway = SmsGateway::query()
            ->system()
            ->where('provider_code', 'local')
            ->firstOrFail();
        $realGateway = SmsGateway::create([
            'name' => 'Disabled real provider',
            'provider_code' => 'ippanel',
            'owner_scope' => 'system',
            'status' => 'disabled',
            'is_default' => false,
            'encrypted_credentials' => ['api_key' => 'unused-token'],
        ]);
        $indexRoute = route('admin.system.sms-gateways.index', absolute: false);
        $localDefaultRoute = route('admin.system.sms-gateways.default', $localGateway, false);
        $realDefaultRoute = route('admin.system.sms-gateways.default', $realGateway, false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get($indexRoute)
            ->assertOk()
            ->assertSee($localDefaultRoute, false)
            ->assertSee($realDefaultRoute, false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->from($indexRoute)
            ->patch($realDefaultRoute)
            ->assertRedirect($indexRoute)
            ->assertSessionHas('status', __('sms.default_updated'));

        $this->assertTrue($realGateway->refresh()->is_default);
        $this->assertFalse($localGateway->refresh()->is_default);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->from($indexRoute)
            ->patch($localDefaultRoute)
            ->assertRedirect($indexRoute);

        $this->assertTrue($localGateway->refresh()->is_default);
        $this->assertFalse($realGateway->refresh()->is_default);

        config(['app.env' => 'production']);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get($indexRoute)
            ->assertOk()
            ->assertDontSee($localDefaultRoute, false)
            ->assertSee($realDefaultRoute, false)
            ->assertSee('disabled="disabled" aria-disabled="true" data-table-action-item', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch($localDefaultRoute)
            ->assertForbidden();

        try {
            app(SmsGatewayService::class)->markDefault($localGateway);
            $this->fail('An unavailable debug gateway must not become the default.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('sms.debug_gateway_unavailable'),
                $exception->errors()['sms_gateway'][0] ?? null
            );
        }

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->from($indexRoute)
            ->patch($realDefaultRoute)
            ->assertRedirect($indexRoute);

        $this->assertTrue($realGateway->refresh()->is_default);
        $this->assertFalse($localGateway->refresh()->is_default);
    }

    /**
     * Ensure even a super administrator cannot edit, delete, or duplicate the protected gateway.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function test_protected_debug_gateway_rejects_admin_mutations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $localGateway = SmsGateway::query()
            ->system()
            ->where('provider_code', 'local')
            ->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.index', absolute: false))
            ->assertOk()
            ->assertSee(__('sms.protected'))
            ->assertDontSee(route('admin.system.sms-gateways.edit', $localGateway, false));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.show', $localGateway, false))
            ->assertOk()
            ->assertSee(__('sms.protected_hint'))
            ->assertDontSee(route('admin.system.sms-gateways.edit', $localGateway, false));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.sms-gateways.edit', $localGateway, false))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put(route('admin.system.sms-gateways.update', $localGateway, false), [
                'name' => 'درگاه تغییر یافته',
                'provider_code' => 'ippanel',
                'owner_scope' => 'system',
                'status' => 'disabled',
                'is_default' => false,
            ])
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.system.sms-gateways.destroy', $localGateway, false))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post(route('admin.system.sms-gateways.store', absolute: false), [
                'name' => 'درگاه محلی تکراری',
                'provider_code' => 'local',
                'owner_scope' => 'system',
                'status' => 'enabled',
                'is_default' => false,
            ])
            ->assertSessionHasErrors('provider_code');

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post(route('admin.system.sms-gateways.store', absolute: false), [
                'name' => 'درگاه محلی کلینیک',
                'provider_code' => 'local',
                'owner_scope' => 'tenant',
                'tenant_id' => $tenant->id,
                'status' => 'enabled',
                'is_default' => true,
            ])
            ->assertSessionHasErrors('provider_code');

        $localGateway->refresh();

        $this->assertSame(__('messages.local_sms_gateway'), $localGateway->name);
        $this->assertSame('local', $localGateway->provider_code);
        $this->assertSame('enabled', $localGateway->status);
        $this->assertTrue($localGateway->is_default);
        $this->assertTrue($localGateway->is_protected);
    }

    /**
     * Bind dashboard requests and generated routes to one explicit host.
     *
     * @param  string  $host  Request host.
     * @return $this Current test instance.
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
