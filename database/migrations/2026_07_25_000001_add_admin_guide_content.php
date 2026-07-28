<?php

use App\Support\AdminGuideDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers editable dashboard guide content and its management permission.
 *
 * @since 2.0.1
 */
return new class extends Migration
{
    private const GUIDE_SETTING_KEY = 'guide.content';

    /**
     * Add the guide permission and default structured system setting.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function up(): void
    {
        $now = now();

        if (DB::table('permissions')->where('code', 'guide.manage')->exists()) {
            DB::table('permissions')->where('code', 'guide.manage')->update([
                'name' => 'مدیریت محتوای راهنما',
                'group' => 'guide',
                'updated_at' => $now,
            ]);
        } else {
            DB::table('permissions')->insert([
                'uuid' => (string) Str::uuid(),
                'code' => 'guide.manage',
                'name' => 'مدیریت محتوای راهنما',
                'group' => 'guide',
                'description' => 'ویرایش بخش‌ها، گام‌ها و پرسش‌های متداول راهنمای داشبورد کلینیک.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! DB::table('system_settings')->where('key', self::GUIDE_SETTING_KEY)->exists()) {
            DB::table('system_settings')->insert([
                'uuid' => (string) Str::uuid(),
                'key' => self::GUIDE_SETTING_KEY,
                'group' => 'guide',
                'label' => 'محتوای راهنمای کاربران کلینیک',
                'type' => 'json',
                'value_json' => json_encode(
                    ['value' => AdminGuideDefaults::content()],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                ),
                'encrypted_value' => null,
                'description' => 'بخش‌ها، گام‌ها و پرسش‌های متداول راهنمای کارکنان کلینیک.',
                'is_encrypted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Remove the guide setting and management permission introduced here.
     *
     * @return void No value is returned.
     *
     * @since 2.0.1
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', self::GUIDE_SETTING_KEY)
            ->delete();

        DB::table('permissions')
            ->where('code', 'guide.manage')
            ->delete();
    }
};
