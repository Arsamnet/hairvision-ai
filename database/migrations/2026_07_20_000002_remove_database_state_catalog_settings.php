<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_settings')) {
            return;
        }

        DB::table('system_settings')
            ->where('group', 'catalog')
            ->orWhere('key', 'like', 'catalog.states.%')
            ->delete();
    }

    public function down(): void
    {
        // State catalogs remain recoverable from config/state-catalog.php.
    }
};
