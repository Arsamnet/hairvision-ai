<?php

namespace App\Services\Auth;

use App\Models\Permission;
use Illuminate\Support\Collection;

/**
 * Canonical permission and access-level definitions for HairVision.
 *
 * @since 1.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
class PermissionCatalog
{
    /**
     * Return all backend permissions grouped by module.
     *
     * @return array<string, array<int, array{code: string, name: string, description?: string}>>
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
                ['code' => 'notifications.manage', 'name' => 'مدیریت اعلان‌ها'],
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
                ['code' => 'ai_model_categories.view', 'name' => 'مشاهده کاربردهای هوش مصنوعی'],
                ['code' => 'ai_model_categories.create', 'name' => 'ایجاد کاربرد هوش مصنوعی'],
                ['code' => 'ai_model_categories.update', 'name' => 'ویرایش کاربرد هوش مصنوعی'],
                ['code' => 'ai_model_categories.delete', 'name' => 'حذف کاربرد هوش مصنوعی'],
                ['code' => 'ai_model_category_outputs.manage', 'name' => 'مدیریت خروجی‌های کاربرد هوش مصنوعی'],
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
                ['code' => 'landing_templates.view', 'name' => 'مشاهده قالب‌ها'],
                ['code' => 'landing_templates.create', 'name' => 'ایجاد قالب'],
                ['code' => 'landing_templates.update', 'name' => 'ویرایش و انتخاب قالب پیش‌فرض'],
                ['code' => 'landing_templates.delete', 'name' => 'حذف قالب'],
                ['code' => 'landing_templates.assign', 'name' => 'تخصیص قالب به کلینیک‌ها'],
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
                ['code' => 'roles.list', 'name' => 'فهرست نقش‌های مدیریتی'],
                ['code' => 'roles.view', 'name' => 'مشاهده نقش مدیریتی'],
                ['code' => 'roles.create', 'name' => 'ایجاد نقش مدیریتی'],
                ['code' => 'roles.edit', 'name' => 'ویرایش نقش مدیریتی'],
                ['code' => 'roles.delete', 'name' => 'حذف نقش مدیریتی'],
                ['code' => 'roles.manage', 'name' => 'مدیریت نقش‌های مدیریتی'],
            ],
            'permissions' => [
                ['code' => 'permissions.list', 'name' => 'فهرست مجوزها'],
                ['code' => 'permissions.view', 'name' => 'مشاهده مجوزها'],
                ['code' => 'permissions.manage', 'name' => 'مدیریت مجوزها'],
            ],
            'settings' => [
                ['code' => 'settings.manage', 'name' => 'مدیریت تنظیمات سیستم'],
                ['code' => 'clinic_settings.manage', 'name' => 'مدیریت تنظیمات کلینیک'],
            ],
            'guide' => [
                ['code' => 'guide.manage', 'name' => 'مدیریت محتوای راهنما'],
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
                ['code' => 'billing.list', 'name' => 'فهرست فاکتورها و پرداخت‌ها'],
                ['code' => 'billing.view', 'name' => 'مشاهده فاکتورها و پرداخت‌ها'],
                ['code' => 'billing.pay', 'name' => 'پرداخت فاکتورهای خود'],
                ['code' => 'billing.manage', 'name' => 'مدیریت فاکتورها و پرداخت‌ها'],
            ],
            'reports' => [
                ['code' => 'reports.management.view', 'name' => 'مشاهده گزارش‌های مدیریتی'],
                ['code' => 'reports.user.view', 'name' => 'مشاهده گزارش عملکرد و مصرف اعتبار'],
            ],
        ];
    }

    /**
     * Return permission display groups aligned with administrator navigation.
     *
     * @return array<string, string>
     *
     * @since 2.0.1
     */
    public function navigationGroups(): array
    {
        return [
            'crm' => __('menu.groups.crm'),
            'security' => __('menu.groups.users_access'),
            'finance' => __('menu.groups.plans_payments'),
            'reports' => __('menu.groups.reports'),
            'settings' => __('menu.groups.settings'),
        ];
    }

    /**
     * Group persisted permissions for display using the menu hierarchy.
     *
     * Stable permission codes and persisted resource groups remain unchanged;
     * only the presentation hierarchy and canonical Persian names are applied.
     *
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<string, Collection<int, Permission>>
     *
     * @since 2.0.1
     */
    public function groupForDisplay(Collection $permissions): Collection
    {
        $definitions = collect($this->permissions())
            ->flatten(1)
            ->keyBy('code');

        $permissions->each(function (Permission $permission) use ($definitions): void {
            $definition = $definitions->get($permission->code);
            $permission->setAttribute('display_name', $definition['name'] ?? $permission->name);
        });

        $grouped = $permissions
            ->sortBy('code')
            ->groupBy(fn (Permission $permission): string => $this->navigationGroupFor($permission->group));

        return collect($this->navigationGroups())
            ->mapWithKeys(fn (string $label, string $group): array => [
                $group => $grouped->get($group, collect()),
            ])
            ->filter(fn (Collection $groupPermissions): bool => $groupPermissions->isNotEmpty());
    }

    /**
     * Map a persisted permission resource group to its navigation group.
     *
     * @param  string  $permissionGroup  Persisted permission group.
     * @return string Navigation group key.
     *
     * @since 2.0.1
     */
    private function navigationGroupFor(string $permissionGroup): string
    {
        return match ($permissionGroup) {
            'leads' => 'crm',
            'users', 'roles', 'permissions' => 'security',
            'plans', 'billing', 'payment_gateways' => 'finance',
            'reports' => 'reports',
            default => 'settings',
        };
    }

    /**
     * Return default access levels and their permissions.
     *
     * @return array<string, array{name: string, description: string, scope: string, permissions: array<int, string>}>
     *
     * @since 1.0.0
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
                    'leads.delete',
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
                    'clinic_settings.manage',
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
