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
        Schema::table('ai_model_accesses', function (Blueprint $table): void {
            $table->unsignedInteger('credit_reserved')->default(0)->after('credit_used');
        });

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->timestamp('cooldown_until')->nullable()->after('last_assigned_at')->index();
        });

        Schema::table('generation_attempts', function (Blueprint $table): void {
            $table->timestamp('available_at')->nullable()->after('queue_position')->index();
            $table->foreignId('credit_transaction_id')
                ->nullable()
                ->after('ai_model_id')
                ->constrained('credit_transactions')
                ->nullOnDelete();
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
        Schema::table('generation_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credit_transaction_id');
            $table->dropColumn('available_at');
        });

        Schema::table('ai_models', function (Blueprint $table): void {
            $table->dropColumn('cooldown_until');
        });

        Schema::table('ai_model_accesses', function (Blueprint $table): void {
            $table->dropColumn('credit_reserved');
        });
    }
};
