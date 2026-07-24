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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('tenant_domains', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('type')->default('platform_subdomain');
            $table->string('status')->default('active');
            $table->string('ssl_status')->default('unknown');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_landing_page_visits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('host');
            $table->string('ip_hash')->nullable();
            $table->string('user_agent_hash')->nullable();
            $table->string('referer')->nullable();
            $table->timestamp('visited_at');
            $table->timestamps();
            $table->index(['tenant_id', 'visited_at']);
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
        Schema::dropIfExists('tenant_landing_page_visits');
        Schema::dropIfExists('tenant_domains');
        Schema::dropIfExists('tenants');
    }
};
