<?php

namespace Database\Seeders;

use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiModelAccessSeeder extends Seeder
{
    public function run(): void
    {
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
                'settings' => [],
                'prompt' => __('ai_models.seed.demo_prompt'),
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => true,
                'is_demo' => true,
                'is_protected' => true,
                'priority' => 1,
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
                    'demo_images' => [
                        'eyebrow_final_result' => config('hairvision.sample_images.eyebrow_final_result'),
                    ],
                ],
                'prompt' => __('ai_models.seed.eyebrow_demo_prompt'),
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => true,
                'is_demo' => true,
                'is_protected' => true,
                'priority' => 1,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $gapGptModelConfig = config('ai-models.models.gapgpt', []);
        $gapGptAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'hair_transplant',
                'name' => __('ai_models.seed.gapgpt_access_name'),
            ],
            [
                'description' => __('ai_models.seed.gapgpt_access_description'),
                'credentials' => [],
                'settings' => [
                    'endpoint_url' => $gapGptModelConfig['endpoint'] ?? 'https://api.gapgpt.app/v1/images/edits',
                    'model' => $gapGptModelConfig['default_model'] ?? 'gpt-image-2',
                    'size' => $gapGptModelConfig['default_size'] ?? '1024x1024',
                    'response_format' => $gapGptModelConfig['default_response_format'] ?? 'url',
                    'timeout_seconds' => $gapGptModelConfig['default_timeout'] ?? 900,
                    'connect_timeout_seconds' => $gapGptModelConfig['default_connect_timeout'] ?? 30,
                    'stage_prompts' => $this->gapGptStagePrompts(),
                ],
                'prompt' => '{stage_prompt}',
                'credit_limit' => null,
                'credit_used' => 0,
                'usage_count' => 0,
                'is_active' => false,
                'is_demo' => false,
                'is_protected' => false,
                'priority' => 10,
                'created_by' => $superAdmin->id,
                'updated_by' => $superAdmin->id,
            ]
        );

        $demoGapGptAccess = AiModelAccess::query()->updateOrCreate(
            [
                'model_key' => 'gapgpt',
                'usage_category_key' => 'hair_transplant',
                'name' => 'GapGPT واقعی کلینیک دمو',
            ],
            [
                'description' => 'دسترسی فعال GapGPT برای کلینیک دمو',
                'credentials' => [
                    'api_key' => env('HAIRVISION_DEMO_GAPGPT_API_KEY', 'seed-demo-gapgpt-api-key'),
                ],
                'settings' => [
                    'endpoint_url' => $gapGptModelConfig['endpoint'] ?? 'https://api.gapgpt.app/v1/images/edits',
                    'model' => $gapGptModelConfig['default_model'] ?? 'gpt-image-2',
                    'size' => $gapGptModelConfig['default_size'] ?? '1024x1024',
                    'response_format' => $gapGptModelConfig['default_response_format'] ?? 'url',
                    'timeout_seconds' => $gapGptModelConfig['default_timeout'] ?? 900,
                    'connect_timeout_seconds' => $gapGptModelConfig['default_connect_timeout'] ?? 30,
                    'stage_prompts' => $this->gapGptStagePrompts(),
                ],
                'prompt' => '{stage_prompt}',
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

        Tenant::query()
            ->whereIn('slug', ['aria', 'noja'], 'and', false)
            ->get()
            ->each(function (Tenant $tenant) use ($demoAccess, $eyebrowDemoAccess, $gapGptAccess, $superAdmin): void {
                $tenant->aiModelAccesses()->syncWithoutDetaching([
                    $demoAccess->id => [
                        'is_active' => true,
                        'assigned_by' => $superAdmin->id,
                    ],
                    $eyebrowDemoAccess->id => [
                        'is_active' => true,
                        'assigned_by' => $superAdmin->id,
                    ],
                    $gapGptAccess->id => [
                        'is_active' => false,
                        'assigned_by' => $superAdmin->id,
                    ],
                ]);
            });

        $demoTenant = Tenant::query()->where('slug', 'demo')->first();

        if ($demoTenant) {
            $demoTenant->aiModelAccesses()->syncWithoutDetaching([
                $demoGapGptAccess->id => [
                    'is_active' => true,
                    'assigned_by' => $superAdmin->id,
                ],
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function gapGptStagePrompts(): array
    {
        return [
            'two_weeks' => <<<'PROMPT'
Use the uploaded photo as the direct reference and generate exactly one ultra-realistic photo edit of the same person, showing the person two weeks after a complete and successful hair transplant.

Overall objective:
Create a realistic 2-week post-hair-transplant photo edit based on the uploaded image. The transplant must not be limited to the person’s existing hair. Any bald or thinning areas, especially the frontal hairline, temples, front scalp, mid-scalp, and top of the head, must be treated as transplant recipient areas where new hair has been implanted.

Very important transplant coverage rule:
If the original person has a completely bald or heavily receded front area, reconstruct the recipient area as if it has been fully implanted. Do not only thicken old remaining hair. Create a natural new hairline design and visible implantation pattern across the bald frontal and top scalp areas.

Stage requirements:

Show the same person 2 weeks after a complete hair transplant.
The transplanted zones should clearly show many visible transplant marks across the future hairline, frontal scalp, temples, mid-scalp, and top area where needed.
Include realistic small red dots, healing micro-scabs, and mild post-transplant redness across the implanted areas.
Hair should still appear very short, early-stage, and not grown in yet.
The pattern of transplant marks must show that the previously bald or thinning areas have been fully implanted.
Keep the result realistic, medically believable, and not overly graphic.

Critical identity and consistency requirements:

Preserve the exact same person.
Do not change the face, facial structure, skin tone, ethnicity, age, expression, camera angle, pose, clothing, background, lighting, or overall composition.
Keep the same framing and perspective as the original uploaded image.
Do not make the person look like someone else.
Do not change beard, eyebrows, eyes, nose, lips, jawline, ears, or facial details.
Do not change the clothes, clothing color, or clothing style.
Do not change the background in any way.
Do not add or remove objects.
The only allowed changes are the scalp, hairline, hair density, implanted area, and visible hair-transplant healing effects appropriate to this timeline.

General realism requirements:

Ultra-realistic medical-style photo edit.
The result must look like a real hair transplant progress photo, not a 3D render, painting, cartoon, or beauty-retouched image.
Keep natural skin texture, realistic scalp detail, and natural hair texture.
Avoid artificial perfection or over-editing.
Hair color and hair texture should remain consistent with the person’s natural appearance.

Output requirements:

Produce exactly one high-resolution realistic photo edit.
The output must look like the same person in the same original photo setup.
Do not alter any non-hair element of the original image.
PROMPT,
            'intermediate_regrowth' => <<<'PROMPT'
Use the uploaded photo as the direct reference and generate exactly one ultra-realistic photo edit of the same person, showing an intermediate hair regrowth stage after a complete and successful hair transplant.

Overall objective:
Create a realistic intermediate post-hair-transplant regrowth photo edit based on the uploaded image. The transplant must not be limited to the person’s existing hair. Any bald or thinning areas, especially the frontal hairline, temples, front scalp, mid-scalp, and top of the head, must show early new hair growth from the transplanted areas.

Very important transplant coverage rule:
If the original person has a completely bald or heavily receded front area, reconstruct it as a fully treated recipient area with new hair growth beginning across the bald frontal and top scalp areas. Do not only thicken old remaining hair. Create a natural new hairline with sparse and uneven early regrowth.

Stage requirements:

Show the same person at a later intermediate stage after the transplant.
The red transplant wounds and scabs should be mostly healed or no longer visible.
New hair should appear across the transplanted bald or thinning areas, including the front hairline, temples, mid-scalp, and top scalp.
Hair should still look thin, sparse, uneven, and incomplete.
The scalp should still be partially visible, showing that the final density has not fully developed yet.
This stage should look clearly improved compared to the 2-week stage, but not yet full or final.
The new hairline should be visible but still developing.

Critical identity and consistency requirements:

Preserve the exact same person.
Do not change the face, facial structure, skin tone, ethnicity, age, expression, camera angle, pose, clothing, background, lighting, or overall composition.
Keep the same framing and perspective as the original uploaded image.
Do not make the person look like someone else.
Do not change beard, eyebrows, eyes, nose, lips, jawline, ears, or facial details.
Do not change the clothes, clothing color, or clothing style.
Do not change the background in any way.
Do not add or remove objects.
The only allowed changes are the scalp, hairline, hair density, implanted area, and hair regrowth stage.

General realism requirements:

Ultra-realistic medical-style photo edit.
The result must look like a real hair transplant progress photo, not a 3D render, painting, cartoon, or beauty-retouched image.
Keep natural skin texture, realistic scalp detail, and natural hair texture.
Avoid artificial perfection, fake density, or unrealistic hairlines.
Hair color and hair texture should remain consistent with the person’s natural appearance.

Output requirements:

Produce exactly one high-resolution realistic photo edit.
The output must look like the same person in the same original photo setup.
Do not alter any non-hair element of the original image.
PROMPT,
            'final_result' => <<<'PROMPT'
Use the uploaded photo as the direct reference and generate exactly one ultra-realistic photo edit of the same person, showing the final successful result after full hair growth from a complete hair transplant.

Overall objective:
Create a realistic final 1-year post-hair-transplant result based on the uploaded image. The transplant must not be limited to the person’s existing hair. Any bald or thinning areas, especially the frontal hairline, temples, front scalp, mid-scalp, and top of the head, must now look naturally filled in with fully grown transplanted hair.

Very important transplant coverage rule:
If the original person has a completely bald or heavily receded front area, reconstruct it as a successful complete transplant result. Do not only thicken old remaining hair. Create a realistic full new hairline and new hair growth across the bald frontal and top scalp areas, as if the person no longer appears bald in the transplanted areas.

Stage requirements:

Show the same person at the final successful result stage after full hair growth.
The previously bald or thinning front and top areas should now look naturally filled in.
Create a realistic full hairline where needed, including the frontal area and temples.
The person should no longer look bald in the transplanted areas.
Hair should appear fully grown, naturally fuller, and complete across the front, mid-scalp, and top.
The hairline must be natural, age-appropriate, and proportional to the face.
The density should look successful and full, but still realistic and slightly imperfect.
Avoid an unnaturally perfect, overly dense, fake, or wig-like result.
The final image should look like a real successful 1-year post-transplant result, as if the person naturally has a full head of hair again.

Critical identity and consistency requirements:

Preserve the exact same person.
Do not change the face, facial structure, skin tone, ethnicity, age, expression, camera angle, pose, clothing, background, lighting, or overall composition.
Keep the same framing and perspective as the original uploaded image.
Do not make the person look like someone else.
Do not change beard, eyebrows, eyes, nose, lips, jawline, ears, or facial details.
Do not change the clothes, clothing color, or clothing style.
Do not change the background in any way.
Do not add or remove objects.
The only allowed changes are the scalp, hairline, hair density, and fully grown transplanted hair.

General realism requirements:

Ultra-realistic medical-style photo edit.
The result must look like a real successful hair transplant photo, not a 3D render, painting, cartoon, or beauty-retouched image.
Keep natural skin texture, realistic scalp detail, and natural hair texture.
Avoid artificial perfection, fake density, or unrealistic hairlines.
Hair color and hair texture should remain consistent with the person’s natural appearance.
The final hair should look full and successful, but not like a wig or artificial hairpiece.

Output requirements:

Produce exactly one high-resolution realistic photo edit.
The output must look like the same person in the same original photo setup.
Do not alter any non-hair element of the original image.
PROMPT,
        ];
    }
}
