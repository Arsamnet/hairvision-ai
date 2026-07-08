<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generation_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('generation_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('generation_group_id')->constrained('generation_groups')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->foreignId('ai_model_access_id')->nullable()->constrained('ai_model_accesses')->nullOnDelete();
            $table->string('period');
            $table->string('model_name')->nullable();
            $table->text('prompt')->nullable();
            $table->string('status')->default('pending');
            $table->string('provider_job_id')->nullable();
            $table->timestamp('next_poll_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('generated_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('generation_group_id')->constrained('generation_groups')->cascadeOnDelete();
            $table->foreignId('generation_job_id')->constrained('generation_jobs')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('period');
            $table->string('disk');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->json('provider_metadata_json')->nullable();
            $table->timestamps();
        });

        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenant_theme_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('templates')->cascadeOnDelete();
            $table->text('header_html')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->text('body_html')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('background_path')->nullable();
            $table->text('consent_text')->nullable();
            $table->text('footer_html')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('back_link_title')->nullable();
            $table->string('back_link_url')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('tenant_theme_settings');
        Schema::dropIfExists('templates');
        Schema::dropIfExists('generated_assets');
        Schema::dropIfExists('generation_jobs');
        Schema::dropIfExists('generation_groups');
    }
};
