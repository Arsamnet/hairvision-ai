<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('scope')->default('clinic')->after('code')->index();
        });

        DB::table('roles')
            ->where('code', 'super_admin')
            ->update(['scope' => 'platform']);

        DB::table('roles')
            ->whereIn('code', ['clinic_admin', 'editor', 'viewer'])
            ->update(['scope' => 'clinic']);

        Schema::create('platform_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_normalized')->nullable()->unique();
            $table->string('role')->default('super_admin')->index();
            $table->string('status')->default('active')->index();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->index(['role', 'status']);
        });

        Schema::create('clinic_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone_normalized');
            $table->string('role')->default('viewer')->index();
            $table->string('status')->default('active')->index();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['tenant_id', 'phone_normalized'], 'clinic_users_tenant_phone_unique');
            $table->unique(['tenant_id', 'email'], 'clinic_users_tenant_email_unique');
            $table->index(['tenant_id', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_users');
        Schema::dropIfExists('platform_users');

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex(['scope']);
            $table->dropColumn('scope');
        });
    }
};
