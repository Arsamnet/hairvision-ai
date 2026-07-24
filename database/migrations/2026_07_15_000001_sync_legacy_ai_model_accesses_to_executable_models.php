<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        if (
            ! Schema::hasTable('ai_model_accesses')
            || ! Schema::hasTable('ai_model_categories')
            || ! Schema::hasTable('ai_model_category_outputs')
            || ! Schema::hasTable('ai_models')
            || ! Schema::hasTable('ai_model_output_configs')
        ) {
            return;
        }

        DB::table('ai_model_accesses')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($accesses): void {
                foreach ($accesses as $access) {
                    $this->syncAccess($access);
                }
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
        //
    }

    /**
     * Synchronize access.
     *
     * @param  object  $access  The access value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function syncAccess(object $access): void
    {
        $category = DB::table('ai_model_categories')
            ->where('code', $access->usage_category_key)
            ->whereNull('deleted_at')
            ->first();

        if (! $category || ! array_key_exists((string) $access->model_key, config('ai-models.types', []))) {
            return;
        }

        $settings = $this->decodeJson($access->settings);
        $now = now();
        $isActive = (bool) $access->is_active;
        $existingModel = DB::table('ai_models')
            ->where('legacy_ai_model_access_id', $access->id)
            ->first();

        $attributes = [
            'ai_model_category_id' => $category->id,
            'type' => $access->model_key,
            'name' => $access->name,
            'status' => $isActive ? 'active' : 'paused',
            'priority' => $access->priority ?? 100,
            'concurrency_limit' => $this->concurrencyLimitFor((string) $access->model_key, $settings),
            'paused_at' => $isActive ? null : $now,
            'archived_at' => null,
            'updated_by' => $access->updated_by,
            'updated_at' => $now,
        ];

        if ($existingModel) {
            DB::table('ai_models')
                ->where('id', $existingModel->id)
                ->update($attributes);

            $modelId = $existingModel->id;
        } else {
            $modelId = DB::table('ai_models')->insertGetId($attributes + [
                'uuid' => (string) Str::uuid(),
                'legacy_ai_model_access_id' => $access->id,
                'settings' => null,
                'credentials' => null,
                'health_status' => 'unknown',
                'consecutive_failures' => 0,
                'created_by' => $access->created_by,
                'created_at' => $now,
            ]);
        }

        DB::table('ai_model_category_outputs')
            ->where('ai_model_category_id', $category->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $output) use ($modelId, $access, $settings, $now): void {
                $configuration = $this->configurationFor((string) $access->model_key, $settings, $output);
                $existingConfigId = DB::table('ai_model_output_configs')
                    ->where('ai_model_id', $modelId)
                    ->where('ai_model_category_output_id', $output->id)
                    ->value('id');

                $attributes = [
                    'is_active' => (bool) $output->is_active,
                    'configuration' => json_encode($configuration, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ];

                if ($existingConfigId) {
                    DB::table('ai_model_output_configs')
                        ->where('id', $existingConfigId)
                        ->update($attributes);

                    return;
                }

                DB::table('ai_model_output_configs')->insert($attributes + [
                    'uuid' => (string) Str::uuid(),
                    'ai_model_id' => $modelId,
                    'ai_model_category_output_id' => $output->id,
                    'created_at' => $now,
                ]);
            });
    }

    /**
     * Execute the decode json operation.
     *
     * @param  mixed  $value  Input value supplied to the operation.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Execute the concurrency limit for operation.
     *
     * @param  array<string, mixed>  $settings
     * @param  string  $modelKey  The model key value.
     * @return int Calculated numeric value.
     *
     * @since 2.0.0
     */
    private function concurrencyLimitFor(string $modelKey, array $settings): int
    {
        return max(1, (int) data_get(
            $settings,
            'concurrency_limit',
            data_get(config('ai-models.types.'.$modelKey), 'defaults.concurrency_limit', 1)
        ));
    }

    /**
     * Execute the configuration for operation.
     *
     * @param  array<string, mixed>  $settings
     * @param  string  $modelKey  The model key value.
     * @param  object  $output  The output value.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    private function configurationFor(string $modelKey, array $settings, object $output): array
    {
        if ($modelKey === 'sample_image') {
            return [
                'sample_path' => data_get($settings, 'demo_images.'.$output->code)
                    ?? data_get($settings, 'sample_path')
                    ?? config('hairvision.generation.stages.'.$output->code.'.sample_image')
                    ?? config('hairvision.sample_images.'.$output->code)
                    ?? 'sample-results/'.$output->code.'.jpg',
            ];
        }

        if ($modelKey === 'gapgpt') {
            return [
                'prompt' => $output->prompt,
            ];
        }

        return [];
    }
};
