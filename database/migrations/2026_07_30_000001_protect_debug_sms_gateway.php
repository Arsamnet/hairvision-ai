<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Protects the system debug SMS gateway and removes the exact legacy demo-clinic seed row.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    /**
     * Add the protection flag and reconcile legacy seeded SMS gateway data.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        if (! Schema::hasTable('sms_gateways')) {
            return;
        }

        if (! Schema::hasColumn('sms_gateways', 'is_protected')) {
            Schema::table('sms_gateways', function (Blueprint $table): void {
                $table->boolean('is_protected')->default(false);
            });
        }

        DB::table('sms_gateways')
            ->where('owner_scope', 'system')
            ->whereNull('user_id')
            ->whereNull('tenant_id')
            ->where('provider_code', 'local')
            ->update(['is_protected' => true]);

        $demoTenantIds = DB::table('tenants')
            ->where('slug', 'demo')
            ->pluck('id');

        if ($demoTenantIds->isEmpty()) {
            return;
        }

        DB::table('sms_gateways')
            ->whereIn('tenant_id', $demoTenantIds)
            ->where('owner_scope', 'tenant')
            ->where('provider_code', 'ippanel')
            ->where('name', 'سیستم پیامک کلینیک دمو')
            ->delete();
    }

    /**
     * Remove the protection schema while leaving intentionally removed seeded credentials absent.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        if (! Schema::hasTable('sms_gateways') || ! Schema::hasColumn('sms_gateways', 'is_protected')) {
            return;
        }

        Schema::table('sms_gateways', function (Blueprint $table): void {
            $table->dropColumn('is_protected');
        });
    }
};
