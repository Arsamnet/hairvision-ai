<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('generation_jobs', function (Blueprint $table): void {
            $table->foreignId('ai_model_access_id')
                ->nullable()
                ->after('provider_id')
                ->constrained('ai_model_accesses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('generation_jobs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ai_model_access_id');
        });

        Schema::dropIfExists('ai_model_usage_logs');
        Schema::dropIfExists('clinic_ai_model_access');
        Schema::dropIfExists('ai_model_accesses');
    }
};
