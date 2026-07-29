<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> Monetary columns that must accept 64-bit unsigned amounts. */
    private const MONETARY_COLUMNS = [
        'plans' => 'price',
        'invoices' => 'amount',
        'payments' => 'amount',
    ];

    /**
     * Enforce the production monetary column widths with a new migration identity.
     *
     * Some deployments already recorded the earlier widening migration before
     * its guarded verification was added. Laravel will not rerun a migration
     * after it is recorded, so this additive migration rechecks the live schema
     * and repairs any remaining signed or 32-bit definitions before seeding.
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
                    "Unable to enforce an unsigned 64-bit definition for {$tableName}.{$columnName}."
                );
            }
        }
    }

    /**
     * Preserve widened monetary columns during rollback to prevent data loss.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        // Narrowing these columns could truncate already-persisted amounts.
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
