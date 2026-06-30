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
     */
    public function permissions(): array
    {
        return [
            'system' => [
                ['code' => 'system.dashboard', 'name' => 'داشبورد سیستم'],
                ['code' => 'system.integrations.manage', 'name' => 'مدیریت API و یکپارچه‌سازی‌ها'],
            ],
            'ai_models' => [
                ['code' => 'ai_models.list', 'name' => 'فهرست مدل‌های هوش مصنوعی'],
                ['code' => 'ai_models.search', 'name' => 'جستجوی مدل‌های هوش مصنوعی'],
                ['code' => 'ai_models.view', 'name' => 'مشاهده مدل هوش مصنوعی'],
                ['code' => 'ai_models.create', 'name' => 'ایجاد دسترسی مدل هوش مصنوعی'],
                ['code' => 'ai_models.edit', 'name' => 'ویرایش دسترسی مدل هوش مصنوعی'],
                ['code' => 'ai_models.delete', 'name' => 'حذف دسترسی مدل هوش مصنوعی'],
                ['code' => 'ai_models.toggle', 'name' => 'فعال/غیرفعال کردن مدل هوش مصنوعی'],
                ['code' => 'ai_models.assign_to_clinic', 'name' => 'اتصال مدل هوش مصنوعی به کلینیک'],
                ['code' => 'ai_models.manage_credentials', 'name' => 'مدیریت کلیدهای مدل هوش مصنوعی'],
                ['code' => 'ai_models.manage_prompt', 'name' => 'مدیریت پرامپت مدل هوش مصنوعی'],
                ['code' => 'ai_models.manage_credit', 'name' => 'مدیریت اعتبار مدل هوش مصنوعی'],
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
                ['code' => 'settings.view', 'name' => 'مشاهده تنظیمات'],
                ['code' => 'settings.edit', 'name' => 'ویرایش تنظیمات'],
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
     * @return array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    public function roles(): array
    {
        $allPermissions = collect($this->permissions())->flatten(1)->pluck('code')->all();

        return [
            'super_admin' => [
                'name' => __('system.roles.super_admin'),
                'description' => __('system.role_descriptions.super_admin'),
                'permissions' => $allPermissions,
            ],
            'clinic_admin' => [
                'name' => __('system.roles.clinic_admin'),
                'description' => __('system.role_descriptions.clinic_admin'),
                'permissions' => [
                    'leads.list',
                    'leads.search',
                    'leads.view',
                    'leads.create',
                    'leads.edit',
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
                    'settings.view',
                ],
            ],
            'editor' => [
                'name' => __('system.roles.editor'),
                'description' => __('system.role_descriptions.editor'),
                'permissions' => [
                    'leads.list',
                    'leads.search',
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'plans.list',
                    'plans.view',
                    'reports.user.view',
                ],
            ],
            'viewer' => [
                'name' => __('system.roles.viewer'),
                'description' => __('system.role_descriptions.viewer'),
                'permissions' => [
                    'leads.list',
                    'leads.search',
                    'leads.view',
                    'plans.list',
                    'plans.view',
                    'reports.user.view',
                ],
            ],
        ];
    }
}
