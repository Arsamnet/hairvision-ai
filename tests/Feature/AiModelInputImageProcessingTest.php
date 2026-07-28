<?php

namespace Tests\Feature;

use App\Actions\Ai\RunAiModelAction;
use App\Contracts\Ai\AiModelProviderInterface;
use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;
use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\AiModelTypeRegistry;
use App\Services\AI\InputImageProcessingSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AiModelInputImageProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        CapturingImageProvider::$capture = [];

        config([
            'hairvision.api_image.max_dimension' => 800,
            'hairvision.api_image.jpeg_quality' => 85,
            'ai-models.models.capturing_image' => [
                'key' => 'capturing_image',
                'driver' => CapturingImageProvider::class,
                'supported_usage_categories' => ['*'],
                'required_credentials' => [],
            ],
        ]);
    }

    public function test_every_standard_model_provider_receives_the_processed_jpeg_when_enabled(): void
    {
        $tenant = Tenant::factory()->create();
        $source = $this->patternedPng(900, 450);
        Storage::disk('local')->put('uploads/source.png', $source);

        $access = $this->assignedAccess($tenant, [
            InputImageProcessingSettings::ENABLED => true,
            InputImageProcessingSettings::MAX_DIMENSION => 360,
            InputImageProcessingSettings::JPEG_QUALITY => 67,
        ]);

        $response = app(RunAiModelAction::class)->execute(
            clinic: $tenant,
            usageCategoryKey: 'hair_transplant',
            payload: [
                'upload_disk' => 'local',
                'upload_path' => 'uploads/source.png',
            ],
            selectedAccess: $access,
        );

        $this->assertTrue($response->successful);
        $this->assertSame('image/jpeg', CapturingImageProvider::$capture['mime']);
        $this->assertSame([360, 180], CapturingImageProvider::$capture['dimensions']);
        $this->assertStringEndsWith('.jpg', CapturingImageProvider::$capture['path']);
        $this->assertSame($source, Storage::disk('local')->get('uploads/source.png'));
        Storage::disk('local')->assertMissing(CapturingImageProvider::$capture['path']);
    }

    public function test_product_defaults_send_an_800_pixel_jpeg_at_quality_85(): void
    {
        $tenant = Tenant::factory()->create();
        Storage::disk('local')->put('uploads/default-source.png', $this->patternedPng(1200, 600));
        $access = $this->assignedAccess($tenant, InputImageProcessingSettings::defaults());

        $response = app(RunAiModelAction::class)->execute(
            clinic: $tenant,
            usageCategoryKey: 'hair_transplant',
            payload: [
                'upload_disk' => 'local',
                'upload_path' => 'uploads/default-source.png',
            ],
            selectedAccess: $access,
        );

        $this->assertTrue($response->successful);
        $this->assertSame('image/jpeg', CapturingImageProvider::$capture['mime']);
        $this->assertSame([800, 400], CapturingImageProvider::$capture['dimensions']);
        $this->assertSame(85, InputImageProcessingSettings::defaults()[InputImageProcessingSettings::JPEG_QUALITY]);
    }

    public function test_provider_receives_the_original_image_when_processing_is_disabled(): void
    {
        $tenant = Tenant::factory()->create();
        Storage::disk('local')->put('uploads/source.png', $this->patternedPng(320, 160));
        $access = $this->assignedAccess($tenant, [InputImageProcessingSettings::ENABLED => false]);

        $response = app(RunAiModelAction::class)->execute(
            clinic: $tenant,
            usageCategoryKey: 'hair_transplant',
            payload: [
                'upload_disk' => 'local',
                'upload_path' => 'uploads/source.png',
            ],
            selectedAccess: $access,
        );

        $this->assertTrue($response->successful);
        $this->assertSame('image/png', CapturingImageProvider::$capture['mime']);
        $this->assertSame([320, 160], CapturingImageProvider::$capture['dimensions']);
        $this->assertSame('uploads/source.png', CapturingImageProvider::$capture['path']);
    }

    public function test_admin_requires_dimension_and_quality_only_when_processing_is_enabled(): void
    {
        $this->seed(DatabaseSeeder::class);
        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $basePayload = [
            'model_key' => 'sample_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'مدل پردازش تصویر تست',
            'is_active' => '1',
            'settings' => [InputImageProcessingSettings::ENABLED => '1'],
        ];

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/ai-models', $basePayload)
            ->assertSessionHasErrors([
                'settings.'.InputImageProcessingSettings::MAX_DIMENSION,
                'settings.'.InputImageProcessingSettings::JPEG_QUALITY,
            ]);

        $basePayload['settings'] = [
            InputImageProcessingSettings::ENABLED => '1',
            InputImageProcessingSettings::MAX_DIMENSION => '720',
            InputImageProcessingSettings::JPEG_QUALITY => '81',
        ];

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->post('/admin/settings/ai-models', $basePayload)
            ->assertRedirect();

        $access = AiModelAccess::query()->where('name', 'مدل پردازش تصویر تست')->firstOrFail();

        $this->assertTrue(data_get($access->settings, InputImageProcessingSettings::ENABLED));
        $this->assertSame(720, data_get($access->settings, InputImageProcessingSettings::MAX_DIMENSION));
        $this->assertSame(81, data_get($access->settings, InputImageProcessingSettings::JPEG_QUALITY));

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models/'.$access->id.'/edit')
            ->assertOk()
            ->assertSee('data-input-image-processing-toggle', false)
            ->assertSee('value="720"', false)
            ->assertSee('value="81"', false);

        $this->actingAs($superAdmin)
            ->forHost('hairvision-ai.test')
            ->get('/admin/settings/ai-models/'.$access->id)
            ->assertOk()
            ->assertSee(__('ai_models.input_image_processing_title'))
            ->assertSee('720')
            ->assertSee('81');
    }

    public function test_type_registry_exposes_and_validates_processing_settings_for_every_model_type(): void
    {
        $registry = app(AiModelTypeRegistry::class);

        foreach (['sample_image', 'gapgpt'] as $type) {
            $settings = collect($registry->publicDefinition($type)['settings'])->keyBy('key');
            $keys = $settings->keys();

            $this->assertContains(InputImageProcessingSettings::ENABLED, $keys);
            $this->assertContains(InputImageProcessingSettings::MAX_DIMENSION, $keys);
            $this->assertContains(InputImageProcessingSettings::JPEG_QUALITY, $keys);
            $this->assertTrue($settings->get(InputImageProcessingSettings::ENABLED)['default']);
            $this->assertSame(800, $settings->get(InputImageProcessingSettings::MAX_DIMENSION)['default']);
            $this->assertSame(85, $settings->get(InputImageProcessingSettings::JPEG_QUALITY)['default']);
        }

        try {
            $registry->validateSettings('sample_image', [InputImageProcessingSettings::ENABLED => true]);
            $this->fail('Enabled image processing without values should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(InputImageProcessingSettings::MAX_DIMENSION, $exception->errors());
            $this->assertArrayHasKey(InputImageProcessingSettings::JPEG_QUALITY, $exception->errors());
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function assignedAccess(Tenant $tenant, array $settings): AiModelAccess
    {
        $access = AiModelAccess::query()->create([
            'model_key' => 'capturing_image',
            'usage_category_key' => 'hair_transplant',
            'name' => 'Capturing image model',
            'credentials' => [],
            'settings' => $settings,
            'credit_limit' => null,
            'is_active' => true,
            'priority' => 1,
        ]);

        $tenant->aiModelAccesses()->attach($access->id, ['is_active' => true]);

        return $access;
    }

    private function patternedPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y += 10) {
            for ($x = 0; $x < $width; $x += 10) {
                $color = imagecolorallocate(
                    $image,
                    ($x * 13 + $y * 3) % 256,
                    ($x * 5 + $y * 17) % 256,
                    ($x * 19 + $y * 7) % 256,
                );
                imagefilledrectangle($image, $x, $y, min($x + 9, $width - 1), min($y + 9, $height - 1), $color);
            }
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($binary);

        return $binary;
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

class CapturingImageProvider implements AiModelProviderInterface
{
    /** @var array<string, mixed> */
    public static array $capture = [];

    public function run(AiModelRequestData $request): AiModelResponseData
    {
        $disk = (string) data_get($request->payload, 'upload_disk');
        $path = (string) data_get($request->payload, 'upload_path');
        $binary = Storage::disk($disk)->get($path);
        $imageInfo = getimagesizefromstring($binary);

        self::$capture = [
            'path' => $path,
            'mime' => $imageInfo['mime'],
            'dimensions' => [$imageInfo[0], $imageInfo[1]],
        ];

        return new AiModelResponseData(
            successful: true,
            status: 'completed',
            metadata: ['provider' => 'capturing_image'],
            providerReference: 'captured',
            creditConsumed: 1,
        );
    }
}
