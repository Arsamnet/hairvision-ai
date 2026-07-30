<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Increases the default OpenAI model concurrency without overwriting custom limits.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    /**
     * Raise missing or original single-request OpenAI limits to two.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        $this->updateSettings('ai_model_accesses', 'model_key', 2, null);
        $this->updateSettings('ai_models', 'type', 2, 2);
    }

    /**
     * Restore only limits that still match the value introduced here.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        $this->updateSettings('ai_model_accesses', 'model_key', 1, null, 2);
        $this->updateSettings('ai_models', 'type', 1, 1, 2);
    }

    /**
     * Update OpenAI JSON settings and, when present, the executable capacity column.
     *
     * @param  string  $table  Model storage table.
     * @param  string  $modelColumn  Column containing the provider type.
     * @param  int  $newLimit  Concurrency limit to persist.
     * @param  int|null  $columnLimit  Executable-model column value to persist.
     * @param  int|null  $expectedLimit  Existing explicit value required before rollback.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function updateSettings(
        string $table,
        string $modelColumn,
        int $newLimit,
        ?int $columnLimit,
        ?int $expectedLimit = null,
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        $hasConcurrencyColumn = Schema::hasColumn($table, 'concurrency_limit');
        $columns = ['id', 'settings'];

        if ($hasConcurrencyColumn) {
            $columns[] = 'concurrency_limit';
        }

        DB::table($table)
            ->where($modelColumn, 'openai')
            ->orderBy('id')
            ->select($columns)
            ->chunkById(100, function ($models) use (
                $table,
                $newLimit,
                $columnLimit,
                $expectedLimit,
                $hasConcurrencyColumn,
            ): void {
                foreach ($models as $model) {
                    $settings = $this->decodeSettings($model->settings);
                    $currentLimit = array_key_exists('concurrency_limit', $settings)
                        ? (int) $settings['concurrency_limit']
                        : ($hasConcurrencyColumn ? (int) $model->concurrency_limit : null);

                    $canUpdate = $expectedLimit === null
                        ? ($currentLimit === null || $currentLimit === 1)
                        : $currentLimit === $expectedLimit;

                    if (! $canUpdate) {
                        continue;
                    }

                    $settings['concurrency_limit'] = $newLimit;
                    $values = ['settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)];

                    if ($columnLimit !== null && $hasConcurrencyColumn) {
                        $values['concurrency_limit'] = $columnLimit;
                    }

                    DB::table($table)->where('id', $model->id)->update($values);
                }
            });
    }

    /**
     * Decode a nullable JSON settings value.
     *
     * @param  mixed  $value  Raw database value.
     * @return array<string, mixed> Decoded settings.
     *
     * @since 2.0.0
     */
    private function decodeSettings(mixed $value): array
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
};
