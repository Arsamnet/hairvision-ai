<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['status', 'is_system']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('group')->index();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();
            $table->string('group')->default('general')->index();
            $table->string('label');
            $table->string('type')->default('string');
            $table->json('value_json')->nullable();
            $table->text('encrypted_value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });

        Schema::create('sms_gateways', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('provider_code');
            $table->string('owner_scope')->default('system');
            $table->string('status')->default('enabled');
            $table->boolean('is_default')->default(false);
            $table->text('encrypted_credentials')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamps();
            $table->index(['owner_scope', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('provider_code');
            $table->string('status')->default('enabled');
            $table->boolean('is_default')->default(false);
            $table->text('encrypted_credentials')->nullable();
            $table->json('settings_json')->nullable();
            $table->timestamps();
            $table->index(['status', 'is_default']);
        });

        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price');
            $table->string('currency')->default('IRR');
            $table->unsignedInteger('duration_days');
            $table->unsignedInteger('usage_limit');
            $table->json('features_json')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['status', 'price']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('currency')->default('IRR');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('currency')->default('IRR');
            $table->string('authority')->nullable()->index();
            $table->string('reference_id')->nullable()->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('gateway_response_json')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('usage_limit');
            $table->unsignedInteger('usage_used')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('sms_gateways');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
