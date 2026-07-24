<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers landing template permissions for existing installations without reseeding users or roles.
 *
 * @since 2.0.0
 */
return new class extends Migration
{
    /**
     * Add the system landing template permissions to the persisted permission catalog.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function up(): void
    {
        $now = now();

        foreach ($this->permissions() as $code => $name) {
            $existing = DB::table('permissions')->where('code', $code)->exists();

            if ($existing) {
                DB::table('permissions')->where('code', $code)->update([
                    'name' => $name,
                    'group' => 'landing_templates',
                    'updated_at' => $now,
                ]);

                continue;
            }

            DB::table('permissions')->insert([
                'uuid' => (string) Str::uuid(),
                'code' => $code,
                'name' => $name,
                'group' => 'landing_templates',
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Remove permissions introduced solely by this feature.
     *
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('code', array_keys($this->permissions()))
            ->delete();
    }

    /**
     * Return stable permission codes and their Persian labels.
     *
     * @return array<string, string> Landing template permission definitions.
     *
     * @since 2.0.0
     */
    private function permissions(): array
    {
        return [
            'landing_templates.view' => 'مشاهده قالب‌های لندینگ',
            'landing_templates.create' => 'ایجاد قالب لندینگ',
            'landing_templates.update' => 'ویرایش و انتخاب قالب پیش‌فرض لندینگ',
            'landing_templates.delete' => 'حذف قالب لندینگ',
            'landing_templates.assign' => 'تخصیص قالب لندینگ به کلینیک‌ها',
        ];
    }
};
