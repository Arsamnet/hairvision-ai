<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('plan_ai_model_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('ai_model_access_id')->constrained('ai_model_accesses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'ai_model_access_id'], 'plan_ai_model_access_unique');
            $table->index(['ai_model_access_id', 'plan_id'], 'plan_ai_model_access_model_plan_idx');
        });

        $this->backfillExistingPlans();
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
        Schema::dropIfExists('plan_ai_model_access');
    }

    /**
     * Preserve legacy plans when one highest-priority executable model covers
     * every active output of each purchased application.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function backfillExistingPlans(): void
    {
        if (! Schema::hasTable('ai_models') || ! Schema::hasTable('ai_model_output_configs')) {
            return;
        }

        $services = DB::table('plan_service_entitlements as entitlements')
            ->join('plans', 'plans.id', '=', 'entitlements.plan_id')
            ->where('entitlements.is_active', true)
            ->where('plans.plan_type', '!=', 'credit_top_up')
            ->select(['entitlements.plan_id', 'entitlements.service_key'])
            ->distinct()
            ->get();

        $legacyServices = DB::table('plans')
            ->where('plan_type', '!=', 'credit_top_up')
            ->whereNotNull('service_key')
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('plan_service_entitlements')
                ->whereColumn('plan_service_entitlements.plan_id', 'plans.id')
                ->where('plan_service_entitlements.is_active', true))
            ->select(['id as plan_id', 'service_key'])
            ->get();

        $services
            ->concat($legacyServices)
            ->unique(fn (object $service): string => $service->plan_id.':'.$service->service_key)
            ->each(fn (object $service) => $this->backfillService(
                (int) $service->plan_id,
                (string) $service->service_key,
            ));
    }

    /**
     * Execute the backfill service operation.
     *
     * @param  int  $planId  The plan id value.
     * @param  string  $serviceKey  The service key value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function backfillService(int $planId, string $serviceKey): void
    {
        $categoryId = DB::table('ai_model_categories')
            ->where('code', $serviceKey)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->value('id');

        if (! $categoryId) {
            return;
        }

        $requiredOutputIds = DB::table('ai_model_category_outputs')
            ->where('ai_model_category_id', $categoryId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($requiredOutputIds === []) {
            return;
        }

        $candidateRows = DB::table('ai_model_accesses as accesses')
            ->join('ai_models as models', 'models.legacy_ai_model_access_id', '=', 'accesses.id')
            ->join('ai_model_output_configs as configs', 'configs.ai_model_id', '=', 'models.id')
            ->join('ai_model_category_outputs as outputs', 'outputs.id', '=', 'configs.ai_model_category_output_id')
            ->where('accesses.usage_category_key', $serviceKey)
            ->where('accesses.is_active', true)
            ->whereNull('accesses.deleted_at')
            ->where(fn ($query) => $query
                ->whereNull('accesses.credit_limit')
                ->orWhereColumn('accesses.credit_used', '<', 'accesses.credit_limit'))
            ->where('models.ai_model_category_id', $categoryId)
            ->where('models.status', 'active')
            ->whereIn('models.health_status', ['healthy', 'unknown'])
            ->whereNull('models.paused_at')
            ->whereNull('models.archived_at')
            ->whereNull('models.deleted_at')
            ->where('configs.is_active', true)
            ->where('outputs.ai_model_category_id', $categoryId)
            ->where('outputs.is_active', true)
            ->whereNull('outputs.deleted_at')
            ->orderByRaw('accesses.priority is null')
            ->orderBy('accesses.priority')
            ->orderBy('accesses.id')
            ->select(['accesses.id as access_id', 'outputs.id as output_id'])
            ->get()
            ->groupBy('access_id');

        $selectedAccessId = null;

        foreach ($candidateRows as $accessId => $rows) {
            $coveredOutputIds = $rows
                ->pluck('output_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->intersect($requiredOutputIds)
                ->all();

            if (array_diff($requiredOutputIds, $coveredOutputIds) === []) {
                $selectedAccessId = (int) $accessId;

                break;
            }
        }

        if ($selectedAccessId === null) {
            return;
        }

        $now = now();

        DB::table('plan_ai_model_access')->insertOrIgnore([
            'plan_id' => $planId,
            'ai_model_access_id' => $selectedAccessId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
