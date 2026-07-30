<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add durable image-queue observability, worker, and provider-capacity state.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        Schema::table('generation_groups', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable()->after('status')->index();
            $table->timestamp('started_at')->nullable()->after('queued_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::table('generation_jobs', function (Blueprint $table): void {
            $table->timestamp('queued_at')->nullable()->after('status')->index();
            $table->timestamp('dispatched_at')->nullable()->after('queued_at');
            $table->timestamp('provider_call_started_at')->nullable()->after('started_at');
            $table->timestamp('provider_call_finished_at')->nullable()->after('provider_call_started_at');
            $table->unsignedBigInteger('queue_wait_ms')->nullable()->after('provider_call_finished_at');
            $table->unsignedBigInteger('provider_latency_ms')->nullable()->after('queue_wait_ms');
            $table->string('worker_id')->nullable()->after('provider_latency_ms')->index();
            $table->string('error_code')->nullable()->after('last_error');
            $table->unique(['generation_group_id', 'period'], 'generation_jobs_group_stage_unique');
        });

        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->string('resume_token_hash', 64)->nullable()->after('idempotency_key');
            $table->timestamp('resumable_until')->nullable()->after('resume_token_hash')->index();
        });

        Schema::table('generation_attempts', function (Blueprint $table): void {
            $table->unsignedBigInteger('queue_wait_ms')->nullable()->after('dispatched_at');
            $table->unsignedBigInteger('provider_latency_ms')->nullable()->after('provider_call_finished_at');
            $table->string('worker_id')->nullable()->after('provider_latency_ms')->index();
        });

        Schema::create('image_worker_processes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('slot')->unique();
            $table->uuid('worker_id')->unique();
            $table->string('mode')->default('detached');
            $table->unsignedBigInteger('pid')->nullable()->index();
            $table->string('host')->nullable();
            $table->string('status')->default('starting')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable()->index();
            $table->timestamp('restart_requested_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('image_provider_leases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('lease_id')->unique();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->string('worker_id')->nullable();
            $table->timestamp('acquired_at');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id'], 'image_provider_leases_owner_unique');
        });

        Schema::create('image_load_test_samples', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id')->index();
            $table->unsignedInteger('batch_size');
            $table->unsignedInteger('sequence');
            $table->string('status')->default('queued')->index();
            $table->timestamp('queued_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('queue_wait_ms')->nullable();
            $table->unsignedBigInteger('provider_latency_ms')->nullable();
            $table->unsignedBigInteger('memory_bytes')->nullable();
            $table->unsignedBigInteger('worker_pid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove image-queue runtime state and observability columns.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('image_load_test_samples');
        Schema::dropIfExists('image_provider_leases');
        Schema::dropIfExists('image_worker_processes');

        Schema::table('generation_attempts', function (Blueprint $table): void {
            $table->dropColumn(['queue_wait_ms', 'provider_latency_ms', 'worker_id']);
        });

        Schema::table('generation_requests', function (Blueprint $table): void {
            $table->dropColumn(['resume_token_hash', 'resumable_until']);
        });

        Schema::table('generation_jobs', function (Blueprint $table): void {
            $table->dropUnique('generation_jobs_group_stage_unique');
            $table->dropColumn([
                'queued_at',
                'dispatched_at',
                'provider_call_started_at',
                'provider_call_finished_at',
                'queue_wait_ms',
                'provider_latency_ms',
                'worker_id',
                'error_code',
            ]);
        });

        Schema::table('generation_groups', function (Blueprint $table): void {
            $table->dropColumn(['queued_at', 'started_at', 'completed_at']);
        });
    }
};
