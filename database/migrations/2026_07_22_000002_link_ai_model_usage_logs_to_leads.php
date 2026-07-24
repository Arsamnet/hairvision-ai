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
        Schema::table('ai_model_usage_logs', function (Blueprint $table): void {
            $table->foreignId('lead_id')
                ->nullable()
                ->after('clinic_id')
                ->constrained('leads')
                ->nullOnDelete();

            $table->index(
                ['clinic_id', 'lead_id', 'created_at'],
                'ai_usage_logs_clinic_lead_created_index',
            );
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
        Schema::table('ai_model_usage_logs', function (Blueprint $table): void {
            $table->dropIndex('ai_usage_logs_clinic_lead_created_index');
            $table->dropConstrainedForeignId('lead_id');
        });
    }
};
