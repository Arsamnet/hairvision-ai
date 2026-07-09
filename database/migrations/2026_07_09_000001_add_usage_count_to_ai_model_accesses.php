<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_model_accesses', 'usage_count')) {
            Schema::table('ai_model_accesses', function (Blueprint $table): void {
                $table->unsignedInteger('usage_count')->default(0)->after('credit_used');
            });
        }

        if (Schema::hasTable('ai_model_usage_logs')) {
            DB::table('ai_model_accesses')
                ->select('id')
                ->orderBy('id')
                ->each(function (object $access): void {
                    $summary = DB::table('ai_model_usage_logs')
                        ->where('ai_model_access_id', $access->id)
                        ->where('status', 'completed')
                        ->selectRaw('count(*) as usage_count, max(coalesce(completed_at, created_at)) as last_used_at')
                        ->first();

                    $updates = [
                        'usage_count' => (int) ($summary->usage_count ?? 0),
                    ];

                    if (filled($summary->last_used_at ?? null)) {
                        $updates['last_used_at'] = DB::raw('coalesce(last_used_at, '.DB::getPdo()->quote($summary->last_used_at).')');
                    }

                    DB::table('ai_model_accesses')
                        ->where('id', $access->id)
                        ->update($updates);
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_model_accesses', 'usage_count')) {
            Schema::table('ai_model_accesses', function (Blueprint $table): void {
                $table->dropColumn('usage_count');
            });
        }
    }
};
