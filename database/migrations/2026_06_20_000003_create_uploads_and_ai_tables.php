<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_ai_settings');
        Schema::dropIfExists('ai_providers');
        Schema::dropIfExists('uploads');
    }
};
