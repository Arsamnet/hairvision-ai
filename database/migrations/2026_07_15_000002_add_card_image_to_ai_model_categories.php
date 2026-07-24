<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execute the up operation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        Schema::table('ai_model_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('ai_model_categories', 'card_image_path')) {
                $table->string('card_image_path')->nullable()->after('icon');
            }
        });

        DB::table('ai_model_categories')
            ->where('code', 'hair_transplant')
            ->update(['card_image_path' => '/theme/category-icons/hair-transplant.svg']);

        DB::table('ai_model_categories')
            ->where('code', 'eyebrow_transplant')
            ->update(['card_image_path' => '/theme/category-icons/eyebrow-transplant.svg']);
    }

    /**
     * Execute the down operation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        Schema::table('ai_model_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('ai_model_categories', 'card_image_path')) {
                $table->dropColumn('card_image_path');
            }
        });
    }
};
