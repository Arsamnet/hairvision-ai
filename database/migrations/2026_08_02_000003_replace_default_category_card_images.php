<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Replace legacy seeded SVGs without overwriting administrator images.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function up(): void
    {
        DB::table('ai_model_categories')
            ->where('code', 'hair_transplant')
            ->where('card_image_path', '/theme/category-icons/hair-transplant.svg')
            ->update(['card_image_path' => '/theme/category-images/hair-transplant.jpg']);

        DB::table('ai_model_categories')
            ->where('code', 'eyebrow_transplant')
            ->where('card_image_path', '/theme/category-icons/eyebrow-transplant.svg')
            ->update(['card_image_path' => '/theme/category-images/eyebrow-transplant.jpg']);
    }

    /**
     * Restore the legacy seeded paths when they still use the new defaults.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function down(): void
    {
        DB::table('ai_model_categories')
            ->where('code', 'hair_transplant')
            ->where('card_image_path', '/theme/category-images/hair-transplant.jpg')
            ->update(['card_image_path' => '/theme/category-icons/hair-transplant.svg']);

        DB::table('ai_model_categories')
            ->where('code', 'eyebrow_transplant')
            ->where('card_image_path', '/theme/category-images/eyebrow-transplant.jpg')
            ->update(['card_image_path' => '/theme/category-icons/eyebrow-transplant.svg']);
    }
};
