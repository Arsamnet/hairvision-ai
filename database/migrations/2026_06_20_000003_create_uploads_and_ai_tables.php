<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execute the up operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('original_disk');
            $table->string('original_path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('status')->default('uploaded');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('local');
            $table->string('base_url')->nullable();
            $table->string('auth_type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('supports_async')->default(false);
            $table->string('default_model')->nullable();
            $table->json('settings_schema_json')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_ai_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_name')->nullable();
            $table->text('prompt_template')->nullable();
            $table->json('settings_json')->nullable();
            $table->string('encrypted_api_key')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_model_accesses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('model_key')->index();
            $table->string('usage_category_key')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->longText('prompt')->nullable();
            $table->unsignedInteger('credit_limit')->nullable();
            $table->unsignedInteger('credit_used')->default(0);
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_demo')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->unsignedInteger('priority')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['model_key', 'is_active']);
            $table->index(['usage_category_key', 'is_active']);
            $table->index(['is_demo', 'is_protected']);
        });

        Schema::create('clinic_ai_model_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('ai_model_access_id')->constrained('ai_model_accesses')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['clinic_id', 'ai_model_access_id'], 'clinic_ai_model_access_unique');
            $table->index(['clinic_id', 'is_active']);
            $table->index(['ai_model_access_id', 'is_active'], 'clinic_ai_model_access_model_active_index');
        });

        Schema::create('ai_model_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('clinic_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ai_model_access_id')->constrained('ai_model_accesses')->cascadeOnDelete();
            $table->string('usage_category_key')->index();
            $table->string('status')->index();
            $table->unsignedInteger('credit_consumed')->default(0);
            $table->string('request_reference')->nullable();
            $table->string('response_reference')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['clinic_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['ai_model_access_id', 'created_at'], 'ai_model_usage_access_created_index');
            $table->index(['usage_category_key', 'status']);
        });
    }

    /**
     * Execute the down operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_model_usage_logs');
        Schema::dropIfExists('clinic_ai_model_access');
        Schema::dropIfExists('ai_model_accesses');
        Schema::dropIfExists('tenant_ai_settings');
        Schema::dropIfExists('ai_providers');
        Schema::dropIfExists('uploads');
    }
};
