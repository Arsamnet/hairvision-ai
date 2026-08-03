<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add lead demographics and replace the duplicate CRM stage with one status.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('age')->nullable()->after('full_name');
            $table->string('city', 120)->nullable()->after('age');
        });

        $stageMap = [
            'new_lead' => 'new',
            'contacted' => 'needs_follow_up',
            'no_answer' => 'no_answer',
            'needs_follow_up' => 'needs_follow_up',
            'consultation_booked' => 'needs_follow_up',
            'converted' => 'successful',
            'lost' => 'canceled',
            'canceled' => 'canceled',
        ];
        $legacyStatusMap = [
            'new' => 'new',
            'verified' => 'new',
            'contacted' => 'needs_follow_up',
            'qualified' => 'successful',
            'archived' => 'canceled',
        ];

        DB::table('leads')
            ->select(['id', 'status', 'crm_stage'])
            ->orderBy('id')
            ->chunkById(500, function ($leads) use ($stageMap, $legacyStatusMap): void {
                foreach ($leads as $lead) {
                    DB::table('leads')
                        ->where('id', $lead->id)
                        ->update([
                            'status' => $stageMap[$lead->crm_stage]
                                ?? $legacyStatusMap[$lead->status]
                                ?? 'new',
                        ]);
                }
            });

        DB::table('lead_notes')
            ->select(['id', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($notes) use ($stageMap, $legacyStatusMap): void {
                foreach ($notes as $note) {
                    DB::table('lead_notes')
                        ->where('id', $note->id)
                        ->update([
                            'status' => $stageMap[$note->status]
                                ?? $legacyStatusMap[$note->status]
                                ?? 'new',
                        ]);
                }
            });

        Schema::table('lead_notes', function (Blueprint $table): void {
            $table->string('status')->default('new')->change();
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'crm_stage']);
            $table->dropColumn('crm_stage');
        });
    }

    /**
     * Restore the legacy status and CRM-stage split while removing demographics.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('crm_stage')->default('new_lead')->after('status');
        });

        $legacyMap = [
            'new' => ['status' => 'new', 'crm_stage' => 'new_lead'],
            'needs_follow_up' => ['status' => 'contacted', 'crm_stage' => 'needs_follow_up'],
            'no_answer' => ['status' => 'verified', 'crm_stage' => 'no_answer'],
            'successful' => ['status' => 'qualified', 'crm_stage' => 'converted'],
            'canceled' => ['status' => 'archived', 'crm_stage' => 'canceled'],
        ];

        DB::table('leads')
            ->select(['id', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($leads) use ($legacyMap): void {
                foreach ($leads as $lead) {
                    DB::table('leads')
                        ->where('id', $lead->id)
                        ->update($legacyMap[$lead->status] ?? $legacyMap['new']);
                }
            });

        DB::table('lead_notes')
            ->select(['id', 'status'])
            ->orderBy('id')
            ->chunkById(500, function ($notes) use ($legacyMap): void {
                foreach ($notes as $note) {
                    DB::table('lead_notes')
                        ->where('id', $note->id)
                        ->update([
                            'status' => ($legacyMap[$note->status] ?? $legacyMap['new'])['crm_stage'],
                        ]);
                }
            });

        Schema::table('lead_notes', function (Blueprint $table): void {
            $table->string('status')->default('new_lead')->change();
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->index(['tenant_id', 'crm_stage']);
            $table->dropColumn(['age', 'city']);
        });
    }
};
