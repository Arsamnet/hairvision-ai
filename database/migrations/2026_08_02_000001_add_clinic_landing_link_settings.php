<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds the tenant-controlled landing back-link target and clinic settings permission.
 *
 * @since 2.1.0
 */
return new class extends Migration
{
    /**
     * Stable permission granted to clinic administrators.
     *
     * @since 2.1.0
     */
    private const PERMISSION_CODE = 'clinic_settings.manage';

    /**
     * Add the target setting and register access for clinic administrators.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function up(): void
    {
        Schema::table('tenant_theme_settings', function (Blueprint $table): void {
            $table->string('back_link_target', 16)->default('_self')->after('back_link_url');
        });

        $now = now();
        $permissionId = DB::table('permissions')->where('code', self::PERMISSION_CODE)->value('id');

        if ($permissionId) {
            DB::table('permissions')->where('id', $permissionId)->update([
                'name' => 'مدیریت تنظیمات کلینیک',
                'group' => 'settings',
                'updated_at' => $now,
            ]);
        } else {
            $permissionId = DB::table('permissions')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'code' => self::PERMISSION_CODE,
                'name' => 'مدیریت تنظیمات کلینیک',
                'group' => 'settings',
                'description' => 'ویرایش تنظیمات عمومی و نمایشی کلینیک جاری',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->whereIn('code', ['super_admin', 'clinic_admin'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Remove the target setting and permission introduced by this feature.
     *
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', self::PERMISSION_CODE)->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::table('tenant_theme_settings', function (Blueprint $table): void {
            $table->dropColumn('back_link_target');
        });
    }
};
