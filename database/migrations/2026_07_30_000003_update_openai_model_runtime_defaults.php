<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Updates OpenAI timeouts and removes the unsupported response-format setting.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    /**
     * Apply the OpenAI runtime defaults to existing legacy and executable models.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        $this->updateModelSettings(
            timeoutSeconds: 1000,
            connectTimeoutSeconds: 50,
            responseFormat: null,
        );
    }

    /**
     * Restore only values that still match the defaults introduced by this migration.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        $this->updateModelSettings(
            timeoutSeconds: 900,
            connectTimeoutSeconds: 30,
            responseFormat: null,
            expectedTimeoutSeconds: 1000,
            expectedConnectTimeoutSeconds: 50,
        );
    }

    /**
     * Update OpenAI JSON settings in every model storage boundary that exists.
     *
     * @param  int  $timeoutSeconds  Response timeout to persist.
     * @param  int  $connectTimeoutSeconds  Connection timeout to persist.
     * @param  string|null  $responseFormat  Response format to persist, or null to remove it.
     * @param  int|null  $expectedTimeoutSeconds  Existing timeout required before replacement.
     * @param  int|null  $expectedConnectTimeoutSeconds  Existing connection timeout required before replacement.
     * @param  string|null  $expectedResponseFormat  Existing response format required before replacement.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function updateModelSettings(
        int $timeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $responseFormat,
        ?int $expectedTimeoutSeconds = null,
        ?int $expectedConnectTimeoutSeconds = null,
        ?string $expectedResponseFormat = null,
    ): void {
        if (Schema::hasTable('ai_model_accesses')) {
            $this->updateTable(
                table: 'ai_model_accesses',
                modelColumn: 'model_key',
                timeoutSeconds: $timeoutSeconds,
                connectTimeoutSeconds: $connectTimeoutSeconds,
                responseFormat: $responseFormat,
                expectedTimeoutSeconds: $expectedTimeoutSeconds,
                expectedConnectTimeoutSeconds: $expectedConnectTimeoutSeconds,
                expectedResponseFormat: $expectedResponseFormat,
            );
        }

        if (Schema::hasTable('ai_models')) {
            $this->updateTable(
                table: 'ai_models',
                modelColumn: 'type',
                timeoutSeconds: $timeoutSeconds,
                connectTimeoutSeconds: $connectTimeoutSeconds,
                responseFormat: $responseFormat,
                expectedTimeoutSeconds: $expectedTimeoutSeconds,
                expectedConnectTimeoutSeconds: $expectedConnectTimeoutSeconds,
                expectedResponseFormat: $expectedResponseFormat,
            );
        }
    }

    /**
     * Rewrite matching OpenAI settings without touching credentials or unrelated values.
     *
     * @param  string  $table  Model table name.
     * @param  string  $modelColumn  Column containing the configured model type.
     * @param  int  $timeoutSeconds  Response timeout to persist.
     * @param  int  $connectTimeoutSeconds  Connection timeout to persist.
     * @param  string|null  $responseFormat  Response format to persist, or null to remove it.
     * @param  int|null  $expectedTimeoutSeconds  Existing timeout required before replacement.
     * @param  int|null  $expectedConnectTimeoutSeconds  Existing connection timeout required before replacement.
     * @param  string|null  $expectedResponseFormat  Existing response format required before replacement.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    private function updateTable(
        string $table,
        string $modelColumn,
        int $timeoutSeconds,
        int $connectTimeoutSeconds,
        ?string $responseFormat,
        ?int $expectedTimeoutSeconds,
        ?int $expectedConnectTimeoutSeconds,
        ?string $expectedResponseFormat,
    ): void {
        DB::table($table)
            ->where($modelColumn, 'openai')
            ->orderBy('id')
            ->select(['id', 'settings'])
            ->chunkById(100, function ($models) use (
                $table,
                $timeoutSeconds,
                $connectTimeoutSeconds,
                $responseFormat,
                $expectedTimeoutSeconds,
                $expectedConnectTimeoutSeconds,
                $expectedResponseFormat,
            ): void {
                foreach ($models as $model) {
                    $settings = $this->decodeSettings($model->settings);

                    if ($expectedTimeoutSeconds === null
                        || (int) ($settings['timeout_seconds'] ?? 0) === $expectedTimeoutSeconds) {
                        $settings['timeout_seconds'] = $timeoutSeconds;
                    }

                    if ($expectedConnectTimeoutSeconds === null
                        || (int) ($settings['connect_timeout_seconds'] ?? 0) === $expectedConnectTimeoutSeconds) {
                        $settings['connect_timeout_seconds'] = $connectTimeoutSeconds;
                    }

                    if ($expectedResponseFormat === null
                        || ($settings['response_format'] ?? null) === $expectedResponseFormat) {
                        if ($responseFormat === null) {
                            unset($settings['response_format']);
                        } else {
                            $settings['response_format'] = $responseFormat;
                        }
                    }

                    DB::table($table)
                        ->where('id', $model->id)
                        ->update(['settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);
                }
            });
    }

    /**
     * Decode a nullable database JSON value into an associative settings array.
     *
     * @param  mixed  $value  Raw JSON column value.
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
