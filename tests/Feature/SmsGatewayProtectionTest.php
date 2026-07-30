<?php

namespace Tests\Feature;

use App\Models\SmsGateway;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the protected system debug/test SMS gateway lifecycle.
 *
 * @since 2.0.0
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
     * Ensure even a super administrator cannot edit, delete, or duplicate the protected gateway.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
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
