<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_provider_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sms_provider_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sms_provider_type_id')->constrained('sms_provider_types')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('scope')->index();
            $table->string('name');
            $table->text('credentials')->nullable();
            $table->text('settings')->nullable();
            $table->string('sender_number')->nullable();
            $table->unsignedSmallInteger('priority')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['sms_provider_type_id', 'is_active']);
            $table->index(['owner_user_id', 'is_active']);
            $table->index(['scope', 'is_active', 'is_default']);
            $table->index(['scope', 'priority']);
        });

        Schema::create('user_sms_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('default_sms_provider_account_id')->nullable()->constrained('sms_provider_accounts')->nullOnDelete();
            $table->boolean('fallback_to_system')->default(true);
            $table->timestamps();
        });

        Schema::create('sms_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sms_provider_account_id')->nullable()->constrained('sms_provider_accounts')->nullOnDelete();
            $table->foreignId('sms_provider_type_id')->nullable()->constrained('sms_provider_types')->nullOnDelete();
            $table->string('recipient_mobile');
            $table->string('message_preview')->nullable();
            $table->string('message_hash')->nullable();
            $table->string('purpose')->nullable()->index();
            $table->string('status')->index();
            $table->string('provider_message_id')->nullable();
            $table->string('error_code')->nullable();
            $table->string('error_message')->nullable();
            $table->decimal('cost', 12, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['sms_provider_account_id', 'status']);
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_delivery_logs');
        Schema::dropIfExists('user_sms_settings');
        Schema::dropIfExists('sms_provider_accounts');
        Schema::dropIfExists('sms_provider_types');
    }
};
