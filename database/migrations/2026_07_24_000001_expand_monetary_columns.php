<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> Monetary table columns that require 64-bit unsigned storage. */
    private const MONETARY_COLUMNS = [
        'plans' => 'price',
        'invoices' => 'amount',
        'payments' => 'amount',
    ];

    /**
     * Widen legacy monetary columns only when their creator schema is outdated.
     *
     * Fresh installations receive the final unsigned BIGINT definitions from
     * the system-management creator migration. This compatibility migration
     * therefore skips those columns instead of issuing redundant ALTER TABLE
     * statements, while still repairing and verifying older installations.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        foreach (self::MONETARY_COLUMNS as $tableName => $columnName) {
            if ($this->supportsLargeUnsignedAmounts($tableName, $columnName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columnName): void {
                $table->unsignedBigInteger($columnName)->change();
            });

            if (! $this->supportsLargeUnsignedAmounts($tableName, $columnName)) {
                throw new RuntimeException(
                    "Unable to widen {$tableName}.{$columnName} to an unsigned 64-bit monetary column."
                );
            }
        }
    }

    /**
     * Keep the widened columns during rollback to avoid truncating valid money.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        // Narrowing these columns could destroy values above the 32-bit range.
    }

    /**
     * Determine whether a monetary column supports the application's amount range.
     *
     * @param  string  $tableName  Database table name.
     * @param  string  $columnName  Monetary column name.
     * @return bool Whether the column has a compatible 64-bit definition.
     *
     * @since 2.0.0
     */
    private function supportsLargeUnsignedAmounts(string $tableName, string $columnName): bool
    {
        $typeName = strtolower(Schema::getColumnType($tableName, $columnName));
        $fullType = strtolower(Schema::getColumnType($tableName, $columnName, true));

        return match (DB::getDriverName()) {
            'mysql', 'mariadb' => $typeName === 'bigint' && str_contains($fullType, 'unsigned'),
            'sqlite' => in_array($typeName, ['bigint', 'integer'], true),
            default => $typeName === 'bigint',
        };
    }
};
