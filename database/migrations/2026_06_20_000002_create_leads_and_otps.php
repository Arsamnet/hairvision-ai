<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('phone');
            $table->string('phone_normalized');
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('status')->default('new');
            $table->string('crm_stage')->default('new_lead');
            $table->timestamp('crm_last_note_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'phone_normalized']);
        });

        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('phone_normalized');
            $table->string('code_hash');
            $table->string('purpose')->default('lead_login');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'phone_normalized']);
        });

        Schema::create('lead_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('admin_note');
            $table->string('status')->default('new_lead');
            $table->string('communication_channel')->nullable();
            $table->text('note');
            $table->char('next_follow_up_at', 19)->nullable();
            $table->char('completed_at', 19)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_notes');
        Schema::dropIfExists('otp_codes');
        Schema::dropIfExists('leads');
    }
};
