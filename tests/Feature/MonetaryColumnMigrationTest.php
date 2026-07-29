<?php

namespace Tests\Feature;

use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonetaryColumnMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_new_additive_migration_repairs_a_schema_after_the_previous_repair_was_recorded(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('The legacy signed INT schema can only be reproduced on MySQL or MariaDB.');
        }

        foreach ($this->monetaryColumns() as $tableName => $columnName) {
            DB::statement("ALTER TABLE `{$tableName}` MODIFY `{$columnName}` INT NOT NULL");

            $this->assertSame('int', strtolower(Schema::getColumnType($tableName, $columnName)));
        }

        DB::table('migrations')
            ->where('migration', '2026_07_29_000001_enforce_monetary_column_widths')
            ->delete();

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        foreach ($this->monetaryColumns() as $tableName => $columnName) {
            $columnType = strtolower(Schema::getColumnType($tableName, $columnName, true));

            $this->assertStringContainsString('bigint', $columnType);
            $this->assertStringContainsString('unsigned', $columnType);
        }

        $this->seed(PlanSeeder::class);

        $this->assertDatabaseHas('plans', [
            'name' => 'پلن شروع',
            'price' => 4_000_000_000,
        ]);
    }

    /**
     * Return monetary columns covered by the additive schema repair.
     *
     * @return array<string, string>
     */
    private function monetaryColumns(): array
    {
        return [
            'plans' => 'price',
            'invoices' => 'amount',
            'payments' => 'amount',
        ];
    }
}
