<?php

namespace App\Services\Auth;

/**
 * Canonical permission and access-level definitions for HairVision.
 *
 * @since 1.0.0
 */
class PermissionCatalog
{
    /**
     * Return all backend permissions grouped by module.
     *
     * @return array<string, array<int, array{code: string, name: string, description?: string}>>
     *
     * @since 1.0.0
     */
    public function permissions(): array
    {
        return [
            'system' => [
                ['code' => 'system.dashboard', 'name' => 'داشبورد سیستم'],
                ['code' => 'system.integrations.manage', 'name' => 'مدیریت API و یکپارچه‌سازی‌ها'],
            ],
            'notifications' => [
                ['code' => 'notifications.view', 'name' => 'مشاهده پیام‌ها و اعلان‌های کلینیک'],
                ['code' => 'notifications.manage', 'name' => 'مدیریت پیام‌ها و اعلان‌های کلینیک‌ها'],
            ],
            'ai_models' => [
                ['code' => 'ai_models.list', 'name' => 'فهرست مدل‌های هوش مصنوعی'],
                ['code' => 'ai_models.search', 'name' => 'جستجوی مدل‌های هوش مصنوعی'],
                ['code' => 'ai_models.view', 'name' => 'مشاهده مدل هوش مصنوعی'],
                ['code' => 'ai_models.create', 'name' => 'ایجاد دسترسی مدل هوش مصنوعی'],
                ['code' => 'ai_models.edit', 'name' => 'ویرایش دسترسی مدل هوش مصنوعی'],
                ['code' => 'ai_models.delete', 'name' => 'حذف دسترسی مدل هوش مصنوعی'],
                ['code' => 'ai_models.toggle', 'name' => 'فعال/غیرفعال کردن مدل هوش مصنوعی'],
                ['code' => 'ai_models.update', 'name' => 'به‌روزرسانی مدل هوش مصنوعی'],
                ['code' => 'ai_models.activate', 'name' => 'فعال‌سازی مدل هوش مصنوعی'],
                ['code' => 'ai_models.test_connection', 'name' => 'آزمون اتصال مدل هوش مصنوعی'],
                ['code' => 'ai_models.assign_to_clinic', 'name' => 'اتصال مدل هوش مصنوعی به کلینیک'],
                ['code' => 'ai_models.manage_credentials', 'name' => 'مدیریت کلیدهای مدل هوش مصنوعی'],
                ['code' => 'ai_models.manage_credit', 'name' => 'مدیریت اعتبار مدل هوش مصنوعی'],
            ],
            'ai_model_categories' => [
                ['code' => 'ai_model_categories.view', 'name' => 'مشاهده دسته‌بندی‌های مدل'],
                ['code' => 'ai_model_categories.create', 'name' => 'ایجاد دسته‌بندی مدل'],
                ['code' => 'ai_model_categories.update', 'name' => 'ویرایش دسته‌بندی مدل'],
                ['code' => 'ai_model_categories.delete', 'name' => 'حذف دسته‌بندی مدل'],
                ['code' => 'ai_model_category_outputs.manage', 'name' => 'مدیریت خروجی‌های دسته‌بندی مدل'],
                ['code' => 'ai_model_types.view', 'name' => 'مشاهده نوع‌های مدل'],
                ['code' => 'ai_model_jobs.view', 'name' => 'مشاهده صف مدل‌ها'],
                ['code' => 'ai_model_jobs.retry', 'name' => 'اجرای دوباره تلاش مدل'],
                ['code' => 'ai_model_jobs.cancel', 'name' => 'لغو تلاش مدل'],
                ['code' => 'lead_archives.view', 'name' => 'مشاهده آرشیو لید'],
                ['code' => 'lead_archives.download', 'name' => 'دریافت آرشیو لید'],
            ],
            'tenants' => [
                ['code' => 'tenants.list', 'name' => 'فهرست کلینیک‌ها'],
                ['code' => 'tenants.search', 'name' => 'جستجوی کلینیک‌ها'],
                ['code' => 'tenants.view', 'name' => 'مشاهده کلینیک'],
                ['code' => 'tenants.create', 'name' => 'ایجاد کلینیک'],
                ['code' => 'tenants.edit', 'name' => 'ویرایش کلینیک'],
                ['code' => 'tenants.delete', 'name' => 'حذف کلینیک'],
            ],
            'landing_templates' => [
                ['code' => 'landing_templates.view', 'name' => 'مشاهده قالب‌های لندینگ'],
                ['code' => 'landing_templates.create', 'name' => 'ایجاد قالب لندینگ'],
                ['code' => 'landing_templates.update', 'name' => 'ویرایش و انتخاب قالب پیش‌فرض لندینگ'],
                ['code' => 'landing_templates.delete', 'name' => 'حذف قالب لندینگ'],
                ['code' => 'landing_templates.assign', 'name' => 'تخصیص قالب لندینگ به کلینیک‌ها'],
            ],
            'users' => [
                ['code' => 'users.list', 'name' => 'فهرست کاربران'],
                ['code' => 'users.search', 'name' => 'جستجوی کاربران'],
                ['code' => 'users.view', 'name' => 'مشاهده کاربران'],
                ['code' => 'users.create', 'name' => 'ایجاد کاربر'],
                ['code' => 'users.edit', 'name' => 'ویرایش کاربر'],
                ['code' => 'users.delete', 'name' => 'حذف کاربر'],
                ['code' => 'users.manage', 'name' => 'مدیریت دسترسی کاربران'],
            ],
            'leads' => [
                ['code' => 'leads.list', 'name' => 'فهرست لیدها'],
                ['code' => 'leads.search', 'name' => 'جستجوی لیدها'],
                ['code' => 'leads.view', 'name' => 'مشاهده لید'],
                ['code' => 'leads.create', 'name' => 'ایجاد لید'],
                ['code' => 'leads.edit', 'name' => 'ویرایش لید'],
                ['code' => 'leads.delete', 'name' => 'حذف لید'],
                ['code' => 'leads.export', 'name' => 'خروجی لیدها'],
                ['code' => 'leads.manage', 'name' => 'مدیریت لیدها'],
            ],
            'roles' => [
                ['code' => 'roles.list', 'name' => 'فهرست سطوح دسترسی'],
                ['code' => 'roles.view', 'name' => 'مشاهده سطح دسترسی'],
                ['code' => 'roles.create', 'name' => 'ایجاد سطح دسترسی'],
                ['code' => 'roles.edit', 'name' => 'ویرایش سطح دسترسی'],
                ['code' => 'roles.delete', 'name' => 'حذف سطح دسترسی'],
                ['code' => 'roles.manage', 'name' => 'مدیریت سطوح دسترسی'],
            ],
            'permissions' => [
                ['code' => 'permissions.list', 'name' => 'فهرست مجوزها'],
                ['code' => 'permissions.view', 'name' => 'مشاهده مجوزها'],
                ['code' => 'permissions.manage', 'name' => 'مدیریت مجوزها'],
            ],
            'settings' => [
                ['code' => 'settings.manage', 'name' => 'مدیریت تنظیمات سیستم'],
            ],
            'sms_gateways' => [
                ['code' => 'sms_gateways.list', 'name' => 'فهرست درگاه‌های پیامک'],
                ['code' => 'sms_gateways.view', 'name' => 'مشاهده درگاه پیامک'],
                ['code' => 'sms_gateways.create', 'name' => 'ایجاد درگاه پیامک'],
                ['code' => 'sms_gateways.edit', 'name' => 'ویرایش درگاه پیامک'],
                ['code' => 'sms_gateways.delete', 'name' => 'حذف درگاه پیامک'],
                ['code' => 'sms_gateways.manage', 'name' => 'مدیریت درگاه‌های پیامک'],
            ],
            'payment_gateways' => [
                ['code' => 'payment_gateways.list', 'name' => 'فهرست درگاه‌های پرداخت'],
                ['code' => 'payment_gateways.view', 'name' => 'مشاهده درگاه پرداخت'],
                ['code' => 'payment_gateways.create', 'name' => 'ایجاد درگاه پرداخت'],
                ['code' => 'payment_gateways.edit', 'name' => 'ویرایش درگاه پرداخت'],
                ['code' => 'payment_gateways.delete', 'name' => 'حذف درگاه پرداخت'],
                ['code' => 'payment_gateways.manage', 'name' => 'مدیریت درگاه‌های پرداخت'],
            ],
            'plans' => [
                ['code' => 'plans.list', 'name' => 'فهرست پلن‌ها'],
                ['code' => 'plans.view', 'name' => 'مشاهده پلن‌ها'],
                ['code' => 'plans.create', 'name' => 'ایجاد پلن'],
                ['code' => 'plans.edit', 'name' => 'ویرایش پلن'],
                ['code' => 'plans.delete', 'name' => 'حذف پلن'],
                ['code' => 'plans.manage', 'name' => 'مدیریت پلن‌ها'],
            ],
            'billing' => [
                ['code' => 'billing.list', 'name' => 'فهرست مالی'],
                ['code' => 'billing.view', 'name' => 'مشاهده مالی'],
                ['code' => 'billing.pay', 'name' => 'پرداخت فاکتورهای خود'],
                ['code' => 'billing.manage', 'name' => 'مدیریت مالی'],
            ],
            'reports' => [
                ['code' => 'reports.management.view', 'name' => 'مشاهده گزارش‌های مدیریتی'],
                ['code' => 'reports.user.view', 'name' => 'مشاهده گزارش‌های خود'],
            ],
        ];
    }

