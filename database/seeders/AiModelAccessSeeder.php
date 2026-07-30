<?php

namespace Database\Seeders;

use App\Models\AiModelAccess;
use App\Models\AiModelCategory;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AI\ExecutableAiModelSyncService;
use App\Services\AI\InputImageProcessingSettings;
use Illuminate\Database\Seeder;

/**
 * Provides ai model access seeder behavior.
 *
 * @since 1.0.0
 */
class AiModelAccessSeeder extends Seeder
{
    /**
     * Execute the ai model access seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        $this->seedCategoryOutputs();

        $superAdmin = User::query()->where('role', 'super_admin')->firstOrFail();

        $demoAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'sample_image',
                'usage_category_key' => 'hair_transplant',
                'name' => __('ai_models.seed.demo_access_name'),
            ],
            [
                'description' => __('ai_models.seed.demo_access_description'),
                'credentials' => [],
                'settings' => InputImageProcessingSettings::defaults(),
                'prompt' => null,
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => false,
                'is_demo' => true,
                'is_protected' => false,
                'priority' => 100,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $eyebrowDemoAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'sample_image',
                'usage_category_key' => 'eyebrow_transplant',
                'name' => __('ai_models.seed.eyebrow_demo_access_name'),
            ],
            [
                'description' => __('ai_models.seed.eyebrow_demo_access_description'),
                'credentials' => [],
                'settings' => [
                    ...InputImageProcessingSettings::defaults(),
                    'demo_images' => [
                        'eyebrow_final_result' => config('hairvision.sample_images.eyebrow_final_result'),
                    ],
                ],
                'prompt' => null,
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => false,
                'is_demo' => true,
                'is_protected' => false,
                'priority' => 100,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $gapGptModelConfig = config('ai-models.models.gapgpt', []);
        $hairGapGptAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'hair_transplant',
                'name' => __('ai_models.seed.gapgpt_access_name'),
            ],
            [
                'description' => __('ai_models.seed.gapgpt_access_description'),
                'credentials' => [
                    'api_key' => env('HAIRVISION_DEMO_GAPGPT_API_KEY', 'seed-demo-gapgpt-api-key'),
                ],
                'settings' => [
                    ...InputImageProcessingSettings::defaults(),
                    'endpoint_url' => $gapGptModelConfig['endpoint'] ?? 'https://api.gapgpt.app/v1/images/edits',
                    'model' => $gapGptModelConfig['default_model'] ?? 'gpt-image-2',
                    'size' => $gapGptModelConfig['default_size'] ?? '1024x1024',
                    'response_format' => $gapGptModelConfig['default_response_format'] ?? 'url',
                    'timeout_seconds' => $gapGptModelConfig['default_timeout'] ?? 900,
                    'connect_timeout_seconds' => $gapGptModelConfig['default_connect_timeout'] ?? 30,
                ],
                'prompt' => null,
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => false,
                'is_demo' => false,
                'is_protected' => false,
                'priority' => 100,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $eyebrowGapGptAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'eyebrow_transplant',
                'name' => __('ai_models.seed.eyebrow_gapgpt_access_name'),
            ],
            [
                'description' => __('ai_models.seed.eyebrow_gapgpt_access_description'),
                'credentials' => [
                    'api_key' => env('HAIRVISION_DEMO_GAPGPT_API_KEY', 'seed-demo-gapgpt-api-key'),
                ],
                'settings' => [
                    ...InputImageProcessingSettings::defaults(),
                    'endpoint_url' => $gapGptModelConfig['endpoint'] ?? 'https://api.gapgpt.app/v1/images/edits',
                    'model' => $gapGptModelConfig['default_model'] ?? 'gpt-image-2',
                    'size' => $gapGptModelConfig['default_size'] ?? '1024x1024',
                    'response_format' => $gapGptModelConfig['default_response_format'] ?? 'url',
                    'timeout_seconds' => $gapGptModelConfig['default_timeout'] ?? 900,
                    'connect_timeout_seconds' => $gapGptModelConfig['default_connect_timeout'] ?? 30,
                ],
                'prompt' => null,
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => false,
                'is_demo' => false,
                'is_protected' => false,
                'priority' => 100,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $openAiModelConfig = config('ai-models.models.openai', []);
        $openAiSettings = [
            ...InputImageProcessingSettings::defaults(),
            'endpoint_url' => $openAiModelConfig['endpoint'] ?? 'https://api.openai.com/v1/images/edits',
            'model' => $openAiModelConfig['default_model'] ?? 'gpt-image-2',
            'quality' => $openAiModelConfig['default_quality'] ?? 'low',
            'size' => $openAiModelConfig['default_size'] ?? '816x816',
            'output_format' => $openAiModelConfig['default_output_format'] ?? 'jpeg',
            'background' => $openAiModelConfig['default_background'] ?? 'opaque',
            'moderation' => $openAiModelConfig['default_moderation'] ?? 'auto',
            'output_compression' => $openAiModelConfig['default_output_compression'] ?? 70,
            'timeout_seconds' => $openAiModelConfig['default_timeout'] ?? 1000,
            'connect_timeout_seconds' => $openAiModelConfig['default_connect_timeout'] ?? 50,
            'concurrency_limit' => max(1, (int) config('ai-models.types.openai.defaults.concurrency_limit', 2)),
        ];
        $hairOpenAiAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'openai',
                'usage_category_key' => 'hair_transplant',
                'name' => __('ai_models.seed.openai_access_name'),
            ],
            [
                'description' => __('ai_models.seed.openai_access_description'),
                'credentials' => [
                    'api_key' => env('HAIRVISION_DEMO_OPENAI_API_KEY', 'seed-demo-openai-api-key'),
                ],
                'settings' => $openAiSettings,
                'prompt' => null,
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => true,
                'is_demo' => false,
                'is_protected' => false,
                'priority' => 1,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $eyebrowOpenAiAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'openai',
                'usage_category_key' => 'eyebrow_transplant',
                'name' => __('ai_models.seed.eyebrow_openai_access_name'),
            ],
            [
                'description' => __('ai_models.seed.eyebrow_openai_access_description'),
                'credentials' => [
                    'api_key' => env('HAIRVISION_DEMO_OPENAI_API_KEY', 'seed-demo-openai-api-key'),
                ],
                'settings' => $openAiSettings,
                'prompt' => null,
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => true,
                'is_demo' => false,
                'is_protected' => false,
                'priority' => 1,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $executableModels = app(ExecutableAiModelSyncService::class);
        $executableModels->sync($demoAccess, $superAdmin);
        $executableModels->sync($eyebrowDemoAccess, $superAdmin);
        $executableModels->sync($hairGapGptAccess, $superAdmin);
        $executableModels->sync($eyebrowGapGptAccess, $superAdmin);
        $executableModels->sync($hairOpenAiAccess, $superAdmin);
        $executableModels->sync($eyebrowOpenAiAccess, $superAdmin);

        Tenant::query()
            ->whereIn('slug', ['arya', 'noja', 'demo', 'silversands'], 'and', false)
            ->get()
            ->each(fn (Tenant $tenant): int => $tenant->aiModelAccesses()->detach());

        $this->assignSeededPlanModels($hairOpenAiAccess, $eyebrowOpenAiAccess);
    }

    /**
     * Assign seeded plan models.
     *
     * @param  AiModelAccess  $openAiHairAccess  The OpenAI hair-transplant access value.
     * @param  AiModelAccess  $openAiEyebrowAccess  The OpenAI eyebrow-transplant access value.
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function assignSeededPlanModels(
        AiModelAccess $openAiHairAccess,
        AiModelAccess $openAiEyebrowAccess,
    ): void {
        $accessIdsByService = [
            'hair_transplant' => $openAiHairAccess->id,
            'eyebrow_transplant' => $openAiEyebrowAccess->id,
        ];

        Plan::query()
            ->with('serviceEntitlements')
            ->get()
            ->each(function (Plan $plan) use ($accessIdsByService): void {
                if ($plan->isCreditTopUp()) {
                    $plan->aiModelAccesses()->sync([]);

                    return;
                }

                $accessIds = collect($plan->serviceKeys())
                    ->map(fn (string $serviceKey): ?int => $accessIdsByService[$serviceKey] ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $plan->aiModelAccesses()->sync($accessIds);
            });
    }

    /**
     * Execute the seed category outputs operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    private function seedCategoryOutputs(): void
    {
        collect($this->categoryOutputDefinitions())
            ->groupBy('category')
            ->each(function ($definitions, string $categoryCode): void {
                $category = AiModelCategory::query()->where('code', $categoryCode)->first();

                if (! $category) {
                    return;
                }

                $definitions->each(function (array $definition) use ($category): void {
                    $category->outputs()->updateOrCreate(
                        ['code' => $definition['code']],
                        collect($definition)->except(['category', 'code'])->all(),
                    );
                });
            });
    }

    /**
     * @return array<int, array{
     *     category: string,
     *     code: string,
     *     name: string,
     *     description: string,
     *     prompt: string
     * }>
     *
     * @since 1.0.0
     */
    private function categoryOutputDefinitions(): array
    {
        return [
            [
                'category' => 'hair_transplant',
                'code' => 'two_weeks',
                'name' => 'یک روز بعد از کاشت مو',
                'description' => 'نمای طبیعی روز اول با گرافت‌های تازه، قرمزی ملایم و ریزدلمه‌های قابل‌انتظار',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 day after a complete hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Reconstruct a natural, age-appropriate hairline and fully implant all necessary bald or thinning recipient areas, including the frontal hairline, temples, frontal scalp, mid-scalp, and top. Do not limit the transplant to existing hair. Show a realistic day-one result: closely shaved recipient areas, densely placed visible grafts, tiny red implantation dots, mild redness, minimal swelling, and fresh micro-scabbing. The graft pattern must clearly cover the previously bald areas, but must remain medically believable and not overly graphic. Do not show grown or dense hair at this stage. Match the person’s natural hair color and texture. Ultra-realistic, high-resolution, unedited documentary medical-photo style. The only difference from the source image must be the newly transplanted scalp and reconstructed hairline.
PROMPT,
            ],
            [
                'category' => 'hair_transplant',
                'code' => 'intermediate_regrowth',
                'name' => '۶ ماه بعد از کاشت مو',
                'description' => 'رشد میانی شش‌ماهه با خط رویش شکل‌گرفته، تراکم ناکامل و نمایان بودن طبیعی بخشی از پوست سر',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 6 months after a complete hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Show intermediate regrowth across all transplanted areas, including the reconstructed frontal hairline, temples, frontal scalp, mid-scalp, and top. New hair must extend into previously bald areas rather than only thickening the original hair. The wounds, redness, and scabs must be fully healed. The established hairline should be visible but still sparse, short, thin, slightly uneven, and incomplete. Keep some scalp visible and avoid final-stage density. Match the person’s natural hair color, direction, and texture. Ultra-realistic, high-resolution, unedited documentary style. The only difference from the source image must be the realistic 6-month hair-transplant growth.
PROMPT,
            ],
            [
                'category' => 'hair_transplant',
                'code' => 'final_result',
                'name' => 'یک سال بعد از کاشت مو',
                'description' => 'نتیجه کامل یک‌ساله با خط رویش طبیعی، پوشش متناسب و تراکم باورپذیر',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 year after a complete and successful hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Naturally restore all previously bald or thinning areas, including the frontal hairline, temples, frontal scalp, mid-scalp, and top. Do not merely thicken the remaining original hair. Create a natural, age-appropriate hairline proportional to the person’s face. Show fully grown, healthy, naturally fuller hair with realistic density, direction, color, and texture matching the original person. The transplanted areas should no longer appear bald, while retaining slight natural irregularity and subtle scalp visibility where appropriate. Avoid excessive density, sharp artificial hairlines, perfect symmetry, helmet hair, or a wig-like appearance. Ultra-realistic, high-resolution, unedited documentary style. The only difference from the source image must be the natural final hair-transplant result.
PROMPT,
            ],
            [
                'category' => 'eyebrow_transplant',
                'code' => 'eyebrow_final_result',
                'name' => 'یک سال بعد از کاشت ابرو',
                'description' => 'نتیجه کامل یک‌ساله با تراکم، جهت رشد و عدم تقارن طبیعی ابروها',
                'prompt' => <<<'PROMPT'
Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 year after a successful eyebrow transplant. Modify ONLY the eyebrows. Preserve the exact identity, face, age, skin tone, eyes, expression, pose, camera angle, framing, clothing, head hair, facial hair, background, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Create naturally fuller, well-defined, age-appropriate eyebrows proportional to the face. Match the person’s natural eyebrow color and texture, with realistic individual hairs and growth direction: slightly upward at the inner brow, gradually angled outward through the arch and tail. Keep subtle irregularity and natural asymmetry. The transplant must appear fully healed, with no redness, scabs, swelling, or visible procedure marks. Avoid excessive thickness, sharp edges, perfect symmetry, painted-on makeup, tattooed or microbladed appearance, and artificial density. Ultra-realistic, high-resolution, unedited documentary-photo style. The only difference from the source image must be the realistic 1-year eyebrow-transplant result.
PROMPT,
            ],
        ];
    }
}
