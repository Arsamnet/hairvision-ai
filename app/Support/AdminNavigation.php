<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Provides the canonical permission-aware administrator navigation structure.
 *
 * Menu rendering, page context, and breadcrumbs share this catalog so labels
 * and icons cannot drift between navigation surfaces.
 *
 * @since 2.0.1
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
 */
final class AdminNavigation
{
    /**
     * Return canonical administrator navigation groups and items.
     *
     * @return array<int, array{
     *     key: string,
     *     label: string,
     *     icon: string,
     *     forceSubmenu?: bool,
     *     items: array<int, array{
     *         key: string,
     *         label: string,
     *         route: string,
     *         active: array<int, string>,
     *         icon: string,
     *         permissions?: array<int, string>,
     *         planRestricted?: bool,
     *         clinicOnly?: bool,
     *         systemOnly?: bool
     *     }>
     * }>
     *
     * @since 2.0.1
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public static function groups(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => __('menu.groups.dashboard'),
                'icon' => 'layout-dashboard',
                'items' => [
                    [
                        'key' => 'dashboard',
                        'label' => __('menu.items.dashboard'),
                        'route' => 'admin.dashboard',
                        'active' => ['admin.dashboard'],
                        'icon' => 'layout-dashboard',
                    ],
                ],
            ],
            [
                'key' => 'crm',
                'label' => __('menu.groups.crm'),
                'icon' => 'contact',
                'items' => [
                    [
                        'key' => 'leads',
                        'label' => __('menu.items.leads'),
                        'route' => 'admin.leads.index',
                        'active' => ['admin.leads.*'],
                        'icon' => 'contact',
                        'permissions' => ['leads.list'],
                        'planRestricted' => true,
                    ],
                ],
            ],
            [
                'key' => 'security',
                'label' => __('menu.groups.users_access'),
                'icon' => 'security',
                'items' => [
                    [
                        'key' => 'users',
                        'label' => __('menu.items.users'),
                        'route' => 'admin.users.index',
                        'active' => ['admin.users.*'],
                        'icon' => 'users',
                        'permissions' => ['users.list'],
                        'planRestricted' => true,
                    ],
                    [
                        'key' => 'roles',
                        'label' => __('menu.items.roles'),
                        'route' => 'admin.system.roles.index',
                        'active' => ['admin.system.roles.*'],
                        'icon' => 'role',
                        'permissions' => ['roles.list'],
                    ],
                    [
                        'key' => 'permissions',
                        'label' => __('menu.items.permissions'),
                        'route' => 'admin.system.permissions.index',
                        'active' => ['admin.system.permissions.*'],
                        'icon' => 'permission',
                        'permissions' => ['permissions.list'],
                    ],
                ],
            ],
            [
                'key' => 'finance',
                'label' => __('menu.groups.plans_payments'),
                'icon' => 'finance',
                'items' => [
                    [
                        'key' => 'plans',
                        'label' => __('menu.items.plans'),
                        'route' => 'admin.plans.index',
                        'active' => ['admin.plans.*', 'admin.system.plans.*'],
                        'icon' => 'package',
                        'permissions' => ['plans.list'],
                    ],
                    [
                        'key' => 'billing',
                        'label' => __('menu.items.billing'),
                        'route' => 'admin.billing.index',
                        'active' => ['admin.billing.*'],
                        'icon' => 'invoice',
                        'permissions' => ['billing.list'],
                    ],
                    [
                        'key' => 'payment-gateways',
                        'label' => __('menu.items.payment_gateways'),
                        'route' => 'admin.system.payment-gateways.index',
                        'active' => ['admin.system.payment-gateways.*'],
                        'icon' => 'credit-card',
                        'permissions' => ['payment_gateways.list'],
                    ],
                ],
            ],
            [
                'key' => 'reports',
                'label' => __('menu.groups.reports'),
                'icon' => 'chart',
                'items' => [
                    [
                        'key' => 'performance',
                        'label' => __('menu.items.user_reports'),
                        'route' => 'admin.reports',
                        'active' => ['admin.reports'],
                        'icon' => 'report-user',
                        'permissions' => ['reports.user.view'],
                        'planRestricted' => true,
                    ],
                    [
                        'key' => 'credit-usage',
                        'label' => __('menu.items.credit_usage_report'),
                        'route' => 'admin.reports.credit-usage',
                        'active' => ['admin.reports.credit-usage'],
                        'icon' => 'report-credit',
                        'permissions' => ['reports.user.view'],
                        'planRestricted' => true,
                        'clinicOnly' => true,
                    ],
                    [
                        'key' => 'management',
                        'label' => __('menu.items.management_reports'),
                        'route' => 'admin.system.reports.index',
                        'active' => ['admin.system.reports.*'],
                        'icon' => 'report-management',
                        'permissions' => ['reports.management.view'],
                        'systemOnly' => true,
                    ],
                ],
            ],
            [
                'key' => 'help',
                'label' => __('menu.groups.guide'),
                'icon' => 'help-center',
                'items' => [
                    [
                        'key' => 'guide',
                        'label' => __('menu.items.guide'),
                        'route' => 'admin.guide.show',
                        'active' => ['admin.guide.show'],
                        'icon' => 'guide',
                        'clinicOnly' => true,
                    ],
                    [
                        'key' => 'faq',
                        'label' => __('menu.items.faq'),
                        'route' => 'admin.guide.faq',
                        'active' => ['admin.guide.faq'],
                        'icon' => 'faq',
                        'clinicOnly' => true,
                    ],
                ],
            ],
            [
                'key' => 'settings',
                'label' => __('menu.groups.settings'),
                'icon' => 'settings',
                'items' => [
                    [
                        'key' => 'clinics',
                        'label' => __('menu.items.clinics'),
                        'route' => 'admin.clinics.index',
                        'active' => ['admin.clinics.index', 'admin.clinics.show', 'admin.clinics.edit'],
                        'icon' => 'building',
                        'permissions' => ['tenants.list'],
                    ],
                    [
                        'key' => 'templates',
                        'label' => __('menu.items.landing_templates'),
                        'route' => 'admin.system.landing-templates.index',
                        'active' => ['admin.system.landing-templates.*'],
                        'icon' => 'template',
                        'permissions' => ['landing_templates.view'],
                    ],
                    [
                        'key' => 'notifications',
                        'label' => __('menu.items.system_notifications'),
                        'route' => 'admin.system.notifications.index',
                        'active' => ['admin.system.notifications.*'],
                        'icon' => 'bell',
                        'permissions' => ['notifications.manage'],
                    ],
                    [
                        'key' => 'ai-applications',
                        'label' => __('menu.items.ai_model_categories'),
                        'route' => 'admin.system.ai-model-categories.index',
                        'active' => ['admin.system.ai-model-categories.*'],
                        'icon' => 'ai-applications',
                        'permissions' => ['ai_model_categories.view'],
                    ],
                    [
                        'key' => 'ai-models',
                        'label' => __('menu.items.ai_models'),
                        'route' => 'admin.ai-models.index',
                        'active' => ['admin.ai-models.*', 'admin.clinics.ai-models.*'],
                        'icon' => 'ai-model',
                        'permissions' => ['ai_models.list'],
                    ],
                    [
                        'key' => 'sms-gateways',
                        'label' => __('menu.items.system_sms_gateways'),
                        'route' => 'admin.system.sms-gateways.index',
                        'active' => ['admin.system.sms-gateways.*'],
                        'icon' => 'message',
                        'permissions' => ['sms_gateways.list'],
                    ],
                    [
                        'key' => 'clinic-settings',
                        'label' => __('menu.items.clinic_settings'),
                        'route' => 'admin.clinic.settings.edit',
                        'active' => ['admin.clinic.settings.*'],
                        'icon' => 'settings',
                        'permissions' => ['clinic_settings.manage'],
                        'clinicOnly' => true,
                    ],
                    [
                        'key' => 'general',
                        'label' => __('menu.items.system_settings'),
                        'route' => 'admin.system.settings.edit',
                        'active' => ['admin.system.settings.*'],
                        'icon' => 'settings',
                        'permissions' => ['settings.manage'],
                    ],
                    [
                        'key' => 'guide-management',
                        'label' => __('menu.items.guide_management'),
                        'route' => 'admin.system.guide.edit',
                        'active' => ['admin.system.guide.*'],
                        'icon' => 'guide',
                        'permissions' => ['guide.manage'],
                    ],
                    [
                        'key' => 'faq-management',
                        'label' => __('menu.items.faq_management'),
                        'route' => 'admin.system.faq.edit',
                        'active' => ['admin.system.faq.*'],
                        'icon' => 'faq',
                        'permissions' => ['guide.manage'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Resolve the navigation group and item for a route name.
     *
     * @param  string|null  $routeName  Named route to resolve; defaults to the active route.
     * @return array{
     *     group: array<string, mixed>|null,
     *     item: array<string, mixed>|null
     * }
     *
     * @since 2.0.1
     */
    public static function context(?string $routeName = null): array
    {
        $resolvedRouteName = $routeName ?? request()->route()?->getName();

        if (blank($resolvedRouteName)) {
            return ['group' => null, 'item' => null];
        }

        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                foreach ($item['active'] as $pattern) {
                    if (Str::is($pattern, $resolvedRouteName)) {
                        return ['group' => $group, 'item' => $item];
                    }
                }
            }
        }

        if (Str::is('admin.notifications.*', $resolvedRouteName)) {
            return self::supplementalContext(
                'dashboard',
                __('notifications.inbox'),
                'admin.notifications.index',
                'bell'
            );
        }

        if ($resolvedRouteName === 'admin.system.dashboard') {
            return self::supplementalContext(
                'settings',
                __('system.dashboard_title'),
                'admin.system.dashboard',
                'settings'
            );
        }

        return ['group' => null, 'item' => null];
    }

    /**
     * Build context for an administrator destination outside the sidebar list.
     *
     * @param  string  $groupKey  Canonical navigation group key.
     * @param  string  $label  Destination label.
     * @param  string  $route  Destination route.
     * @param  string  $icon  Semantic icon alias.
     * @return array{group: array<string, mixed>|null, item: array<string, mixed>}
     *
     * @since 2.0.1
     */
    private static function supplementalContext(string $groupKey, string $label, string $route, string $icon): array
    {
        $group = collect(self::groups())->firstWhere('key', $groupKey);

        return [
            'group' => $group,
            'item' => [
                'key' => $route,
                'label' => $label,
                'route' => $route,
                'active' => [$route.'.*', $route],
                'icon' => $icon,
            ],
        ];
    }
}
