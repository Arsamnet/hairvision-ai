<?php

namespace Tests\Feature;

use App\Jobs\ProcessGenerationAttempt;
use App\Models\AiModel;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationAttempt;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\AI\AiModelTypeRegistry;
use App\Support\JalaliDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HairVisionV2ArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_type_registry_masks_secrets_and_rejects_unknown_fields(): void
    {
        $registry = app(AiModelTypeRegistry::class);

        $gapgpt = $registry->publicDefinition('gapgpt');

        $this->assertSame('gapgpt', $gapgpt['key']);
        $this->assertArrayNotHasKey('provider', $gapgpt);
        $this->assertSame('secret', $gapgpt['credentials'][0]['type']);

        $this->expectException(ValidationException::class);

        $registry->validateSettings('gapgpt', [
            'endpoint' => 'https://api.gapgpt.app/v1/images/edits',
            'model' => 'gpt-image-2',
            'size' => '1024x1024',
            'timeout_seconds' => 900,
            'provider' => 'forbidden',
        ]);
    }

    public function test_system_admin_can_create_category_output_and_sample_model_with_masked_credentials(): void
    {
        $admin = User::factory()->create([
            'role' => 'super_admin',
            'access_scope' => 'system',
            'status' => 'active',
        ]);

        $category = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/system/ai-model-categories', [
                'code' => 'skin_preview',
                'name' => 'پیش‌نمایش پوست',
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('data');

        $output = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/system/ai-model-categories/'.$category['id'].'/outputs', [
                'code' => 'final_result',
                'name' => 'نتیجه نهایی',
                'prompt' => 'یک پیش‌نمایش نهایی واقع‌گرایانه بساز.',
                'credit_cost' => 2,
                'is_active' => true,
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('یک پیش‌نمایش نهایی واقع‌گرایانه بساز.', $output['prompt']);

        $model = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/system/ai-models', [
                'category_id' => $category['id'],
                'type' => 'sample_image',
                'name' => 'Sample Skin',
                'status' => 'active',
                'concurrency_limit' => 2,
                'settings' => [],
                'credentials' => [],
                'output_configs' => [
                    [
                        'output_id' => $output['id'],
                        'is_active' => true,
                        'configuration' => ['sample_path' => 'sample-results/final_result.jpg'],
                    ],
                ],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame('Sample Skin', $model['name']);
        $this->assertSame([], $model['credentials']);
        $this->assertSame('final_result', $model['output_configs'][0]['output_code']);
    }

    public function test_public_profile_replaces_current_source_image_and_archives_previous_one(): void
    {
        [$tenant, $lead] = $this->tenantAndVerifiedLead('aria.hairvision-ai.test');

        $first = UploadedFile::fake()->image('first.jpg', 10, 10);
        $second = UploadedFile::fake()->image('second.jpg', 10, 10);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/profile/source-image', [
                'image' => $first,
                'consent' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 1);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/profile/source-image', [
                'image' => $second,
                'consent' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseCount('lead_source_images', 2);
        $this->assertDatabaseHas('lead_source_images', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'version' => 1,
            'is_current' => false,
        ]);
        $this->assertDatabaseHas('lead_source_images', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'version' => 2,
            'is_current' => true,
        ]);
    }

    public function test_public_bootstrap_lists_only_active_entitled_categories_with_active_outputs(): void
    {
        [$tenant] = $this->tenantAndVerifiedLead('aria.hairvision-ai.test');

        $skin = AiModelCategory::query()->create([
            'code' => 'skin_preview',
            'name' => 'پیش‌نمایش پوست',
            'description' => 'کاربرد فعال پوست',
            'status' => 'active',
            'sort_order' => 5,
        ]);
        $skin->outputs()->create([
            'code' => 'final_result',
            'name' => 'نتیجه نهایی',
            'description' => 'خروجی نهایی',
            'prompt' => 'این متن محرمانه است.',
            'sort_order' => 10,
            'credit_cost' => 1,
            'is_active' => true,
        ]);

        $inactive = AiModelCategory::query()->create([
            'code' => 'inactive_preview',
            'name' => 'کاربرد غیرفعال',
            'status' => 'inactive',
        ]);
        $inactive->outputs()->create([
            'code' => 'final_result',
            'name' => 'نتیجه نهایی',
            'prompt' => 'نمایش داده نشود.',
            'credit_cost' => 1,
            'is_active' => true,
        ]);
        AiModelCategory::query()->create([
            'code' => 'empty_preview',
            'name' => 'کاربرد بدون خروجی',
            'status' => 'active',
            'sort_order' => 6,
        ]);

        $this->grantEntitlement($tenant, 'skin_preview', 3);
        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $plan = $subscription->plan;

        foreach (['empty_preview', 'inactive_preview', 'eyebrow_transplant'] as $serviceKey) {
            SubscriptionServiceEntitlement::query()->create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'service_key' => $serviceKey,
                'included_credit' => 3,
                'purchased_credit' => 0,
                'used_credit' => 0,
                'is_active' => true,
                'starts_at' => JalaliDate::nowDateTimeString(),
                'ends_at' => JalaliDate::toJalaliDateTime(now()->addDays(30)),
            ]);
        }

        $this->categoryOutputAndActiveModel('skin_preview', 'final_result', $tenant);
        $this->categoryOutputAndActiveModel('eyebrow_transplant', 'eyebrow_final_result', $tenant);

        $response = $this->forHost('aria.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('categories.0.code', 'skin_preview')
            ->assertJsonPath('categories.0.outputs.0.code', 'final_result')
            ->assertJsonPath('categories.1.code', 'eyebrow_transplant')
            ->assertJsonPath('categories.1.outputs.0.code', 'eyebrow_final_result')
            ->assertJsonMissingPath('categories.0.outputs.0.prompt')
            ->assertJsonMissingPath('categories.1.outputs.0.prompt')
            ->json('categories');

        $this->assertCount(2, $response);
        $this->assertSame('skin_preview', $response[0]['code']);
        $this->assertSame('eyebrow_transplant', $response[1]['code']);
        $this->assertNull($response[0]['card_image_url']);
        $this->assertSame('/theme/category-icons/eyebrow-transplant.svg', $response[1]['card_image_url']);
    }

    public function test_visitor_can_request_final_output_independently_and_idempotently(): void
    {
        Queue::fake();

        [$tenant, $lead] = $this->tenantAndVerifiedLead('aria.hairvision-ai.test');
        [$category, $output] = $this->categoryOutputAndActiveModel('hair_transplant', 'final_result', $tenant);
        $this->grantEntitlement($tenant, $category->code, 5);

        $source = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/profile/source-image', [
                'image' => UploadedFile::fake()->image('source.jpg', 10, 10),
                'consent' => true,
            ])
            ->assertCreated()
            ->json('data');

        $payload = [
            'source_image_id' => $source['id'],
            'category_id' => $category->uuid,
            'output_id' => $output->uuid,
            'idempotency_key' => '11111111-1111-4111-8111-111111111111',
        ];

        $first = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', $payload)
            ->assertCreated()
            ->assertJsonPath('data.output.code', 'final_result')
            ->assertJsonPath('data.status', 'queued')
            ->json('data.id');

        $second = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', $payload)
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('generation_requests', 1);
        $this->assertDatabaseCount('generation_attempts', 1);
        $this->assertDatabaseHas('generation_attempts', [
            'prompt' => $output->prompt,
            'status' => 'dispatched',
        ]);
        Queue::assertPushed(ProcessGenerationAttempt::class, 1);
    }

    public function test_independent_generation_attempt_job_stores_asset_and_consumes_credit(): void
    {
        Queue::fake();
        Storage::disk('local')->put('sample-results/final_result.jpg', 'fake-generated-image');

        [$tenant, $lead] = $this->tenantAndVerifiedLead('aria.hairvision-ai.test');
        [$category, $output] = $this->categoryOutputAndActiveModel('hair_transplant', 'final_result', $tenant);
        $this->grantEntitlement($tenant, $category->code, 5);

        $source = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/profile/source-image', [
                'image' => UploadedFile::fake()->image('source.jpg', 10, 10),
                'consent' => true,
            ])
            ->assertCreated()
            ->json('data');

        $generationId = $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', [
                'source_image_id' => $source['id'],
                'category_id' => $category->uuid,
                'output_id' => $output->uuid,
                'idempotency_key' => '22222222-2222-4222-8222-222222222222',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'queued')
            ->json('data.id');

        $attempt = GenerationAttempt::query()->with('generationRequest')->firstOrFail();

        (new ProcessGenerationAttempt($attempt))->handle(
            app(\App\Actions\Ai\RunAiModelAction::class),
            app(\App\Services\Billing\CreditLedgerService::class),
        );

        $this->assertDatabaseHas('generation_attempts', [
            'id' => $attempt->id,
            'status' => 'completed',
            'prompt' => $output->prompt,
        ]);
        $this->assertDatabaseHas('generation_requests', [
            'uuid' => $generationId,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('generated_assets', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'ai_model_category_output_id' => $output->id,
            'generation_attempt_id' => $attempt->id,
            'is_current' => true,
        ]);
        $this->assertDatabaseHas('subscription_service_entitlements', [
            'tenant_id' => $tenant->id,
            'service_key' => $category->code,
            'used_credit' => 1,
        ]);
        $this->assertDatabaseHas('credit_transactions', [
            'tenant_id' => $tenant->id,
            'service_key' => $category->code,
            'amount' => -1,
            'balance_after' => 4,
        ]);

        $this->withSession(['lead_id' => $lead->id])
            ->forHost('aria.hairvision-ai.test')
            ->getJson('/api/v1/generations/'.$generationId)
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.output.code', 'final_result')
            ->assertJson(fn ($json) => $json->whereType('data.asset_url', 'string')->etc());
    }

    public function test_same_phone_has_isolated_profiles_across_tenants(): void
    {
        [$firstTenant, $firstLead] = $this->tenantAndVerifiedLead('aria.hairvision-ai.test', '09123456789');
        [$secondTenant, $secondLead] = $this->tenantAndVerifiedLead('beta.hairvision-ai.test', '09123456789');

        $firstSource = $this->withSession(['lead_id' => $firstLead->id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/profile/source-image', [
                'image' => UploadedFile::fake()->image('first.jpg', 10, 10),
                'consent' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->withSession(['lead_id' => $secondLead->id])
            ->forHost('beta.hairvision-ai.test')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('data.source_image', null);

        $this->assertNotSame($firstTenant->id, $secondTenant->id);
        $this->assertNotSame($firstLead->id, $secondLead->id);
        $this->assertDatabaseHas('lead_source_images', [
            'uuid' => $firstSource,
            'tenant_id' => $firstTenant->id,
            'lead_id' => $firstLead->id,
        ]);
    }

    public function test_system_admin_manages_dynamic_category_for_plans_and_ai_model_accesses(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'role' => 'super_admin',
            'access_scope' => 'system',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post(route('admin.system.ai-model-categories.store'), [
                '_form' => 'create_category',
                'code' => 'filler_under_the_eyes',
                'name' => 'فیلر زیر چشم',
                'description' => 'پیش‌نمایش تصویر فیلر زیر چشم',
                'status' => 'active',
                'sort_order' => 30,
                'icon' => 'fi-rr-eye',
                'card_image' => UploadedFile::fake()->image('filler-card.png', 32, 32),
            ])
            ->assertRedirect();

        $category = AiModelCategory::query()->where('code', 'filler_under_the_eyes')->firstOrFail();
        $this->assertSame('eye', $category->icon);
        $this->assertStringStartsWith('/storage/category-cards/', (string) $category->card_image_path);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', (string) $category->card_image_path));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.ai-model-categories.index'))
            ->assertOk()
            ->assertSee('filler_under_the_eyes')
            ->assertSee('فیلر زیر چشم')
            ->assertSee($category->cardImageUrl(), false);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertSee('فیلر زیر چشم')
            ->assertSee('data-category-icon="eye"', false);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->put(route('admin.system.ai-model-categories.update', $category), [
                '_form' => 'edit_category_'.$category->id,
                'name' => 'فیلر زیر چشم',
                'description' => 'پیش‌نمایش تصویر فیلر زیر چشم',
                'status' => 'active',
                'sort_order' => 30,
                'icon' => 'missing-icon-code',
            ])
            ->assertRedirect();

        $this->assertNull($category->refresh()->icon);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.ai-model-categories.show', $category))
            ->assertOk()
            ->assertSee($category->cardImageUrl(), false)
            ->assertSee('data-admin-modal-trigger="show-create-output-', false);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.system.ai-model-categories.outputs.index'));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertSee('فیلر زیر چشم');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post(route('admin.system.ai-model-categories.outputs.store', $category), [
                '_form' => 'create_output_'.$category->id,
                'code' => 'final_result',
                'name' => 'نتیجه نهایی',
                'description' => 'خروجی نهایی فیلر زیر چشم',
                'prompt' => 'یک پیش‌نمایش نهایی واقع‌گرایانه بساز.',
                'credit_cost' => 2,
                'sort_order' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.ai-model-categories.show', $category))
            ->assertOk()
            ->assertSee('data-admin-table-container', false)
            ->assertSee('data-table-action-menu', false)
            ->assertSee('data-admin-modal-trigger="show-create-output-', false)
            ->assertSee('data-admin-modal-trigger="show-edit-output-', false)
            ->assertSee('نتیجه نهایی')
            ->assertSee(__('ai_models.outputs.credit_cost'));

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post(route('admin.system.plans.store'), [
                '_form' => 'create_plan',
                'name' => 'پلن فیلر زیر چشم',
                'plan_type' => 'service_add_on',
                'price' => 1000000,
                'currency' => 'ریال',
                'duration_days' => 30,
                'status' => 'active',
                'is_public' => '1',
                'service_credits' => [
                    'filler_under_the_eyes' => 25,
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('plan_service_entitlements', [
            'service_key' => 'filler_under_the_eyes',
            'credit_amount' => 25,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.ai-models.index'))
            ->assertOk()
            ->assertSee('فیلر زیر چشم')
            ->assertSee('مدل نمونه HairVision');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->post(route('admin.ai-models.store'), [
                '_form' => 'create_ai_model_access',
                'model_key' => 'sample_image',
                'usage_category_key' => $category->code,
                'name' => 'مدل نمونه فیلر زیر چشم',
                'prompt' => 'نمونه پیش‌نمایش فیلر زیر چشم را بساز.',
                'is_active' => '1',
                'return_to_index' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_model_accesses', [
            'model_key' => 'sample_image',
            'usage_category_key' => 'filler_under_the_eyes',
            'name' => 'مدل نمونه فیلر زیر چشم',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.system.ai-model-categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->patch(route('admin.system.ai-model-categories.archive', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('ai_model_categories', [
            'id' => $category->id,
            'status' => 'archived',
        ]);

        $unusedCategory = AiModelCategory::query()->create([
            'code' => 'unused_category',
            'name' => 'کاربرد بدون استفاده',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->patch(route('admin.system.ai-model-categories.archive', $unusedCategory))
            ->assertSessionHasErrors('category');

        $this->actingAs($admin)
            ->forHost('hairvision-ai.test')
            ->delete(route('admin.system.ai-model-categories.destroy', $unusedCategory))
            ->assertRedirect();

        $this->assertSoftDeleted('ai_model_categories', [
            'id' => $unusedCategory->id,
        ]);
    }

    /**
     * @return array{Tenant, Lead}
     */
    private function tenantAndVerifiedLead(string $host, string $phone = '09123456789'): array
    {
        $slug = str($host)->before('.')->toString();

        $tenant = Tenant::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'status' => 'active',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'domain' => $host,
            'type' => 'tenant_subdomain',
            'status' => 'active',
        ]);

        $lead = Lead::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => $phone,
            'phone_normalized' => $phone,
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        return [$tenant, $lead];
    }

    /**
     * @return array{AiModelCategory, AiModelCategoryOutput}
     */
    private function categoryOutputAndActiveModel(string $categoryCode, string $outputCode, Tenant $tenant): array
    {
        $category = AiModelCategory::query()->where('code', $categoryCode)->firstOrFail();
        $output = AiModelCategoryOutput::query()
            ->where('ai_model_category_id', $category->id)
            ->where('code', $outputCode)
            ->firstOrFail();

        $access = AiModelAccess::query()->create([
            'model_key' => 'sample_image',
            'usage_category_key' => $categoryCode,
            'name' => 'Sample '.$categoryCode.' '.$outputCode,
            'credentials' => [],
            'settings' => [],
            'credit_limit' => null,
            'credit_used' => 0,
            'usage_count' => 0,
            'is_active' => true,
            'is_demo' => true,
            'is_protected' => false,
            'priority' => 100,
        ]);

        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => true]);

        $model = AiModel::query()->create([
            'ai_model_category_id' => $category->id,
            'type' => 'sample_image',
            'name' => 'Sample Hair',
            'status' => 'active',
            'priority' => 100,
            'concurrency_limit' => 1,
            'settings' => [],
            'credentials' => [],
            'health_status' => 'healthy',
            'legacy_ai_model_access_id' => $access->id,
        ]);

        $model->outputConfigs()->create([
            'ai_model_category_output_id' => $output->id,
            'is_active' => true,
            'configuration' => ['sample_path' => 'sample-results/'.$outputCode.'.jpg'],
        ]);

        return [$category, $output];
    }

    private function grantEntitlement(Tenant $tenant, string $serviceKey, int $credit): void
    {
        $plan = Plan::query()->create([
            'name' => 'Base',
            'plan_type' => 'base_subscription',
            'price' => 1000,
            'duration_days' => 30,
            'usage_limit' => 0,
            'status' => 'active',
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => JalaliDate::nowDateTimeString(),
            'ends_at' => JalaliDate::toJalaliDateTime(now()->addDays(30)),
            'usage_limit' => 0,
        ]);

        SubscriptionServiceEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'service_key' => $serviceKey,
            'included_credit' => $credit,
            'purchased_credit' => 0,
            'used_credit' => 0,
            'is_active' => true,
            'starts_at' => JalaliDate::nowDateTimeString(),
            'ends_at' => JalaliDate::toJalaliDateTime(now()->addDays(30)),
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
