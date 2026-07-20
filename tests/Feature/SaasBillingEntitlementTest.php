<?php

namespace Tests\Feature;

use App\Models\AiModelAccess;
use App\Models\CreditTransaction;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Upload;
use App\Models\User;
use App\Services\AI\ExecutableAiModelSyncService;
use App\Services\Billing\BillingService;
use App\Services\Billing\CreditLedgerService;
use App\Support\JalaliDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaasBillingEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['hair_transplant', 'eyebrow_transplant'] as $serviceKey) {
            $access = AiModelAccess::query()->create([
                'model_key' => 'sample_image',
                'usage_category_key' => $serviceKey,
                'name' => 'Default '.$serviceKey.' access',
                'credentials' => [],
                'settings' => [],
                'credit_limit' => null,
                'is_active' => true,
                'is_demo' => true,
                'is_protected' => false,
                'priority' => 10,
            ]);

            app(ExecutableAiModelSyncService::class)->sync($access);
        }
    }

    public function test_base_plan_can_grant_multiple_services_and_only_one_active_base_subscription_exists(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $billing = app(BillingService::class);
        $plan = $this->plan('پلن مو و ابرو', 'base_subscription', [
            'hair_transplant' => 1200,
            'eyebrow_transplant' => 600,
        ], durationDays: 365);

        $invoice = $billing->invoiceForPlan($user, $plan, $tenant);
        $subscription = $billing->activatePlan($user, $plan, $invoice, $tenant);

        $this->assertSame('active', $subscription->status);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->id)->where('status', 'active')->count());
        $this->assertDatabaseHas('subscription_service_entitlements', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'service_key' => 'hair_transplant',
            'included_credit' => 1200,
        ]);
        $this->assertDatabaseHas('subscription_service_entitlements', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'service_key' => 'eyebrow_transplant',
            'included_credit' => 600,
        ]);
        $this->assertEqualsCanonicalizing(
            ['hair_transplant', 'eyebrow_transplant'],
            $tenant->aiModelAccesses()
                ->wherePivot('is_active', true)
                ->pluck('usage_category_key')
                ->all()
        );

        $this->expectException(ValidationException::class);
        $billing->invoiceForPlan($user, $plan, $tenant);
    }

    public function test_service_add_on_and_credit_top_up_attach_to_active_base_subscription(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $billing = app(BillingService::class);
        $base = $this->plan('پلن پایه کاشت مو', 'base_subscription', ['hair_transplant' => 10]);
        $subscription = $billing->activatePlan($user, $base, $billing->invoiceForPlan($user, $base, $tenant), $tenant);
        $originalEndsAt = $subscription->ends_at;

        $addOn = $this->plan('افزودن کاشت ابرو', 'service_add_on', ['eyebrow_transplant' => 5]);
        $billing->activatePlan($user, $addOn, $billing->invoiceForPlan($user, $addOn, $tenant), $tenant);

        $this->assertDatabaseHas('subscription_service_entitlements', [
            'subscription_id' => $subscription->id,
            'service_key' => 'eyebrow_transplant',
            'source_type' => 'add_on',
            'included_credit' => 5,
        ]);
        $this->assertTrue($tenant->aiModelAccesses()
            ->where('usage_category_key', 'eyebrow_transplant')
            ->wherePivot('is_active', true)
            ->exists());

        $this->expectException(ValidationException::class);
        $billing->invoiceForPlan($user, $addOn, $tenant);
    }

    public function test_paid_invoice_assigns_the_exact_model_selected_on_the_plan(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $plan = $this->plan('پلن با مدل اختصاصی', 'base_subscription', ['hair_transplant' => 10]);
        $defaultAccess = AiModelAccess::query()
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();
        $selectedAccess = AiModelAccess::query()->create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'Plan-selected hair model',
            'credentials' => [],
            'settings' => [],
            'credit_limit' => null,
            'is_active' => true,
            'priority' => 100,
        ]);
        app(ExecutableAiModelSyncService::class)->sync($selectedAccess);
        $plan->aiModelAccesses()->sync([$selectedAccess->id]);

        $billing = app(BillingService::class);
        $invoice = $billing->invoiceForPlan($user, $plan, $tenant);

        $this->assertSame([$selectedAccess->id], $invoice->aiModelAccessIdsSnapshot());

        // A later plan edit must not change the already issued invoice.
        $plan->aiModelAccesses()->sync([$defaultAccess->id]);
        $billing->activatePlan($user, $plan, $invoice, $tenant);

        $this->assertDatabaseHas('clinic_ai_model_access', [
            'clinic_id' => $tenant->id,
            'ai_model_access_id' => $selectedAccess->id,
            'is_active' => true,
            'assigned_by' => $user->id,
        ]);
        $this->assertDatabaseMissing('clinic_ai_model_access', [
            'clinic_id' => $tenant->id,
            'ai_model_access_id' => $defaultAccess->id,
        ]);
    }

    public function test_credit_top_up_adds_credit_without_extending_subscription_or_unlocking_new_service(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $billing = app(BillingService::class);
        $base = $this->plan('پلن پایه کاشت مو', 'base_subscription', ['hair_transplant' => 10]);
        $subscription = $billing->activatePlan($user, $base, $billing->invoiceForPlan($user, $base, $tenant), $tenant);
        $originalEndsAt = $subscription->ends_at;

        $topUp = $this->plan('بسته اعتبار کاشت مو', 'credit_top_up', ['hair_transplant' => 25], durationDays: null);
        $billing->activatePlan($user, $topUp, $billing->invoiceForPlan($user, $topUp, $tenant), $tenant);

        $entitlement = SubscriptionServiceEntitlement::query()
            ->where('subscription_id', $subscription->id)
            ->where('service_key', 'hair_transplant')
            ->firstOrFail();

        $this->assertSame(25, $entitlement->purchased_credit);
        $this->assertSame(35, $entitlement->remainingCredit());
        $this->assertSame($originalEndsAt, $subscription->refresh()->ends_at);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'starts_at' => JalaliDate::normalizeDateTime($subscription->starts_at),
            'ends_at' => JalaliDate::normalizeDateTime($subscription->ends_at),
        ]);
        $this->assertDatabaseHas('credit_transactions', [
            'tenant_id' => $tenant->id,
            'service_key' => 'hair_transplant',
            'type' => 'top_up',
            'amount' => 25,
            'balance_after' => 35,
        ]);

        $eyebrowTopUp = $this->plan('بسته اعتبار ابرو', 'credit_top_up', ['eyebrow_transplant' => 10], durationDays: null);

        $this->expectException(ValidationException::class);
        $billing->invoiceForPlan($user, $eyebrowTopUp, $tenant);
    }

    public function test_plan_purchase_is_rejected_before_payment_when_an_application_has_no_executable_model(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $plan = $this->plan('پلن بدون مدل', 'base_subscription', ['hair_transplant' => 10]);

        AiModelAccess::query()
            ->where('usage_category_key', 'hair_transplant')
            ->update(['is_active' => false]);

        try {
            app(BillingService::class)->invoiceForPlan($user, $plan, $tenant);
            $this->fail('A plan without an executable model must not reach payment.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                __('subscriptions.errors.service_model_unavailable', ['service' => 'کاشت مو']),
                data_get($exception->errors(), 'plan.0')
            );
        }

        $this->assertDatabaseMissing('invoices', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_provisioning_assigns_complementary_models_to_cover_all_application_outputs(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $plan = $this->plan('پلن با مدل‌های مکمل', 'base_subscription', ['hair_transplant' => 10]);

        AiModelAccess::query()
            ->where('usage_category_key', 'hair_transplant')
            ->update(['is_active' => false]);

        $first = AiModelAccess::query()->create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'Hair model A',
            'credentials' => [],
            'settings' => [],
            'credit_limit' => null,
            'is_active' => true,
            'priority' => 10,
        ]);
        $second = AiModelAccess::query()->create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'Hair model B',
            'credentials' => [],
            'settings' => [],
            'credit_limit' => null,
            'is_active' => true,
            'priority' => 20,
        ]);

        $firstModel = app(ExecutableAiModelSyncService::class)->sync($first);
        $secondModel = app(ExecutableAiModelSyncService::class)->sync($second);
        $outputIds = $firstModel->category->outputs()->active()->pluck('id');

        $firstModel->outputConfigs()->where('ai_model_category_output_id', '!=', $outputIds->first())->update(['is_active' => false]);
        $secondModel->outputConfigs()->where('ai_model_category_output_id', $outputIds->first())->update(['is_active' => false]);

        $billing = app(BillingService::class);
        $billing->activatePlan($user, $plan, $billing->invoiceForPlan($user, $plan, $tenant), $tenant);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $tenant->aiModelAccesses()
                ->wherePivot('is_active', true)
                ->pluck('ai_model_accesses.id')
                ->all()
        );
    }

    public function test_upgrade_replaces_base_plan_without_carrying_remaining_credit(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $billing = app(BillingService::class);
        $starter = $this->plan('پلن شروع کاشت مو', 'base_subscription', ['hair_transplant' => 100]);
        $active = $billing->activatePlan($user, $starter, $billing->invoiceForPlan($user, $starter, $tenant), $tenant);

        app(CreditLedgerService::class)->consume($tenant, 'hair_transplant', 40);

        $upgrade = $this->plan('پلن حرفه‌ای کاشت مو', 'base_subscription', ['hair_transplant' => 300]);
        $newActive = $billing->activatePlan($user, $upgrade, $billing->invoiceForPlan($user, $upgrade, $tenant), $tenant);

        $this->assertSame('replaced', $active->refresh()->status);
        $this->assertSame($upgrade->id, $newActive->plan_id);
        $this->assertSame(300, $newActive->serviceEntitlements()->where('service_key', 'hair_transplant')->firstOrFail()->remainingCredit());
    }

    public function test_credit_consumption_is_atomic_logged_and_never_negative(): void
    {
        [$tenant, $user] = $this->clinicUser();
        $billing = app(BillingService::class);
        $base = $this->plan('پلن کم اعتبار', 'base_subscription', ['hair_transplant' => 2]);
        $billing->activatePlan($user, $base, $billing->invoiceForPlan($user, $base, $tenant), $tenant);
        $ledger = app(CreditLedgerService::class);

        $ledger->consume($tenant, 'hair_transplant');
        $ledger->consume($tenant, 'hair_transplant');

        try {
            $ledger->consume($tenant, 'hair_transplant');
            $this->fail('Credit over-consumption should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $entitlement = SubscriptionServiceEntitlement::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(0, $entitlement->remainingCredit());
        $this->assertSame(2, CreditTransaction::query()->where('tenant_id', $tenant->id)->where('type', 'use')->count());
    }

    public function test_generation_is_blocked_when_subscription_is_expired_or_service_credit_is_finished(): void
    {
        [$tenant, $user] = $this->clinicUser(domain: 'aria.hairvision-ai.test');
        $this->attachAiModel($tenant);
        $upload = $this->uploadForTenant($tenant);
        $billing = app(BillingService::class);
        $lowCredit = $this->plan('پلن ناکافی', 'base_subscription', ['hair_transplant' => 2]);
        $subscription = $billing->activatePlan($user, $lowCredit, $billing->invoiceForPlan($user, $lowCredit, $tenant), $tenant);

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_access');

        $subscription->forceFill(['ends_at' => now()->subMinute()])->save();

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('service_access');
    }

    /**
     * @param  array<string, int>  $credits
     */
    private function plan(string $name, string $type, array $credits, ?int $durationDays = 30): Plan
    {
        $plan = Plan::query()->create([
            'name' => $name,
            'description' => $name,
            'plan_type' => $type,
            'service_key' => array_key_first($credits),
            'price' => 1000,
            'currency' => 'IRR',
            'duration_days' => $durationDays,
            'usage_limit' => array_sum($credits),
            'status' => 'active',
        ]);

        foreach ($credits as $serviceKey => $credit) {
            $plan->serviceEntitlements()->create([
                'service_key' => $serviceKey,
                'credit_amount' => $credit,
                'is_active' => true,
            ]);
        }

        return $plan->load('serviceEntitlements');
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function clinicUser(string $domain = 'clinic.test'): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Clinic',
            'slug' => 'clinic-'.str()->random(6),
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
            'type' => 'tenant_subdomain',
            'status' => 'active',
            'ssl_status' => 'unknown',
        ]);

        $user = User::factory()->create([
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        $tenant->users()->attach($user->id, [
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        return [$tenant, $user];
    }

    private function attachAiModel(Tenant $tenant): void
    {
        $access = AiModelAccess::query()->create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'Test Sample Access',
            'credentials' => [],
            'prompt' => 'Sample hair growth preview',
            'credit_limit' => null,
            'is_active' => true,
            'is_demo' => true,
            'is_protected' => false,
            'priority' => 1,
        ]);

        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => true]);
    }

    private function uploadForTenant(Tenant $tenant): Upload
    {
        $lead = Lead::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => '09123334444',
            'phone_normalized' => '09123334444',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        return Upload::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'original_disk' => 'local',
            'original_path' => 'uploads/test.jpg',
            'mime' => 'image/jpeg',
            'size' => 1,
            'status' => 'validated',
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