    /**
     * Return default access levels and their permissions.
     *
     * @return array<string, array{name: string, description: string, scope: string, permissions: array<int, string>}>
     *
     * @since 1.0.0
     */
    public function roles(): array
    {
        $allPermissions = collect($this->permissions())->flatten(1)->pluck('code')->all();

        return [
            'super_admin' => [
                'name' => __('system.roles.super_admin'),
                'description' => __('system.role_descriptions.super_admin'),
                'scope' => 'platform',
                'permissions' => $allPermissions,
            ],
            'clinic_admin' => [
                'name' => __('system.roles.clinic_admin'),
                'description' => __('system.role_descriptions.clinic_admin'),
                'scope' => 'clinic',
                'permissions' => [
                    'leads.list',
                    'leads.search',
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'notifications.view',
                    'users.list',
                    'users.search',
                    'users.view',
                    'users.create',
                    'users.edit',
                    'billing.list',
                    'billing.view',
                    'billing.pay',
                    'plans.list',
                    'plans.view',
                    'reports.user.view',
                    'sms_gateways.view',
                    'sms_gateways.edit',
                ],
            ],
            'editor' => [
                'name' => __('system.roles.editor'),
                'description' => __('system.role_descriptions.editor'),
                'scope' => 'clinic',
                'permissions' => [
                    'leads.list',
                    'leads.search',
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'notifications.view',
                    'plans.list',
                    'plans.view',
                    'reports.user.view',
                ],
            ],
            'viewer' => [
                'name' => __('system.roles.viewer'),
                'description' => __('system.role_descriptions.viewer'),
                'scope' => 'clinic',
                'permissions' => [
                    'leads.list',
                    'leads.search',
                    'leads.view',
                    'notifications.view',
                    'plans.list',
                    'plans.view',
                    'reports.user.view',
                ],
            ],
        ];
    }
}
