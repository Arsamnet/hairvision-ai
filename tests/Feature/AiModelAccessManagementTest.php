<?php

namespace Tests\Feature;

use App\Actions\Ai\RunAiModelAction;
use App\Models\AiModel;
use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\AiModelUsageLog;
use App\Models\AiProvider;
use App\Models\CreditTransaction;
use App\Models\GenerationGroup;
use App\Models\GenerationJob;
use App\Models\Lead;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionServiceEntitlement;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Upload;
use App\Models\User;
use App\Services\AI\AiModelManager;
use App\Services\AI\InputImageProcessingSettings;
use Database\Seeders\AiModelAccessSeeder;
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
            'hairvision.api_image.max_dimension' => 800,
            'hairvision.api_image.jpeg_quality' => 85,
            'hairvision.otp.max_requests' => 10,
            'hairvision.otp.decay_seconds' => 600,
        ]);
    }

    public function test_config_seeded_demo_model_and_permissions_are_available(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $demo = AiModelAccess::query()->where('model_key', 'sample_image')->where('is_demo', true)->firstOrFail();
        $gapGpt = AiModelAccess::query()
            ->where('model_key', 'gapgpt')
            ->where('usage_category_key', 'hair_transplant')
            ->where('name', 'مدل واقعی GapGPT کاشت مو')
            ->firstOrFail();
        $rawGapGptCredentials = DB::table('ai_model_accesses')->where('id', $gapGpt->id)->value('credentials');
        $outputPrompts = AiModelCategoryOutput::query()
            ->whereHas('category', fn ($query) => $query->where('code', 'hair_transplant'))
            ->pluck('prompt', 'code')
            ->all();
        $outputMetadata = AiModelCategoryOutput::query()
            ->whereHas('category', fn ($query) => $query->where('code', 'hair_transplant'))
            ->get(['code', 'name', 'description'])
            ->keyBy('code');

        $this->assertArrayHasKey('sample_image', config('ai-models.models'));
        $this->assertArrayHasKey('gapgpt', config('ai-models.models'));
        $this->assertArrayHasKey('hair_transplant', config('ai-models.usage_categories'));
        $this->assertArrayHasKey('eyebrow_transplant', config('ai-models.usage_categories'));
        $this->assertSame('کاشت مو', __('ai_models.usage_categories.hair_transplant'));
        $this->assertFalse((bool) config('ai-models.models.sample_image.is_protected'));
        $this->assertFalse((bool) config('ai-models.models.sample_image.requires_credentials'));
        $this->assertTrue((bool) config('ai-models.models.gapgpt.requires_credentials'));
        $this->assertFalse($demo->is_protected);
        $this->assertTrue($demo->is_active);
        $this->assertTrue($gapGpt->is_active);
        $this->assertNotEmpty($gapGpt->credentials['api_key'] ?? null);
        $this->assertIsString($rawGapGptCredentials);
        $this->assertNull($gapGpt->prompt);
        $this->assertSame('https://api.gapgpt.app/v1/images/edits', data_get($gapGpt->settings, 'endpoint_url'));
        $this->assertSame('gpt-image-2', data_get($gapGpt->settings, 'model'));
        $this->assertSame('1024x1024', data_get($gapGpt->settings, 'size'));
        $this->assertSame('url', data_get($gapGpt->settings, 'response_format'));
        $this->assertSame(900, data_get($gapGpt->settings, 'timeout_seconds'));
        $this->assertSame(30, data_get($gapGpt->settings, 'connect_timeout_seconds'));

        foreach ([$demo, $gapGpt] as $seededAccess) {
            $this->assertTrue(data_get($seededAccess->settings, InputImageProcessingSettings::ENABLED));
            $this->assertSame(800, data_get($seededAccess->settings, InputImageProcessingSettings::MAX_DIMENSION));
            $this->assertSame(85, data_get($seededAccess->settings, InputImageProcessingSettings::JPEG_QUALITY));
        }
        $this->assertArrayNotHasKey('retry_attempts', $gapGpt->settings);
        $this->assertArrayNotHasKey('retry_sleep_ms', $gapGpt->settings);
        $this->assertArrayNotHasKey('stage_prompts', $gapGpt->settings);
        $expectedStagePromptKeys = ['two_weeks', 'intermediate_regrowth', 'final_result'];
        $stagePromptKeys = array_keys($outputPrompts);
        sort($expectedStagePromptKeys);
        sort($stagePromptKeys);

        $this->assertSame($expectedStagePromptKeys, $stagePromptKeys);
        $this->assertSame(config('hairvision.generation.stages.two_weeks.prompt'), $outputPrompts['two_weeks']);
        $this->assertSame(config('hairvision.generation.stages.intermediate_regrowth.prompt'), $outputPrompts['intermediate_regrowth']);
        $this->assertSame(config('hairvision.generation.stages.final_result.prompt'), $outputPrompts['final_result']);
        $this->assertSame('یک روز بعد از کاشت مو', $outputMetadata['two_weeks']->name);
        $this->assertSame('نمای طبیعی روز اول با گرافت‌های تازه، قرمزی ملایم و ریزدلمه‌های قابل‌انتظار', $outputMetadata['two_weeks']->description);
        $this->assertSame('۶ ماه بعد از کاشت مو', $outputMetadata['intermediate_regrowth']->name);
        $this->assertSame('رشد میانی شش‌ماهه با خط رویش شکل‌گرفته، تراکم ناکامل و نمایان بودن طبیعی بخشی از پوست سر', $outputMetadata['intermediate_regrowth']->description);
        $this->assertSame('یک سال بعد از کاشت مو', $outputMetadata['final_result']->name);
        $this->assertSame('نتیجه کامل یک‌ساله با خط رویش طبیعی، پوشش متناسب و تراکم باورپذیر', $outputMetadata['final_result']->description);

        $this->assertSame([
            'مدل دمو کاشت ابرو',
            'مدل دمو کاشت مو',
            'مدل واقعی GapGPT کاشت ابرو',
            'مدل واقعی GapGPT کاشت مو',
        ], AiModelAccess::query()->orderBy('name')->pluck('name')->all());

        $eyebrowOutputs = AiModelCategoryOutput::query()
            ->whereHas('category', fn ($query) => $query->where('code', 'eyebrow_transplant'))
            ->get(['code', 'name', 'description', 'prompt'])
            ->keyBy('code');

        $this->assertSame(['eyebrow_final_result'], $eyebrowOutputs->keys()->all());
        $this->assertSame('یک سال بعد از کاشت ابرو', $eyebrowOutputs['eyebrow_final_result']->name);
        $this->assertSame('نتیجه کامل یک‌ساله با تراکم، جهت رشد و عدم تقارن طبیعی ابروها', $eyebrowOutputs['eyebrow_final_result']->description);
        $this->assertSame(config('hairvision.generation.stages.eyebrow_final_result.prompt'), $eyebrowOutputs['eyebrow_final_result']->prompt);

        $this->assertSame(3, AiModel::query()
            ->where('type', 'sample_image')
            ->where('status', 'active')
            ->whereHas('category', fn ($query) => $query->where('code', 'hair_transplant'))
            ->whereHas('outputConfigs', fn ($query) => $query->where('is_active', true))
            ->firstOrFail()
            ->outputConfigs()
            ->where('is_active', true)
            ->count());
        $this->assertTrue(AiModel::query()
            ->where('type', 'sample_image')
            ->where('status', 'active')
            ->whereHas('category', fn ($query) => $query->where('code', 'eyebrow_transplant'))
            ->whereHas('outputConfigs.output', fn ($query) => $query->where('code', 'eyebrow_final_result'))
            ->exists());
        $this->assertSame(2, AiModel::query()->where('type', 'gapgpt')->where('status', 'active')->count());
        $this->assertTrue(AiModel::query()
            ->where('type', 'gapgpt')
            ->whereHas('category', fn ($query) => $query->where('code', 'eyebrow_transplant'))
            ->whereHas('outputConfigs.output', fn ($query) => $query->where('code', 'eyebrow_final_result'))
            ->exists());

        $bootstrapCategories = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code')
            ->all();

        $this->assertContains('hair_transplant', $bootstrapCategories);
        $this->assertContains('eyebrow_transplant', $bootstrapCategories);
        $this->assertTrue($superAdmin->hasPermission('ai_models.manage_credit'));
        $this->assertTrue($tenant->aiModelAccesses()->whereKey($demo->id)->wherePivot('is_active', true)->exists());
        $this->assertTrue($tenant->aiModelAccesses()
            ->where('usage_category_key', 'eyebrow_transplant')
            ->where('model_key', 'sample_image')
            ->wherePivot('is_active', true)
            ->exists());
        $this->assertTrue($tenant->aiModelAccesses()->whereKey($gapGpt->id)->wherePivot('is_active', false)->exists());
    }

    public function test_ai_model_access_seeder_refreshes_output_metadata_and_executable_prompt(): void
    {
        $this->seed(DatabaseSeeder::class);

        $output = AiModelCategoryOutput::query()
            ->where('code', 'two_weeks')
            ->whereHas('category', fn ($query) => $query->where('code', 'hair_transplant'))
            ->firstOrFail();

        $output->update([
            'name' => 'عنوان قدیمی',
            'description' => 'توضیح قدیمی',
            'prompt' => 'stale prompt',
        ]);

        $this->seed(AiModelAccessSeeder::class);

        $output->refresh();
        $this->assertSame('یک روز بعد از کاشت مو', $output->name);
        $this->assertSame('نمای طبیعی روز اول با گرافت‌های تازه، قرمزی ملایم و ریزدلمه‌های قابل‌انتظار', $output->description);
        $this->assertSame(config('hairvision.generation.stages.two_weeks.prompt'), $output->prompt);
        $this->assertTrue(AiModel::query()
            ->where('type', 'gapgpt')
            ->whereHas('category', fn ($query) => $query->where('code', 'hair_transplant'))
            ->whereHas('outputConfigs', fn ($query) => $query
                ->where('ai_model_category_output_id', $output->id)
                ->where('configuration->prompt', $output->prompt))
            ->exists());
    }

    public function test_seeded_gapgpt_accesses_use_category_specific_names_and_dashboard_settings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $gapGptAccesses = AiModelAccess::query()
            ->where('model_key', 'gapgpt')
            ->orderBy('usage_category_key')
            ->get();

        $this->assertSame([
            'eyebrow_transplant' => 'مدل واقعی GapGPT کاشت ابرو',
            'hair_transplant' => 'مدل واقعی GapGPT کاشت مو',
        ], $gapGptAccesses->pluck('name', 'usage_category_key')->all());

        $gapGptAccesses->each(function (AiModelAccess $gapGpt) use ($tenant): void {
            $rawCredentials = DB::table('ai_model_accesses')->where('id', $gapGpt->id)->value('credentials');

            $this->assertTrue($gapGpt->is_active);
            $this->assertNotEmpty($gapGpt->credentials['api_key'] ?? null);
            $this->assertSame('https://api.gapgpt.app/v1/images/edits', data_get($gapGpt->settings, 'endpoint_url'));
            $this->assertSame('gpt-image-2', data_get($gapGpt->settings, 'model'));
            $this->assertIsString($rawCredentials);
            $this->assertTrue($tenant->aiModelAccesses()->whereKey($gapGpt->id)->wherePivot('is_active', false)->exists());
        });
    }

    public function test_public_bootstrap_tracks_clinic_model_assignment_and_entitlement_state(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $eyebrowAccess = AiModelAccess::query()
            ->where('model_key', 'sample_image')
            ->where('usage_category_key', 'eyebrow_transplant')
            ->firstOrFail();

        $initialCategories = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code');

        $this->assertContains('hair_transplant', $initialCategories);
        $this->assertContains('eyebrow_transplant', $initialCategories);

        $tenant->aiModelAccesses()->updateExistingPivot($eyebrowAccess->id, ['is_active' => false]);

        $withoutClinicModel = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code');

        $this->assertContains('hair_transplant', $withoutClinicModel);
        $this->assertNotContains('eyebrow_transplant', $withoutClinicModel);

        $tenant->aiModelAccesses()->updateExistingPivot($eyebrowAccess->id, ['is_active' => true]);
        SubscriptionServiceEntitlement::query()
            ->where('tenant_id', $tenant->id)
            ->where('service_key', 'eyebrow_transplant')
            ->update(['is_active' => false]);

        $withoutEntitlement = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code');

        $this->assertContains('hair_transplant', $withoutEntitlement);
        $this->assertNotContains('eyebrow_transplant', $withoutEntitlement);
    }

    public function test_real_gapgpt_access_is_requestable_when_demo_models_are_disabled(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();

        AiModelAccess::query()
            ->where('model_key', 'sample_image')
            ->where('usage_category_key', 'hair_transplant')
            ->update(['is_active' => false]);

        $access = app(AiModelManager::class)->create([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT فعال تست',
            'description' => 'مدل واقعی فعال برای تست لندینگ',
            'credentials' => ['api_key' => 'test-gapgpt-api-key'],
            'settings' => [
                'endpoint_url' => 'https://api.gapgpt.app/v1/images/edits',
                'model' => 'gpt-image-2',
                'size' => '1024x1024',
                'response_format' => 'url',
                'timeout_seconds' => 900,
                'connect_timeout_seconds' => 30,
            ],
            'credit_limit' => null,
            'priority' => 1,
            'is_active' => true,
        ], $superAdmin);

        $tenant->aiModelAccesses()->syncWithoutDetaching([
            $access->id => [
                'is_active' => true,
                'assigned_by' => $superAdmin->id,
            ],
        ]);

        $bridgedModel = AiModel::query()
            ->where('legacy_ai_model_access_id', $access->id)
            ->where('type', 'gapgpt')
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame(3, $bridgedModel->outputConfigs()->where('is_active', true)->count());

        $categories = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'));

        $hair = $categories->firstWhere('code', 'hair_transplant');

        $this->assertIsArray($hair);
        $this->assertSame(['two_weeks', 'intermediate_regrowth', 'final_result'], collect($hair['outputs'])->pluck('code')->all());
    }

    public function test_system_category_toggle_hides_application_from_all_public_landings(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $category = AiModelCategory::query()
            ->where('code', 'eyebrow_transplant')
            ->firstOrFail();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.system.ai-model-categories.index'))
            ->assertOk()
            ->assertSee(__('ai_models.categories.deactivate'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch(route('admin.system.ai-model-categories.toggle', $category))
            ->assertRedirect();

        $this->assertSame('inactive', $category->refresh()->status);

        $categories = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code');

        $this->assertContains('hair_transplant', $categories);
        $this->assertNotContains('eyebrow_transplant', $categories);
    }

    public function test_disabled_global_model_hides_dependent_applications_and_is_visible_on_clinic_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $eyebrowAccess = AiModelAccess::query()
            ->where('model_key', 'sample_image')
            ->where('usage_category_key', 'eyebrow_transplant')
            ->firstOrFail();

        $eyebrowAccess->forceFill(['is_active' => false])->save();

        $categories = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code');

        $this->assertContains('hair_transplant', $categories);
        $this->assertNotContains('eyebrow_transplant', $categories);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.applications.title'))
            ->assertSee(__('dashboard.applications.statuses.model_disabled_by_system'));
    }

    public function test_disabled_purchased_plan_hides_public_applications_and_is_reported_on_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->with('plan')
            ->firstOrFail();

        $subscription->plan->forceFill(['status' => 'inactive'])->save();

        $categories = collect($this->forHost('arya.hairvision-ai.test')
            ->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->json('categories'))
            ->pluck('code');

        $this->assertNotContains('hair_transplant', $categories);
        $this->assertNotContains('eyebrow_transplant', $categories);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.plan.plan_disabled'))
            ->assertSee(__('dashboard.plan.disabled_by_system_badge'))
            ->assertSee(__('dashboard.applications.statuses.plan_disabled_by_system'));
    }

    public function test_super_admin_crud_masks_credentials_and_allows_demo_model_editing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($clinicUser);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/ai-models', [
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
        $this->assertNull($access->prompt);
        $this->assertArrayNotHasKey('stage_prompts', $access->settings ?? []);
        $this->assertArrayNotHasKey('credentials', $access->toArray());

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models')
            ->assertOk()
            ->assertSee(__('ai_models.details'))
            ->assertSee(__('ai_models.edit'))
            ->assertSee(__('ai_models.usage_count'));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models?search=secure-secret')
            ->assertOk()
            ->assertDontSee('super-secret-ai-key');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models/'.$access->id)
            ->assertOk()
            ->assertSee($access->maskedCredentials()['api_key'])
            ->assertDontSee('super-secret-ai-key');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models/'.$access->id.'/edit')
            ->assertOk()
            ->assertSee(__('ai_models.edit'))
            ->assertSee($access->name);

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
            ->get('/admin/settings/ai-models/'.$shortSecretAccess->id)
            ->assertOk()
            ->assertSee('*****')
            ->assertDontSee('abcde');

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/ai-models/'.$access->id, [
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

        $access->refresh();
        $this->assertSame('super-secret-ai-key', $access->credentials['api_key']);
        $this->assertNull($access->prompt);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/ai-models', [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => 'دسترسی نمونه مجاز',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_model_accesses', [
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'دسترسی نمونه مجاز',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/settings/ai-models/'.$access->id.'/toggle')
            ->assertRedirect();

        $this->assertFalse($access->refresh()->is_active);

        $demo = AiModelAccess::query()
            ->where('model_key', 'sample_image')
            ->where('is_demo', true)
            ->firstOrFail();

        $this->assertFalse($demo->is_protected);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models/'.$demo->id.'/edit')
            ->assertOk()
            ->assertSee('value="sample_image"', false)
            ->assertSee('value="gapgpt"', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->put('/admin/settings/ai-models/'.$demo->id, [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => 'ویرایش مجاز دمو',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ai_model_accesses', [
            'id' => $demo->id,
            'name' => 'ویرایش مجاز دمو',
            'is_protected' => false,
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models/'.$demo->id)
            ->assertOk();

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $clinicUser->tenants()->firstOrFail()->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models')
            ->assertForbidden();
    }

    public function test_model_usage_reports_show_application_and_link_only_visible_leads(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $this->activateSubscription($clinicUser);
        $access = $tenant->aiModelAccesses()
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();
        $lead = Lead::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => '09121234567',
            'phone_normalized' => '09121234567',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);
        AiModelUsageLog::query()->create([
            'clinic_id' => $tenant->id,
            'lead_id' => $lead->id,
            'ai_model_access_id' => $access->id,
            'usage_category_key' => 'hair_transplant',
            'status' => 'completed',
            'credit_consumed' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $otherTenant = $this->createTenant('Clinic Other', 'other', 'other.hairvision-ai.test');
        $otherLead = Lead::query()->create([
            'tenant_id' => $otherTenant->id,
            'phone' => '09129876543',
            'phone_normalized' => '09129876543',
            'phone_verified_at' => now(),
            'status' => 'verified',
        ]);
        AiModelUsageLog::query()->create([
            'clinic_id' => $otherTenant->id,
            'lead_id' => $otherLead->id,
            'ai_model_access_id' => $access->id,
            'usage_category_key' => 'hair_transplant',
            'status' => 'completed',
            'credit_consumed' => 1,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get(route('admin.ai-models.show', $access, false))
            ->assertOk()
            ->assertSee(__('ai_models.usage_type'))
            ->assertSee(__('ai_models.usage_categories.hair_transplant'))
            ->assertSee('09121234567')
            ->assertSee('09129876543')
            ->assertSee(route('admin.leads.show', $lead), false);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get(route('admin.reports.credit-usage', absolute: false))
            ->assertOk()
            ->assertSee(__('ai_models.usage_type'))
            ->assertSee(__('ai_models.usage_categories.hair_transplant'))
            ->assertSee('09121234567')
            ->assertDontSee('09129876543')
            ->assertSee(route('admin.leads.show', $lead), false);

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get(route('admin.leads.show', $lead, false))
            ->assertOk();
    }

    public function test_assigned_active_model_is_used_for_generation_credit_and_logs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seedSampleImages();

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
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
            ->post('/admin/settings/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $access->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $uploadId = $this->createVerifiedUploadViaApi('arya.hairvision-ai.test');

        $groupId = $this->forHost('arya.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $uploadId])
            ->assertOk()
            ->assertJsonPath('generation_group.status', 'completed')
            ->json('generation_group.id');

        $this->assertSame(3, $access->refresh()->credit_used);
        $this->assertSame(3, $access->usage_count);
        $this->assertNotNull($access->last_used_at);
        $this->assertSame(3, AiModelUsageLog::query()->where('ai_model_access_id', $access->id)->where('status', 'completed')->count());
        $this->assertSame(3, AiModelUsageLog::query()
            ->where('ai_model_access_id', $access->id)
            ->where('status', 'completed')
            ->whereNotNull('lead_id')
            ->count());
        $this->assertSame(3, GenerationGroup::query()->where('uuid', $groupId)->firstOrFail()->jobs()->where('ai_model_access_id', $access->id)->count());
    }

    public function test_dashboard_topbar_uses_service_credit_balance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $clinicUser = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $subscription = $this->activateSubscription($clinicUser);
        $tenant = $clinicUser->tenants()->firstOrFail();

        $subscription->serviceEntitlements()->firstOrFail()->forceFill([
            'used_credit' => 4,
        ])->save();

        $this->actingAs($clinicUser)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.plan.remaining_credit', ['count' => number_format(96)]))
            ->assertDontSee(__('dashboard.plan.remaining_credit', ['count' => number_format(100)]));
    }

    public function test_clinic_can_have_multiple_active_model_assignments_and_route_by_priority_then_queue_capacity(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $manager = app(AiModelManager::class);

        DB::table('clinic_ai_model_access')
            ->where('clinic_id', $tenant->id)
            ->whereIn('ai_model_access_id', AiModelAccess::query()
                ->where('usage_category_key', 'hair_transplant')
                ->pluck('id'))
            ->update(['is_active' => false]);

        $hairAccessA = $this->createAccess([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT Hair A',
            'credentials' => ['api_key' => 'gap-a'],
            'is_demo' => false,
            'is_protected' => false,
            'priority' => 10,
        ]);

        $hairAccessB = $this->createAccess([
            'model_key' => 'gapgpt',
            'usage_category_key' => 'hair_transplant',
            'name' => 'GapGPT Hair B',
            'credentials' => ['api_key' => 'gap-b'],
            'is_demo' => false,
            'is_protected' => false,
            'priority' => 20,
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
            ->post('/admin/settings/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $hairAccessA->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $hairAccessB->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/clinics/'.$tenant->id.'/ai-models', [
                'ai_model_access_id' => $eyebrowAccess->id,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue((bool) $tenant->aiModelAccesses()->whereKey($hairAccessA->id)->firstOrFail()->pivot->is_active);
        $this->assertTrue((bool) $tenant->aiModelAccesses()->whereKey($hairAccessB->id)->firstOrFail()->pivot->is_active);
        $this->assertTrue((bool) $tenant->aiModelAccesses()->whereKey($eyebrowAccess->id)->firstOrFail()->pivot->is_active);
        $this->assertSame($hairAccessA->id, $manager->resolveForClinic($tenant, 'hair_transplant')->id);

        $upload = $this->createVerifiedUpload($tenant);
        $provider = AiProvider::query()->firstOrFail();
        $group = GenerationGroup::query()->create([
            'tenant_id' => $tenant->id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'status' => 'pending',
        ]);

        GenerationJob::query()->create([
            'generation_group_id' => $group->id,
            'tenant_id' => $tenant->id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'provider_id' => $provider->id,
            'ai_model_access_id' => $hairAccessA->id,
            'period' => 'final_result',
            'model_name' => 'gapgpt',
            'prompt' => 'test prompt',
            'status' => 'processing',
        ]);

        $this->assertSame($hairAccessB->id, $manager->resolveForClinic($tenant, 'hair_transplant')->id);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->patch('/admin/settings/clinics/'.$tenant->id.'/ai-models/'.$hairAccessB->id, [
                'ai_model_access_id' => $hairAccessB->id,
                'is_active' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $tenant->aiModelAccesses()->whereKey($hairAccessB->id)->firstOrFail()->pivot->is_active);
    }

    public function test_clinic_show_assign_model_modal_lists_active_models_and_disables_assigned_options(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();
        $assignedAccess = $tenant->aiModelAccesses()
            ->where('ai_model_accesses.is_active', true)
            ->firstOrFail();

        $addableAccess = $this->createAccess([
            'name' => 'مدل فعال قابل اتصال',
            'is_demo' => true,
            'is_active' => true,
        ]);

        $inactiveAccess = $this->createAccess([
            'name' => 'مدل غیرفعال قابل نمایش نیست',
            'is_active' => false,
        ]);

        $response = $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/clinics/'.$tenant->id);

        $response->assertOk()
            ->assertSee('data-admin-modal-trigger="assign-clinic-ai-model-modal"', false)
            ->assertSee('name="ai_model_access_id"', false)
            ->assertSee('value="'.$assignedAccess->id.'" disabled', false)
            ->assertSee(__('ai_models.already_assigned'))
            ->assertSee($addableAccess->name)
            ->assertDontSee($inactiveAccess->name);

        $this->assertStringNotContainsString(
            $addableAccess->name.' - '.__('ai_models.usage_categories.hair_transplant').' ('.__('ai_models.already_assigned').')',
            $response->getContent()
        );
    }

    public function test_unassigned_inactive_and_finished_credit_models_cannot_be_used(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $this->activateTenantSubscription($tenant);
        $upload = $this->createVerifiedUpload($tenant);
        $access = $this->createAccess(['credit_limit' => null]);

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('arya.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');

        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => false]);

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('arya.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');

        $tenant->aiModelAccesses()->updateExistingPivot($access->id, ['is_active' => true]);
        $access->forceFill(['is_active' => false])->save();

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('arya.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');

        $access->forceFill(['is_active' => true, 'credit_limit' => 0, 'credit_used' => 0])->save();

        $this->withSession(['lead_id' => $upload->lead_id])
            ->forHost('arya.hairvision-ai.test')
            ->postJson('/api/v1/generations', ['upload_id' => $upload->uuid])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ai_model_access');
    }

    public function test_failed_provider_response_does_not_consume_credit(): void
    {
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
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
        $tenant = $this->createTenant('Clinic One', 'arya', 'arya.hairvision-ai.test');
        $sampleImage = $this->seedSampleImages();

        $access = $this->createAccess([
            'is_demo' => true,
            'is_protected' => false,
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
            $this->assertSame($sampleImage, $response->output['binary_content'] ?? null);
            $this->assertSame('sample_image', $response->metadata['provider'] ?? null);
            $this->assertSame($stageKey, $response->metadata['stage_key'] ?? null);
        }

        $eyebrowAccess = $this->createAccess([
            'usage_category_key' => 'eyebrow_transplant',
            'is_demo' => true,
            'is_protected' => false,
            'credit_limit' => null,
        ]);
        $tenant->aiModelAccesses()->attach($eyebrowAccess->id, ['is_active' => true]);

        $eyebrowResponse = app(RunAiModelAction::class)->execute(
            $tenant,
            'eyebrow_transplant',
            ['period' => 'eyebrow_final_result'],
            selectedAccess: $eyebrowAccess,
        );

        $this->assertTrue($eyebrowResponse->successful);
        $this->assertSame($sampleImage, $eyebrowResponse->output['binary_content'] ?? null);
        $this->assertSame('eyebrow_final_result', $eyebrowResponse->metadata['stage_key'] ?? null);

        $access->refresh();

        $this->assertSame(0, $access->credit_used);
        $this->assertSame(3, $access->usage_count);
        $this->assertSame(1, $eyebrowAccess->refresh()->usage_count);
        $this->assertNotNull($access->last_used_at);
    }

    public function test_clinic_manager_creates_only_own_clinic_users_and_cannot_assign_system_roles(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->where('slug', 'arya')->firstOrFail();
        $otherTenant = $this->createTenant('Clinic Two', 'clinic2', 'clinic2.hairvision-ai.test');
        $clinicManager = User::query()->where('role', 'clinic_admin')->firstOrFail();
        $this->activateSubscription($clinicManager);

        $this->actingAs($clinicManager)
            ->withSession(['admin_active_tenant_id' => $tenant->id])
            ->forHost('hairvision-ai.test')
            ->post('/admin/security/users', [
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
            ->post('/admin/security/users', [
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
            ->post('/admin/security/users', [
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

        AiProvider::query()->firstOrCreate(
            ['code' => 'sample_image'],
            [
                'name' => 'Sample Image Provider',
                'type' => 'local',
                'is_active' => true,
                'supports_async' => false,
            ]
        );

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

    private function seedSampleImages(): string
    {
        $sampleImage = $this->patternedJpeg(1600, 800);

        foreach (['two-weeks.jpg', 'intermediate_regrowth.jpg', 'final_result.jpg', 'eyebrow_final_result.jpg'] as $filename) {
            Storage::disk('local')->put('sample-results/'.$filename, $sampleImage);
        }

        return $sampleImage;
    }

    private function patternedJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y += 20) {
            for ($x = 0; $x < $width; $x += 20) {
                $color = imagecolorallocate(
                    $image,
                    ($x * 13 + $y * 3) % 256,
                    ($x * 5 + $y * 17) % 256,
                    ($x * 19 + $y * 7) % 256,
                );
                imagefilledrectangle($image, $x, $y, min($x + 19, $width - 1), min($y + 19, $height - 1), $color);
            }
        }

        ob_start();
        imagejpeg($image, null, 95);
        $binary = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($binary);

        return $binary;
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
