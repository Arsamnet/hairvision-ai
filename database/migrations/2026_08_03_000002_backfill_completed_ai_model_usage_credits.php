<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs successful usage logs whose consumed credit was previously hidden as zero.
 *
 * @since 2.1.1
 */
return new class extends Migration
{
    /**
     * Backfill the minimum credit consumed by every successful model execution.
     *
     * RunAiModelAction has always normalized successful provider responses to at
     * least one credit, so a completed zero-credit row represents the historical
     * unlimited-access logging defect rather than a legitimate free execution.
     *
     * @return void No value is returned.
     *
     * @since 2.1.1
     */
    public function up(): void
    {
        if (! Schema::hasTable('ai_model_usage_logs')) {
            return;
        }

        DB::table('ai_model_usage_logs')
            ->where('status', 'completed')
            ->where('credit_consumed', 0)
            ->update(['credit_consumed' => 1]);
    }

    /**
     * Leave corrected historical usage intact during rollback.
     *
     * Resetting every one-credit row would also corrupt valid executions created
     * after this migration, so this data repair is intentionally irreversible.
     *
     * @return void No value is returned.
     *
     * @since 2.1.1
     */
    public function down(): void
    {
        // Intentionally left blank because corrected and native one-credit rows are indistinguishable.
    }
};
