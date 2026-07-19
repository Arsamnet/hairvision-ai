<?php

namespace Tests\Feature;

use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\ClinicNotification;
use App\Models\ClinicNotificationRecipient;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_notification_lists_show_shared_meaningful_empty_states(): void
    {
        $tenant = $this->createTenant('کلینیک بدون پیام', 'empty-inbox', 'empty-inbox.hairvision-ai.test');
        $superAdmin = $this->createSuperAdmin();
        $clinicUser = $this->createUserForTenant($tenant, 'viewer', '09120000009');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/notifications')
            ->assertOk()
            ->assertSee(__('notifications.empty_management'))
            ->assertSee('data-admin-empty-state', false)
            ->assertDontSee('> - </p>', false);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/notifications')
            ->assertOk()
            ->assertSee(__('notifications.empty_inbox'))
            ->assertSee('data-admin-empty-state', false)
            ->assertDontSee('> - </p>', false);
    }

    public function test_clinic_notifications_show_unread_badge_and_record_reader_on_open(): void
    {
        $tenantA = $this->createTenant('کلینیک آریا', 'aria', 'aria.hairvision-ai.test');
        $tenantB = $this->createTenant('کلینیک نوژا', 'noja', 'noja.hairvision-ai.test');
        $superAdmin = $this->createSuperAdmin();
        $clinicUser = $this->createUserForTenant($tenantA, 'viewer', '09120000001');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/notifications')
            ->assertOk()
            ->assertSee(__('notifications.matching_tenants_count'), false)
            ->assertDontSee('کلینیک‌های دارای شرط');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'ارسال شرطی قدیمی',
                'body_html' => '<p>این scope دیگر معتبر نیست.</p>',
                'type' => 'general',
                'importance' => 'normal',
                'target_scope' => 'filter',
                'filter_plan_state' => 'inactive',
                'send_timing' => 'immediate',
            ])
            ->assertSessionHasErrors('target_scope');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'به‌روزرسانی نسخه',
                'body_html' => '<p><strong>نسخه جدید HairVision</strong> فعال شد. <a href="https://hairvision-ai.test/changelog">جزئیات</a></p><script>alert(1)</script>',
                'type' => 'release',
                'importance' => 'important',
                'target_scope' => 'all',
                'send_timing' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $notification = ClinicNotification::query()->firstOrFail();

        $this->assertSame(2, $notification->recipients()->count());

        $recipientA = ClinicNotificationRecipient::query()
            ->where('clinic_notification_id', $notification->id)
            ->where('tenant_id', $tenantA->id)
            ->firstOrFail();

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('admin-notification-badge', false);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/notifications')
            ->assertOk()
            ->assertSee('به‌روزرسانی نسخه')
            ->assertSee(__('notifications.unread'))
            ->assertDontSee('نسخه جدید HairVision')
            ->assertDontSee(__('notifications.read_by'));

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/notifications/'.$recipientA->id)
            ->assertOk()
            ->assertSee('نسخه جدید HairVision')
            ->assertSee('https://hairvision-ai.test/changelog', false)
            ->assertDontSee('<script>', false)
            ->assertDontSee(__('notifications.read_by'));

        $this->assertNotNull($recipientA->refresh()->read_at);
        $this->assertSame($clinicUser->id, $recipientA->read_by_user_id);
        $this->assertSame($clinicUser->name, $recipientA->read_by_user_name);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/system/notifications/'.$notification->id)
            ->assertOk()
            ->assertSee($tenantA->name)
            ->assertSee($clinicUser->name)
            ->assertSee($tenantB->name)
            ->assertSee(__('notifications.unread'));

        $this->assertNull(
            ClinicNotificationRecipient::query()
                ->where('clinic_notification_id', $notification->id)
                ->where('tenant_id', $tenantB->id)
                ->firstOrFail()
                ->read_at
        );

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('admin-notification-badge', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'پیام فوری کلینیک',
                'body' => 'لطفا پیام را بررسی کنید.',
                'type' => 'system',
                'importance' => 'urgent',
                'target_scope' => 'tenant',
                'target_tenant_ids' => [$tenantA->id],
                'send_timing' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenantA->id])
            ->forHost('hairvision-ai.test')
            ->patch('/admin/notifications/read-all')
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            ClinicNotificationRecipient::query()
                ->where('tenant_id', $tenantA->id)
                ->whereNull('read_at')
                ->count()
        );
    }

    public function test_system_notifications_can_target_clinics_by_category_and_assigned_model(): void
    {
        $tenantA = $this->createTenant('کلینیک دارای مدل', 'model-clinic', 'model-clinic.hairvision-ai.test');
        $tenantB = $this->createTenant('کلینیک بدون مدل', 'plain-clinic', 'plain-clinic.hairvision-ai.test');
        $superAdmin = $this->createSuperAdmin();
        $category = AiModelCategory::query()->where('code', 'hair_transplant')->firstOrFail();
        $access = AiModelAccess::query()->create([
            'model_key' => 'sample_image',
            'usage_category_key' => $category->code,
            'name' => 'مدل نمونه تست',
            'is_active' => true,
            'is_demo' => true,
            'credit_limit' => null,
            'credit_used' => 0,
        ]);

        $this->grantCategoryEntitlement($tenantA, $category);
        $this->grantCategoryEntitlement($tenantB, $category);
        $tenantA->aiModelAccesses()->attach($access->id, [
            'is_active' => true,
            'assigned_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'پیام مدل نمونه',
                'body' => 'این پیام فقط برای کلینیک دارای مدل ارسال می‌شود.',
                'type' => 'system',
                'importance' => 'info',
                'target_scope' => 'all',
                'filter_ai_model_category_id' => $category->id,
                'filter_ai_model_access_id' => $access->id,
                'send_timing' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $notification = ClinicNotification::query()->where('title', 'پیام مدل نمونه')->firstOrFail();

        $this->assertSame([$tenantA->id], $notification->recipients()->pluck('tenant_id')->all());
    }

    public function test_system_notifications_can_target_selected_clinics_and_clinics_without_active_plan(): void
    {
        $tenantA = $this->createTenant('کلینیک اول', 'first', 'first.hairvision-ai.test');
        $tenantB = $this->createTenant('کلینیک دوم', 'second', 'second.hairvision-ai.test');
        $tenantC = $this->createTenant('کلینیک بدون پلن', 'no-plan', 'no-plan.hairvision-ai.test');
        $superAdmin = $this->createSuperAdmin();
        $category = AiModelCategory::query()->where('code', 'hair_transplant')->firstOrFail();

        $this->grantCategoryEntitlement($tenantA, $category);
        $this->grantCategoryEntitlement($tenantB, $category);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'پیام گزینشی',
                'body_html' => '<p>فقط دو کلینیک انتخاب‌شده</p>',
                'type' => 'general',
                'importance' => 'normal',
                'target_scope' => 'selected',
                'target_tenant_ids' => [$tenantA->id, $tenantC->id],
                'send_timing' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $selected = ClinicNotification::query()->where('title', 'پیام گزینشی')->firstOrFail();
        $this->assertEqualsCanonicalizing([$tenantA->id, $tenantC->id], $selected->recipients()->pluck('tenant_id')->all());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'پیام گزینشی دارای امکان',
                'body_html' => '<p>برای کلینیک‌های انتخاب‌شده دارای امکان</p>',
                'type' => 'general',
                'importance' => 'normal',
                'target_scope' => 'selected',
                'target_tenant_ids' => [$tenantA->id, $tenantB->id, $tenantC->id],
                'filter_ai_model_category_id' => $category->id,
                'send_timing' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $selectedWithFilter = ClinicNotification::query()->where('title', 'پیام گزینشی دارای امکان')->firstOrFail();
        $this->assertEqualsCanonicalizing([$tenantA->id, $tenantB->id], $selectedWithFilter->recipients()->pluck('tenant_id')->all());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/system/notifications', [
                'title' => 'پیام بدون پلن فعال',
                'body_html' => '<p>برای کلینیک‌هایی که پلن فعال ندارند</p>',
                'type' => 'billing',
                'importance' => 'important',
                'target_scope' => 'all',
                'filter_plan_state' => 'inactive',
                'send_timing' => 'immediate',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $withoutPlan = ClinicNotification::query()->where('title', 'پیام بدون پلن فعال')->firstOrFail();
        $this->assertSame([$tenantC->id], $withoutPlan->recipients()->pluck('tenant_id')->all());
    }

    private function createTenant(string $name, string $slug, string $domain): Tenant
    {
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        return $tenant;
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'access_scope' => 'system',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
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

    private function grantCategoryEntitlement(Tenant $tenant, AiModelCategory $category): void
    {
        $plan = Plan::query()->create([
            'name' => 'پلن '.$tenant->slug,
            'description' => 'پلن تست',
            'plan_type' => 'base_subscription',
            'service_key' => $category->code,
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => 30,
            'usage_limit' => 100,
            'status' => 'active',
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => 100,
            'usage_used' => 0,
        ]);

        SubscriptionServiceEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'service_key' => $category->code,
            'source_type' => 'base_plan',
            'source_id' => $plan->id,
            'included_credit' => 100,
            'purchased_credit' => 0,
            'used_credit' => 0,
            'is_active' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    private function forHost(string $host): self
    {
        return $this->withHeader('Host', $host);
    }
}
