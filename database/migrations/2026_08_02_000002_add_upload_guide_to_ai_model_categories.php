<?php

use App\Support\AiModelCategoryUploadGuideDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add and backfill the visitor upload guide for every AI application.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_model_categories')) {
            return;
        }

        if (! Schema::hasColumn('ai_model_categories', 'upload_guide_html')) {
            Schema::table('ai_model_categories', function (Blueprint $table): void {
                $table->longText('upload_guide_html')->nullable()->after('description');
            });
        }

        DB::table('ai_model_categories')
            ->select(['id', 'code', 'name'])
            ->whereNull('upload_guide_html')
            ->orderBy('id')
            ->get()
            ->each(function (object $category): void {
                DB::table('ai_model_categories')
                    ->where('id', $category->id)
                    ->update([
                        'upload_guide_html' => AiModelCategoryUploadGuideDefaults::forCategory(
                            (string) $category->code,
                            (string) $category->name,
                        ),
                    ]);
            });
    }

    /**
     * Remove the visitor upload-guide field.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function down(): void
    {
        if (Schema::hasTable('ai_model_categories') && Schema::hasColumn('ai_model_categories', 'upload_guide_html')) {
            Schema::table('ai_model_categories', function (Blueprint $table): void {
                $table->dropColumn('upload_guide_html');
            });
        }
    }
};
