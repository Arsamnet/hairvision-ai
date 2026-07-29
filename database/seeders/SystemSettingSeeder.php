<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * Provides system setting seeder behavior.
 *
 * @since 1.0.0
 */
class SystemSettingSeeder extends Seeder
{
    /**
     * Execute the system setting seeder operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        SystemSetting::query()
            ->where('group', 'catalog')
            ->orWhere('key', 'like', 'catalog.states.%')
            ->delete();

        foreach ($this->defaultSettings() as $key => $setting) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $setting['group'],
                    'label' => $setting['label'],
                    'type' => $setting['type'],
                    'value_json' => ['value' => $setting['value']],
                    'description' => $setting['description'],
                    'is_encrypted' => false,
                ]
            );
        }
    }

    /**
     * Execute the default settings operation.
     *
     * @return array<string, array{group: string, label: string, type: string, value: mixed, description: string}>
     *
     * @since 1.0.0
     */
    private function defaultSettings(): array
    {
        return [
            'system.name' => [
                'group' => 'general',
                'label' => 'نام سیستم',
                'type' => 'string',
                'value' => 'HairVision',
                'description' => 'نام نمایشی در صفحه‌های مدیریتی پلتفرم.',
            ],
            'support.phone' => [
                'group' => 'general',
                'label' => 'شماره پشتیبانی',
                'type' => 'string',
                'value' => '09124248164',
                'description' => 'شماره تماس اصلی پشتیبانی برای کاربران داشبورد.',
            ],
            'notifications.global_enabled' => [
                'group' => 'notifications',
                'label' => 'اعلان‌های سراسری',
                'type' => 'boolean',
                'value' => true,
                'description' => 'ارسال اعلان‌های سطح پلتفرم را کنترل می‌کند.',
            ],
            'billing.invoice_due_days' => [
                'group' => 'billing',
                'label' => 'مهلت پرداخت فاکتور',
                'type' => 'integer',
                'value' => 7,
                'description' => 'مهلت پیش‌فرض پرداخت فاکتورهای ایجاد شده.',
            ],
            'api.pagination.default_per_page' => [
                'group' => 'api',
                'label' => 'تعداد پیش‌فرض هر صفحه API',
                'type' => 'integer',
                'value' => config('hairvision.pagination.default_per_page'),
                'description' => 'تعداد پیش‌فرض آیتم‌ها در صفحه‌بندی‌های مشترک.',
            ],
            'api.pagination.max_per_page' => [
                'group' => 'api',
                'label' => 'حداکثر تعداد هر صفحه API',
                'type' => 'integer',
                'value' => config('hairvision.pagination.max_per_page'),
                'description' => 'حداکثر تعداد قابل قبول برای صفحه‌بندی‌های مشترک.',
            ],
        ];
    }
}
