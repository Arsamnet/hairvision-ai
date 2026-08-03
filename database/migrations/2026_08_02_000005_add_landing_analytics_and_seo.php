<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds first-party landing funnel events plus managed template SEO and tracker fields.
 *
 * @since 2.1.0
 */
return new class extends Migration
{
    /**
     * Apply the landing analytics and discoverability schema.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function up(): void
    {
        Schema::table('tenant_landing_page_visits', function (Blueprint $table): void {
            $table->foreignId('lead_id')->nullable()->after('tenant_id')->constrained('leads')->nullOnDelete();
            $table->string('analytics_token_hash', 64)->nullable()->unique()->after('user_agent_hash');
            $table->index(['tenant_id', 'lead_id', 'visited_at'], 'landing_visits_tenant_lead_date_index');
        });

        Schema::create('tenant_landing_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tenant_landing_page_visit_id')
                ->constrained('tenant_landing_page_visits')
                ->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('ai_model_category_id')->nullable()->constrained('ai_model_categories')->nullOnDelete();
            $table->foreignId('ai_model_category_output_id')->nullable()->constrained('ai_model_category_outputs')->nullOnDelete();
            $table->uuid('event_id');
            $table->string('event_type', 50);
            $table->string('category_code', 100)->nullable();
            $table->string('category_name', 150)->nullable();
            $table->string('output_code', 100)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id'], 'landing_events_tenant_event_unique');
            $table->index(['tenant_id', 'event_type', 'occurred_at'], 'landing_events_tenant_type_date_index');
            $table->index(['tenant_landing_page_visit_id', 'event_type'], 'landing_events_visit_type_index');
            $table->index(['tenant_id', 'category_code', 'event_type'], 'landing_events_category_type_index');
        });

        Schema::table('templates', function (Blueprint $table): void {
            $table->string('seo_title', 70)->nullable()->after('consent_text');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->string('seo_image_path')->nullable()->after('seo_description');
            $table->string('seo_robots', 40)->default('index,follow')->after('seo_image_path');
            $table->longText('tracking_head_code')->nullable()->after('seo_robots');
            $table->longText('tracking_body_code')->nullable()->after('tracking_head_code');
        });
    }

    /**
     * Roll back the landing analytics and discoverability schema.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table): void {
            $table->dropColumn([
                'seo_title',
                'seo_description',
                'seo_image_path',
                'seo_robots',
                'tracking_head_code',
                'tracking_body_code',
            ]);
        });

        Schema::dropIfExists('tenant_landing_events');

        Schema::table('tenant_landing_page_visits', function (Blueprint $table): void {
            $table->dropIndex('landing_visits_tenant_lead_date_index');
            $table->dropUnique(['analytics_token_hash']);
            $table->dropForeign(['lead_id']);
            $table->dropColumn(['lead_id', 'analytics_token_hash']);
        });
    }
};
