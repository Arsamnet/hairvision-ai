<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unprotect existing AI model access records so admins can edit every model.
     */
    public function up(): void
    {
        if (Schema::hasTable('ai_model_accesses') && Schema::hasColumn('ai_model_accesses', 'is_protected')) {
            DB::table('ai_model_accesses')->update(['is_protected' => false]);
        }
    }

    public function down(): void
    {
        //
    }
};
