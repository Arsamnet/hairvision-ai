<?php

namespace Tests\Feature;

use App\Models\AiModel;
use App\Models\AiModelAccess;
use App\Models\AiModelCategoryOutput;
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
        $this->assertSame('816x816', $definition['defaults']['size']);
        $this->assertSame('jpeg', $definition['defaults']['output_format']);
        $this->assertSame('opaque', $definition['defaults']['background']);
        $this->assertSame('auto', $definition['defaults']['moderation']);
        $this->assertSame(70, $definition['defaults']['output_compression']);

        $validated = $registry->validateSettings('openai', collect($definition['defaults'])
            ->except('concurrency_limit')
            ->all());
        $this->assertSame('low', $validated['quality']);

        $this->expectException(ValidationException::class);

        $registry->validateSettings('openai', collect($definition['defaults'])
            ->except('concurrency_limit')
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
            $this->assertSame('gpt-image-1.5', data_get($access->settings, 'model'));
            $this->assertSame('low', data_get($access->settings, 'quality'));
            $this->assertSame('816x816', data_get($access->settings, 'size'));
            $this->assertSame('jpeg', data_get($access->settings, 'output_format'));
            $this->assertSame('opaque', data_get($access->settings, 'background'));
            $this->assertSame('auto', data_get($access->settings, 'moderation'));
            $this->assertSame(70, data_get($access->settings, 'output_compression'));
            $this->assertSame(900, data_get($access->settings, 'timeout_seconds'));
            $this->assertSame(30, data_get($access->settings, 'connect_timeout_seconds'));
            $this->assertTrue(data_get($access->settings, InputImageProcessingSettings::ENABLED));
        });

        $this->assertSame(2, AiModel::query()->where('type', 'openai')->where('status', 'active')->count());

        AiModel::query()
            ->where('type', 'openai')
            ->with(['category.outputs', 'outputConfigs.output'])
            ->get()
            ->each(function (AiModel $model): void {
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
}
