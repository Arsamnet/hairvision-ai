<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen every persisted currency amount for existing installations.
     *
     * Some databases ran an earlier version of the system-management migration
     * while these columns were still 32-bit integers. Changing the historical
     * migration does not update those databases, so this additive migration is
     * required even though fresh installations already use unsigned BIGINTs.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('price')->change();
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount')->change();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('amount')->change();
        });
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
};
