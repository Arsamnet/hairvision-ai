<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone_normalized')->nullable()->unique()->after('email');
            $table->string('role')->default('viewer')->after('phone_normalized');
            $table->string('status')->default('active')->after('role');
            $table->index(['role', 'status']);
        });

        Schema::create('tenant_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('viewer');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->index(['user_id', 'status']);
            $table->index(['tenant_id', 'role']);
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->string('full_name')->nullable()->after('tenant_id');
            $table->string('source')->nullable()->after('phone_normalized');
            $table->foreignId('assigned_user_id')->nullable()->after('crm_last_note_at')->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'crm_stage']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'assigned_user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'crm_stage']);
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['tenant_id', 'assigned_user_id']);
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropColumn(['full_name', 'source']);
        });

        Schema::dropIfExists('tenant_user');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role', 'status']);
            $table->dropUnique(['phone_normalized']);
            $table->dropColumn(['phone_normalized', 'role', 'status']);
        });
    }
};
