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

            $table->unique(['role_id', 'permission_id'], 'permission_role_unique');
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
            $table->string('plan_type')->default('base_subscription')->index();
            $table->string('service_key')->nullable()->index();
            $table->unsignedBigInteger('price');
            $table->string('currency')->default('IRR');
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('usage_limit')->default(0);
            $table->json('features_json')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['status', 'price']);
            $table->index(['plan_type', 'status']);
        });

        Schema::create('plan_service_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('service_key')->index();
            $table->unsignedInteger('credit_amount')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'service_key'], 'plan_service_unique');
            $table->index(['service_key', 'is_active'], 'plan_service_active_idx');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount');
            $table->string('currency')->default('IRR');
            $table->char('due_at', 19)->nullable();
            $table->char('paid_at', 19)->nullable();
            $table->char('expires_at', 19)->nullable();
            $table->char('activated_at', 19)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
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
            $table->char('verified_at', 19)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['invoice_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('status')->default('active');
            $table->char('starts_at', 19);
            $table->char('ends_at', 19);
            $table->unsignedInteger('usage_limit');
            $table->unsignedInteger('usage_used')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'ends_at']);
        });

        Schema::create('subscription_service_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('sub_ent_uuid_unique');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('service_key');
            $table->string('source_type')->default('base_plan');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('included_credit')->default(0);
            $table->unsignedInteger('purchased_credit')->default(0);
            $table->unsignedInteger('used_credit')->default(0);
            $table->boolean('is_active')->default(true);
            $table->char('starts_at', 19);
            $table->char('ends_at', 19)->nullable();
            $table->timestamps();

            $table->unique(
                ['subscription_id', 'service_key', 'source_type'],
                'sub_ent_sub_service_src_unique'
            );

            $table->index('service_key', 'sub_ent_service_idx');

            $table->index(
                ['tenant_id', 'service_key', 'is_active'],
                'sub_ent_tenant_service_active_idx'
            );

            $table->index(
                ['subscription_id', 'is_active'],
                'sub_ent_subscription_active_idx'
            );
        });

        Schema::create('credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();

            $table->foreignId('subscription_service_entitlement_id')->nullable();

            $table->foreign(
                'subscription_service_entitlement_id',
                'credit_tx_entitlement_fk'
            )
                ->references('id')
                ->on('subscription_service_entitlements')
                ->nullOnDelete();

            $table->string('service_key')->index();
            $table->string('type')->index();
            $table->integer('amount');
            $table->integer('balance_after')->nullable();
            $table->nullableMorphs('related');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'service_key', 'created_at'],
                'credit_tx_tenant_service_created_idx'
            );

            $table->index(
                ['subscription_id', 'created_at'],
                'credit_tx_subscription_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('subscription_service_entitlements');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('plan_service_entitlements');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('sms_gateways');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
