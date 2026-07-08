<?php

namespace Tests\Feature;

use App\Actions\Ai\RunAiModelAction;
use App\Models\AiModelAccess;
use App\Models\AiModelUsageLog;
use App\Models\AiProvider;
use App\Models\CreditTransaction;
use App\Models\GenerationGroup;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\TenantAiSetting;
use App\Models\TenantDomain;
use App\Models\Upload;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiModelAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_JPEG_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASP/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASP/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Al//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EFBQRAQAAAAAAAAAAAAAAAAAAARD/2gAIAQMBAT8QH//EFBQRAQAAAAAAAAAAAAAAAAAAARD/2gAIAQIBAT8QH//EFBABAQAAAAAAAAAAAAAAAAAAARD/2gAIAQEAAT8QH//Z';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        config([
            'app.debug' => true,
            'hairvision.debug_fast_samples' => true,
            'hairvision.default_ai_provider' => 'sample_image',
            'hairvision.otp.max_requests' => 10,
            'hairvision.otp.decay_seconds' => 600,
        ]);
    }

    public function test_config_seeded_demo_model_and_permissions_are_available(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $demo = AiModelAccess::query()->where('model_key', 'sample_image')->where('is_demo', true)->firstOrFail();
        $gapGpt = AiModelAccess::query()->where('model_key', 'gapgpt')->where('usage_category_key', 'hair_transplant')->firstOrFail();
        $rawGapGptCredentials = DB::table('ai_model_accesses')->where('id', $gapGpt->id)->value('credentials');
        $stagePrompts = data_get($gapGpt->settings, 'stage_prompts');

        $this->assertArrayHasKey('sample_image', config('ai-models.models'));
        $this->assertArrayHasKey('gapgpt', config('ai-models.models'));
        $this->assertArrayHasKey('hair_transplant', config('ai-models.usage_categories'));
        $this->assertSame('کاشت مو', __('ai_models.usage_categories.hair_transplant'));
        $this->assertTrue((bool) config('ai-models.models.sample_image.is_protected'));
        $this->assertFalse((bool) config('ai-models.models.sample_image.requires_credentials'));
        $this->assertTrue((bool) config('ai-models.models.gapgpt.requires_credentials'));
        $this->assertTrue($demo->is_protected);
        $this->assertTrue($demo->is_active);
        $this->assertFalse($gapGpt->is_active);
        $this->assertSame([], $gapGpt->credentials);
        $this->assertIsString($rawGapGptCredentials);
        $this->assertSame('{stage_prompt}', $gapGpt->prompt);
        $this->assertSame('https://api.gapgpt.app/v1/images/edits', data_get($gapGpt->settings, 'endpoint_url'));
        $this->assertSame('gpt-image-2', data_get($gapGpt->settings, 'model'));
        $this->assertSame('1024x1024', data_get($gapGpt->settings, 'size'));
        $this->assertSame('url', data_get($gapGpt->settings, 'response_format'));
        $this->assertSame(300, data_get($gapGpt->settings, 'timeout_seconds'));
        $this->assertSame(15, data_get($gapGpt->settings, 'connect_timeout_seconds'));
        $this->assertSame(1, data_get($gapGpt->settings, 'retry_attempts'));
        $this->assertSame(750, data_get($gapGpt->settings, 'retry_sleep_ms'));
        $this->assertIsArray($stagePrompts);
        $expectedStagePromptKeys = ['two_weeks', 'intermediate_regrowth', 'final_result'];
        $stagePromptKeys = array_keys($stagePrompts);
        sort($expectedStagePromptKeys);
        sort($stagePromptKeys);

        $this->assertSame($expectedStagePromptKeys, $stagePromptKeys);
        $this->assertStringContainsString('two weeks after a complete and successful hair transplant', $stagePrompts['two_weeks']);
        $this->assertStringContainsString('intermediate hair regrowth stage after a complete', $stagePrompts['intermediate_regrowth']);
        $this->assertStringContainsString('final successful result after full hair growth', $stagePrompts['final_result']);
        $this->assertTrue($superAdmin->hasPermission('ai_models.manage_credit'));
        $this->assertTrue($tenant->aiModelAccesses()->whereKey($demo->id)->wherePivot('is_active', true)->exists());
        $this->assertTrue($tenant->aiModelAccesses()->whereKey($gapGpt->id)->wherePivot('is_active', false)->exists());
    }

    public function test_gapgpt_seeded_access_uses_dashboard_settings_without_env_credentials(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $gapGpt = AiModelAccess::query()->where('model_key', 'gapgpt')->where('usage_category_key', 'hair_transplant')->firstOrFail();
        $rawCredentials = DB::table('ai_model_accesses')->where('id', $gapGpt->id)->value('credentials');

        $this->assertFalse($gapGpt->is_active);
        $this->assertSame([], $gapGpt->credentials);
        $this->assertSame('https://api.gapgpt.app/v1/images/edits', data_get($gapGpt->settings, 'endpoint_url'));
        $this->assertSame('gpt-image-2', data_get($gapGpt->settings, 'model'));
        $this->assertIsString($rawCredentials);
        $this->assertTrue($tenant->aiModelAccesses()->whereKey($gapGpt->id)->wherePivot('is_active', false)->exists());
    }

    public function test_super_admin_crud_masks_credentials_and_protects_demo_delete(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($clinicUser);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/ai-models', [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'hair_transplant',
                'name' => 'دسترسی امن تست',
                'description' => 'برای تست مدیریت مدل',
                'credentials' => ['api_key' => 'super-secret-ai-key'],
                'settings' => [
                    'endpoint_url' => 'https://api.gapgpt.app/v1/images/edits',
                    'model' => 'gpt-image-2',
                    'size' => '1024x1024',
                    'response_format' => 'url',
                ],
                'prompt' => 'Prompt',
                'credit_limit' => 5,
                'priority' => 5,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $access = AiModelAccess::query()->where('name', 'دسترسی امن تست')->firstOrFail();
        $rawCredentials = DB::table('ai_model_accesses')->where('id', $access->id)->value('credentials');

        $this->assertIsString($rawCredentials);
        $this->assertStringNotContainsString('super-secret-ai-key', $rawCredentials);
        $this->assertSame('super-secret-ai-key', $access->credentials['api_key']);
        $this->assertArrayNotHasKey('credentials', $access->toArray());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/ai-models')
            ->assertOk()
            ->assertSee(__('ai_models.details'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/ai-models?search=secure-secret')
            ->assertOk()
            ->assertDontSee('super-secret-ai-key');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/ai-models/'.$access->id)
            ->assertOk()
            ->assertSee($access->maskedCredentials()['api_key'])
            ->assertDontSee('super-secret-ai-key');

        $shortSecretAccess = AiModelAccess::create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'دسترسی کلید کوتاه',
            'credentials' => ['api_key' => 'abcde'],
            'is_active' => true,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->assertSame('*****', $shortSecretAccess->maskedCredentials()['api_key']);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/ai-models/'.$shortSecretAccess->id)
            ->assertOk()
            ->assertSee('*****')
            ->assertDontSee('abcde');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/ai-models/'.$access->id, [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'hair_transplant',
                'name' => 'دسترسی امن ویرایش',
                'description' => 'ویرایش شد',
                'prompt' => 'Prompt 2',
                'credit_limit' => 8,
                'priority' => 4,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('super-secret-ai-key', $access->refresh()->credentials['api_key']);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/ai-models', [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => 'دسترسی نمونه غیرمجاز',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('model_key');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/ai-models/'.$access->id.'/toggle')
            ->assertRedirect();

        $this->assertFalse($access->refresh()->is_active);

        $demo = AiModelAccess::query()->where('is_protected', true)->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->delete('/admin/ai-models/'.$demo->id)
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/ai-models/'.$demo->id, [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => 'ویرایش غیرمجاز دمو',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $clinicUser->tenants()->firstOrFail()->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/ai-models')
            ->assertForbidden();
    }

    public function test_assigned_active_model_is_used_for_generation_credit_and_logs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedSampleImages();

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $this->activateSubscription(User::query()->where('role', 'clinic_admin')->firstOrFail());
        $gapGpt = AiModelAccess::query()->where('model_key', 'gapgpt')->firstOrFail();
        $tenant->aiModelAccesses()->updateExistingPivot($gapGpt->id, ['is_active' => false]);

        $access = AiModelAccess::create([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'دسترسی اعتباری',
            'credentials' => [],
            'prompt' => 'Credit prompt',
            'credit_limit' => 3,
            'is_active' => true,
            'priority' => 0,
            'created_by' => $superAdmin->id,
            'updated_by' => $superAdmin->id,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $access->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $uploadId = $this->createVerifiedUploadViaApi('aria.hairvision-ai.test');

        $groupId = $this->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'completed')
            ->json('generation_group.id');

        $this->assertSame(3, $access->refresh()->credit_used);
        $this->assertSame(3, AiModelUsageLog::query()->where('ai_model_access_id', $access->id)->where('status', 'completed')->count());
        $this->assertSame(3, GenerationGroup::query()->where('uuid', $groupId)->firstOrFail()->jobs()->where('ai_model_access_id', $access->id)->count());
    }

    public function test_clinic_can_have_only_one_active_model_assignment_per_usage_category(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $hairAccessA = $this->createAccess([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT Hair A',
            'credentials' => ['api_key' => 'gap-a'],
            'is_demo' => false,
            'is_protected' => false,
            'priority' => 0,
        ]);

        $hairAccessB = $this->createAccess([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT Hair B',
            'credentials' => ['api_key' => 'gap-b'],
            'is_demo' => false,
            'is_protected' => false,
            'priority' => 0,
        ]);

        $eyebrowAccess = $this->createAccess([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'eyebrow_transplant',
            'name' => 'GapGPT Eyebrow',
            'credentials' => ['api_key' => 'gap-eyebrow'],
            'is_demo' => false,
            'is_protected' => false,
            'priority' => 0,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $hairAccessA->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $hairAccessB->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $eyebrowAccess->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $tenant->aiModelAccesses()->whereKey($hairAccessA->id)->firstOrFail()->pivot->is_active);
        $this->assertTrue((bool) $tenant->aiModelAccesses()->whereKey($hairAccessB->id)->firstOrFail()->pivot->is_active);
        $this->assertTrue((bool) $tenant->aiModelAccesses()->whereKey($eyebrowAccess->id)->firstOrFail()->pivot->is_active);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/clinics/'.$tenant->id.'/ai-models/'.$hairAccessB->id, [
                'ai_model_access_id' => $hairAccessB->id,
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $tenant->aiModelAccesses()->whereKey($hairAccessB->id)->firstOrFail()->pivot->is_active);
    }

    public function test_unassigned_inactive_and_finished_credit_models_cannot_be_used(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->activateTenantSubscription($tenant);
        $upload = $this->createVerifiedUpload($tenant);
        $access = $this->createAccess(['credit_limit' => null]);

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');

        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => false]);

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');

        $tenant->aiModelAccesses()->updateExistingPivot($access->id, ['is_active' => true]);
        $access->forceFill(['is_active' => false])->save();

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');

        $access->forceFill(['is_active' => true, 'credit_limit' => 0, 'credit_used' => 0])->save();

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('aria.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');
    }

    public function test_failed_provider_response_does_not_consume_credit(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $access = $this->createAccess([
            'credit_limit' => 1,
            'settings' => ['force_failure' => true],
        ]);
        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => true]);

        $response = app(RunAiModelAction::class)->execute($tenant, 'hair_transplant', selectedAccess: $access);

        $this->assertFalse($response->successful);
        $this->assertSame(0, $access->refresh()->credit_used);
        $this->assertDatabaseHas('ai_model_usage_logs', [
            'ai_model_access_id' => $access->id,
            'status' => 'failed',
            'credit_consumed' => 0,
        ]);
    }

    public function test_active_demo_model_returns_predefined_image_for_each_stage(): void
    {
        $tenant = $this->createTenant('Clinic One', 'aria', 'aria.hairvision-ai.test');
        $this->seedSampleImages();

        $access = $this->createAccess([
            'is_demo' => true,
            'is_protected' => true,
            'credit_limit' => null,
        ]);

        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => true]);

        foreach (['two_weeks', 'intermediate_regrowth', 'final_result'] as $stageKey) {
            $response = app(RunAiModelAction::class)->execute(
                $tenant,
                'hair_transplant',
                ['period' => $stageKey],
                selectedAccess: $access,
            );

            $this->assertTrue($response->successful);
            $this->assertSame(base64_decode(self::TEST_JPEG_BASE64), $response->output['binary_content'] ?? null);
            $this->assertSame('sample_image', $response->metadata['provider'] ?? null);
            $this->assertSame($stageKey, $response->metadata['stage_key'] ?? null);
        }
    }

    public function test_clinic_manager_creates_only_own_clinic_users_and_cannot_assign_system_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'aria')->firstOrFail();
        $otherTenant = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $clinicManager = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($clinicManager);

        $this->actingAs($clinicManager)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/users', [
                'name' => 'کاربر کلینیک',
                'email' => 'clinic-user@example.com',
                'phone_normalized' => '09124445555',
                'role' => 'viewer',
                'status' => 'active',
                'tenant_ids' => [$tenant->id],
                'tenant_roles' => [$tenant->id => 'viewer'],
                'tenant_statuses' => [$tenant->id => 'active'],
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'clinic-user@example.com')->firstOrFail();
        $this->assertTrue($created->tenants()->whereKey($tenant->id)->exists());

        $this->actingAs($clinicManager)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/users', [
                'name' => 'کاربر بیرون کلینیک',
                'email' => 'outside-user@example.com',
                'phone_normalized' => '09124446666',
                'role' => 'viewer',
                'status' => 'active',
                'tenant_ids' => [$otherTenant->id],
                'tenant_roles' => [$otherTenant->id => 'viewer'],
                'tenant_statuses' => [$otherTenant->id => 'active'],
            ])
            ->assertSessionHasErrors('tenant_ids');

        $systemRole = Role::create([
            'code' => 'system_operator',
            'name' => 'System Operator',
            'description' => 'System role',
            'status' => 'active',
        ]);
        $systemRole->permissions()->attach(Permission::query()->where('code', 'ai_models.list')->firstOrFail());

        $this->actingAs($clinicManager)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/users', [
                'name' => 'کاربر سیستم',
                'email' => 'system-role-user@example.com',
                'phone_normalized' => '09124447777',
                'role' => 'system_operator',
                'status' => 'active',
                'tenant_ids' => [$tenant->id],
                'tenant_roles' => [$tenant->id => 'system_operator'],
                'tenant_statuses' => [$tenant->id => 'active'],
            ])
            ->assertSessionHasErrors('role');
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

        $provider = AiProvider::query()->firstOrCreate(
            ['code' => 'sample_image'],
            [
                'name' => 'Sample Image Provider',
                'type' => 'local',
                'is_active' => true,
                'supports_async' => false,
            ]
        );

        TenantAiSetting::create([
            'tenant_id' => $tenant->id,
            'provider_id' => $provider->id,
            'is_default' => true,
        ]);

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createAccess(array $overrides = []): AiModelAccess
    {
        return AiModelAccess::create(array_merge([
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'Test AI Access '.uniqid(),
            'credentials' => [],
            'settings' => [],
            'prompt' => 'Test prompt',
            'credit_limit' => null,
            'is_active' => true,
            'priority' => 1,
        ], $overrides));
    }

    private function createVerifiedUpload(Tenant $tenant): Upload
    {
        $lead = Lead::create([
            'tenant_id' => $tenant->id,
            'phone' => '09123334444',
            'phone_normalized' => '09123334444',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        return Upload::create([
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'original_disk' => 'local',
            'original_path' => 'uploads/test.jpg',
            'mime' => 'image/jpeg',
            'size' => 1,
            'status' => 'validated',
        ]);
    }

    private function createVerifiedUploadViaApi(string $host): string
    {
        $tenantId = TenantDomain::query()
            ->where('domain', $host)
            ->value('tenant_id');

        $lead = Lead::create([
            'tenant_id' => $tenantId,
            'phone' => '09127770000',
            'phone_normalized' => '09127770000',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);

        return $this->withSession(['lead_id' => $lead->id])
            ->forHost($host)
            ->post('/api/v1/uploads', [
                'image' => $this->fakeImageUpload(),
                'consent' => '1',
            ])
            ->assertOk()
            ->json('upload.id');
    }

    private function fakeImageUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hairvision-test-image');
        file_put_contents($path, base64_decode(self::TEST_JPEG_BASE64));

        return new UploadedFile($path, 'scalp.jpg', 'image/jpeg', null, true);
    }

    private function seedSampleImages(): void
    {
        foreach (['two-weeks.jpg', 'intermediate_regrowth.jpg', 'final_result.jpg'] as $filename) {
            Storage::disk('local')->put('sample-results/'.$filename, base64_decode(self::TEST_JPEG_BASE64));
        }
    }

    private function activateSubscription(User $user): Subscription
    {
        $tenant = $user->tenants()->first();

        if (! $tenant) {
            $tenant = $this->createTenant('Clinic For Subscription', 'clinic-sub-'.$user->id, 'clinic-sub-'.$user->id.'.hairvision-ai.test');
            $tenant->users()->attach($user->id, ['role' => $user->role, 'status' => 'active']);
        }

        return $this->activateTenantSubscription($tenant, $user);
    }

    private function activateTenantSubscription(Tenant $tenant, ?User $user = null, int $credit = 100): Subscription
    {
        $user ??= User::factory()->create([
            'role' => 'clinic_admin',
            'status' => 'active',
        ]);

        if (! $tenant->users()->whereKey($user->id)->exists()) {
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
            'usage_limit' => $credit,
            'status' => 'active',
        ]);

        $plan->serviceEntitlements()->create([
            'service_key' => 'hair_transplant',
            'credit_amount' => $credit,
            'is_active' => true,
        ]);

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'usage_limit' => $credit,
            'usage_used' => 0,
        ]);

        $entitlement = SubscriptionServiceEntitlement::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'service_key' => 'hair_transplant',
            'source_type' => 'base_plan',
            'source_id' => $plan->id,
            'included_credit' => $credit,
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
            'amount' => $credit,
            'balance_after' => $credit,
        ]);

        return $subscription;
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
