<?php

use App\Support\LandingTemplateDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds centrally managed landing template content, default selection, and safe tenant assignment.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    /**
     * Apply the landing template management schema.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        $hasDuplicateThemes = DB::table('tenant_theme_settings')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateThemes) {
            throw new RuntimeException('Duplicate tenant theme rows must be resolved before enabling managed landing templates.');
        }

        Schema::table('templates', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
            $table->string('primary_color', 7)->nullable()->after('description');
            $table->string('favicon_path')->nullable()->after('primary_color');
            $table->text('consent_text')->nullable()->after('favicon_path');
            $table->longText('header_html')->nullable()->after('consent_text');
            $table->longText('body_html')->nullable()->after('header_html');
            $table->longText('footer_html')->nullable()->after('body_html');
            $table->string('back_link_title')->nullable()->after('footer_html');
            $table->string('back_link_url')->nullable()->after('back_link_title');
            $table->boolean('is_default')->default(false)->index()->after('is_active');
            $table->unsignedInteger('revision')->default(1)->after('is_default');
            $table->foreignId('created_by')->nullable()->after('revision')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('tenant_theme_settings', function (Blueprint $table): void {
            $table->dropForeign(['template_id']);
            $table->boolean('use_template_content')->default(false)->after('template_id');
            $table->unique('tenant_id', 'tenant_theme_settings_tenant_unique');
            $table->foreign('template_id')->references('id')->on('templates')->restrictOnDelete();
        });

        $this->configureExistingDefaultTemplate();
    }

    /**
     * Roll back the landing template management schema.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        Schema::table('tenant_theme_settings', function (Blueprint $table): void {
            $table->dropForeign(['template_id']);
            $table->dropUnique('tenant_theme_settings_tenant_unique');
            $table->dropColumn('use_template_content');
            $table->foreign('template_id')->references('id')->on('templates')->cascadeOnDelete();
        });

        Schema::table('templates', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropIndex(['is_default']);
            $table->dropColumn([
                'description',
                'primary_color',
                'favicon_path',
                'consent_text',
                'header_html',
                'body_html',
                'footer_html',
                'back_link_title',
                'back_link_url',
                'is_default',
                'revision',
                'created_by',
                'updated_by',
            ]);
        });
    }

    /**
     * Mark the existing classic template as the active default without changing tenant records.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function configureExistingDefaultTemplate(): void
    {
        $templateId = DB::table('templates')
            ->where('code', LandingTemplateDefaults::CODE)
            ->value('id')
            ?? DB::table('templates')->where('is_active', true)->orderBy('id')->value('id');

        if (! $templateId) {
            return;
        }

        DB::table('templates')->update(['is_default' => false]);
        DB::table('templates')->where('id', $templateId)->update(array_merge(
            LandingTemplateDefaults::content(),
            ['is_active' => true, 'is_default' => true]
        ));
    }
};
