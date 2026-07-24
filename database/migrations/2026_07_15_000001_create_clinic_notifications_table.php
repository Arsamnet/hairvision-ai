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
     * @since 2.0.0
     */
    public function up(): void
    {
        Schema::create('clinic_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->text('body');
            $table->longText('body_html')->nullable();
            $table->string('type')->default('general')->index();
            $table->string('importance')->default('normal')->index();
            $table->string('status')->default('published')->index();
            $table->string('target_scope')->default('all')->index();
            $table->json('targeting_rules')->nullable();
            $table->timestamp('visible_from')->nullable()->index();
            $table->timestamp('visible_until')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'visible_from', 'visible_until'], 'clinic_notifications_visibility_idx');
        });

        Schema::create('clinic_notification_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_notification_id')->constrained('clinic_notifications')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('read_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('read_by_user_name')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['clinic_notification_id', 'tenant_id'], 'clinic_notification_tenant_unique');
            $table->index(['tenant_id', 'read_at'], 'clinic_notification_tenant_read_idx');
            $table->index(['tenant_id', 'delivered_at'], 'clinic_notification_tenant_delivered_idx');
        });
    }

    /**
     * Execute the down operation.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('clinic_notification_recipients');
        Schema::dropIfExists('clinic_notifications');
    }
};
