<?php

namespace Database\Seeders;

use App\Support\AiModelCategoryUploadGuideDefaults;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds starter visitor photo guidance and upgrades legacy card image defaults.
 *
 * @since 2.1.0
 */
class AiModelCategorySeeder extends Seeder
{
    /** @var array<string, array{legacy: string, current: string}> */
    private const SEEDED_CARD_IMAGES = [
        'hair_transplant' => [
            'legacy' => '/theme/category-icons/hair-transplant.svg',
            'current' => '/theme/category-images/hair-transplant.jpg',
        ],
        'eyebrow_transplant' => [
            'legacy' => '/theme/category-icons/eyebrow-transplant.svg',
            'current' => '/theme/category-images/eyebrow-transplant.jpg',
        ],
    ];

    /**
     * Fill missing guidance for every seeded AI application.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function run(): void
    {
        foreach (AiModelCategoryUploadGuideDefaults::seeded() as $code => $guideHtml) {
            DB::table('ai_model_categories')
                ->where('code', $code)
                ->whereNull('upload_guide_html')
                ->update(['upload_guide_html' => $guideHtml]);
        }

        foreach (self::SEEDED_CARD_IMAGES as $code => $paths) {
            DB::table('ai_model_categories')
                ->where('code', $code)
                ->where('card_image_path', $paths['legacy'])
                ->update(['card_image_path' => $paths['current']]);
        }
    }
}
