<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiModelAccess;
use App\Models\AiModelCategoryOutput;
use App\Models\AiProvider;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiModelTypeRegistry;
use App\Services\AI\InputImageProcessingSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Verifies OpenAI model registration and seeded executable defaults.
 *
 * @since 2.0.2
 */
class OpenAiModelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the public type registry exposes safe OpenAI fields and validates quality choices.
     *
     * @return void No value is returned.
     *
     * @since 2.0.2
     */
    public function test_openai_type_registry_exposes_safe_defaults_and_three_quality_modes(): void
    {
        $registry = app(AiModelTypeRegistry::class);
        $definition = $registry->publicDefinition('openai');

        $this->assertSame('openai', $definition['key']);
        $this->assertArrayNotHasKey('provider', $definition);
        $this->assertSame('secret', $definition['credentials'][0]['type']);
        $this->assertSame(['low', 'medium', 'high'], collect($definition['settings'])->firstWhere('key', 'quality')['options']);
        $this->assertNotContains('response_format', collect($definition['settings'])->pluck('key')->all());
        $this->assertSame('816x816', $definition['defaults']['size']);
        $this->assertSame('jpeg', $definition['defaults']['output_format']);
        $this->assertArrayNotHasKey('response_format', $definition['defaults']);
        $this->assertSame('opaque', $definition['defaults']['background']);
        $this->assertSame('auto', $definition['defaults']['moderation']);
        $this->assertSame(70, $definition['defaults']['output_compression']);
        $this->assertSame(1000, $definition['defaults']['timeout_seconds']);
        $this->assertSame(50, $definition['defaults']['connect_timeout_seconds']);
        $this->assertSame(2, $definition['defaults']['concurrency_limit']);
        $this->assertSame(
            2,
            collect($definition['settings'])->firstWhere('key', 'concurrency_limit')['default']
        );

        $validated = $registry->validateSettings('openai', $definition['defaults']);
        $this->assertSame('low', $validated['quality']);
        $this->assertSame(2, $validated['concurrency_limit']);

        $this->expectException(ValidationException::class);

        $registry->validateSettings('openai', collect($definition['defaults'])
            ->merge(['quality' => 'auto'])
            ->all());
    }

    /**
     * Ensure seed data creates category-specific OpenAI accesses and prompt-backed v2 models.
     *
     * @return void No value is returned.
     *
     * @since 2.0.2
     */
    public function test_seeder_creates_openai_accesses_with_requested_defaults_and_output_prompts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $accesses = AiModelAccess::query()
            ->where('model_key', 'openai')
            ->orderBy('usage_category_key')
            ->get();

        $this->assertSame([
            'eyebrow_transplant' => 'مدل OpenAI کاشت ابرو',
            'hair_transplant' => 'مدل OpenAI کاشت مو',
        ], $accesses->pluck('name', 'usage_category_key')->all());

        $accesses->each(function (AiModelAccess $access): void {
            $this->assertTrue($access->is_active);
            $this->assertNotEmpty($access->credentials['api_key'] ?? null);
            $this->assertSame('https://api.openai.com/v1/images/edits', data_get($access->settings, 'endpoint_url'));
            $this->assertSame('gpt-image-2', data_get($access->settings, 'model'));
            $this->assertSame('low', data_get($access->settings, 'quality'));
            $this->assertSame('816x816', data_get($access->settings, 'size'));
            $this->assertSame('jpeg', data_get($access->settings, 'output_format'));
            $this->assertArrayNotHasKey('response_format', $access->settings ?? []);
            $this->assertSame('opaque', data_get($access->settings, 'background'));
            $this->assertSame('auto', data_get($access->settings, 'moderation'));
            $this->assertSame(70, data_get($access->settings, 'output_compression'));
            $this->assertSame(1000, data_get($access->settings, 'timeout_seconds'));
            $this->assertSame(50, data_get($access->settings, 'connect_timeout_seconds'));
            $this->assertSame(2, data_get($access->settings, 'concurrency_limit'));
            $this->assertTrue(data_get($access->settings, InputImageProcessingSettings::ENABLED));
        });

        $this->assertSame(2, AiModel::query()->where('type', 'openai')->where('status', 'active')->count());

        AiModel::query()
            ->where('type', 'openai')
            ->with(['category.outputs', 'outputConfigs.output'])
            ->get()
            ->each(function (AiModel $model): void {
                $this->assertSame(2, $model->concurrency_limit);
                $expectedPrompts = AiModelCategoryOutput::query()
                    ->where('ai_model_category_id', $model->ai_model_category_id)
                    ->pluck('prompt', 'id');

                $model->outputConfigs->each(function ($outputConfig) use ($expectedPrompts): void {
                    $this->assertSame(
                        $expectedPrompts[$outputConfig->ai_model_category_output_id],
                        data_get($outputConfig->configuration, 'prompt'),
                    );
                });
            });
    }

    /**
     * Ensure dashboard updates accept larger timeouts and render a localized error only once.
     *
     * @return void No value is returned.
     *
     * @since 2.0.3
     */
    public function test_openai_edit_accepts_timeout_above_previous_limit_and_shows_one_localized_error(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('role', 'super_admin')->firstOrFail();
        $access = AiModelAccess::query()
            ->where('model_key', 'openai')
            ->where('usage_category_key', 'hair_transplant')
            ->firstOrFail();
        $editUrl = '/admin/settings/ai-models/'.$access->id.'/edit';
        $settings = $access->settings ?? [];
        $settings['timeout_seconds'] = 1800;
        $settings['response_format'] = 'url';
        $this->app['url']->forceRootUrl('http://hairvision-ai.test');
        $this->withServerVariables([
            'HTTP_HOST' => 'hairvision-ai.test',
            'SERVER_NAME' => 'hairvision-ai.test',
        ]);

        $this->actingAs($admin)
            ->put('/admin/settings/ai-models/'.$access->id, [
                '_form' => 'edit_ai_model_access',
                'model_key' => $access->model_key,
                'usage_category_key' => $access->usage_category_key,
                'name' => $access->name,
                'settings' => $settings,
                'priority' => $access->priority,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $persistedSettings = $access->refresh()->settings ?? [];
        $this->assertSame(1800, data_get($persistedSettings, 'timeout_seconds'));
        $this->assertArrayNotHasKey('response_format', $persistedSettings);

        $settings['timeout_seconds'] = 'نامعتبر';

        $response = $this->actingAs($admin)
            ->from($editUrl)
            ->followingRedirects()
            ->put('/admin/settings/ai-models/'.$access->id, [
                '_form' => 'edit_ai_model_access',
                'model_key' => $access->model_key,
                'usage_category_key' => $access->usage_category_key,
                'name' => $access->name,
                'settings' => $settings,
                'priority' => $access->priority,
                'is_active' => '1',
            ])
            ->assertOk();

        $message = __('validation.integer', ['attribute' => __('ai_models.timeout_seconds')]);

        $this->assertSame(1, substr_count($response->getContent(), $message));
    }

    /**
     * Ensure all seeded plan and clinic execution paths use OpenAI exclusively.
     *
     * @return void No value is returned.
     *
     * @since 2.0.3
     */
    public function test_seeded_plans_providers_models_and_clinic_assignments_use_only_openai(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['openai'], AiProvider::query()
            ->where('is_active', true)
            ->pluck('code')
            ->sort()
            ->values()
            ->all());
        $this->assertSame(0, AiModelAccess::query()
            ->where('model_key', '!=', 'openai')
            ->where('is_active', true)
            ->count());
        $this->assertSame(0, AiModel::query()
            ->where('type', '!=', 'openai')
            ->where('status', 'active')
            ->count());

        Plan::query()
            ->with(['serviceEntitlements', 'aiModelAccesses'])
            ->get()
            ->each(function (Plan $plan): void {
                if ($plan->isCreditTopUp()) {
                    $this->assertCount(0, $plan->aiModelAccesses);

                    return;
                }

                $this->assertCount(count($plan->serviceKeys()), $plan->aiModelAccesses);
                $this->assertSame(['openai'], $plan->aiModelAccesses
                    ->pluck('model_key')
                    ->unique()
                    ->values()
                    ->all());
                $this->assertEqualsCanonicalizing(
                    $plan->serviceKeys(),
                    $plan->aiModelAccesses->pluck('usage_category_key')->all(),
                );
            });

        Tenant::query()
            ->whereIn('slug', ['arya', 'noja', 'demo', 'silversands'])
            ->get()
            ->each(function (Tenant $tenant): void {
                $connectedModelKeys = $tenant->aiModelAccesses()
                    ->pluck('model_key')
                    ->unique()
                    ->values()
                    ->all();

                $this->assertSame(['openai'], $connectedModelKeys);
                $this->assertSame(0, $tenant->aiModelAccesses()
                    ->wherePivot('is_active', false)
                    ->count());
            });

        Subscription::query()
            ->where('status', 'active')
            ->with(['tenant.aiModelAccesses', 'plan.aiModelAccesses', 'invoice'])
            ->get()
            ->each(function (Subscription $subscription): void {
                $expectedModelIds = $subscription->plan->aiModelAccesses
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                $connectedClinicModelIds = $subscription->tenant->aiModelAccesses()
                    ->pluck('ai_model_accesses.id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();
                $invoiceModelIds = collect($subscription->invoice->aiModelAccessIdsSnapshot())
                    ->map(fn (mixed $id): int => (int) $id)
                    ->sort()
                    ->values()
                    ->all();

                $this->assertSame($expectedModelIds, $connectedClinicModelIds);
                $this->assertSame($expectedModelIds, $invoiceModelIds);
                $this->assertSame(['openai'], $subscription->plan->aiModelAccesses
                    ->pluck('model_key')
                    ->unique()
                    ->values()
                    ->all());
            });
    }
}
