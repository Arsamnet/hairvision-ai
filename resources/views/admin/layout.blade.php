<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('dashboard.admin_title'))</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $faviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png'); ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    @include('admin.partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-shell h-screen overflow-hidden font-sans antialiased">
    @php
        $user = auth()->user();
        $tenant = $tenant ?? request()->attributes->get('adminTenant');
        $availableTenants = $availableTenants ?? request()->attributes->get('adminTenants');
        $planAccess = request()->attributes->get('planAccess', ['allowed' => true, 'status' => null, 'subscription' => null]);
        $planAllowsAccess = (bool) ($planAccess['allowed'] ?? true);
        $activeSubscription = $activeSubscription ?? request()->attributes->get('activeSubscription');

        $menuGroups = [
            [
                'label' => __('menu.groups.dashboard'),
                'icon' => 'layout-dashboard',
                'items' => [
                    ['label' => __('menu.items.dashboard'), 'route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'icon' => 'layout-dashboard'],
                ],
            ],
            [
                'label' => __('menu.groups.crm'),
                'icon' => 'contact',
                'items' => [
                    ['label' => __('menu.items.leads'), 'route' => 'admin.leads.index', 'active' => ['admin.leads.*'], 'icon' => 'contact', 'permissions' => ['leads.list'], 'planRestricted' => true],
                ],
            ],
            [
                'label' => __('menu.groups.users_access'),
                'icon' => 'users',
                'items' => [
                    ['label' => __('menu.items.users'), 'route' => 'admin.users.index', 'active' => ['admin.users.*'], 'icon' => 'users', 'permissions' => ['users.list'], 'planRestricted' => true],
                    ['label' => __('menu.items.access'), 'route' => 'admin.system.access.index', 'active' => ['admin.system.access.*'], 'icon' => 'users', 'permissions' => ['users.manage']],
                    ['label' => __('menu.items.roles'), 'route' => 'admin.system.roles.index', 'active' => ['admin.system.roles.*'], 'icon' => 'shield', 'permissions' => ['roles.list']],
                    ['label' => __('menu.items.permissions'), 'route' => 'admin.system.permissions.index', 'active' => ['admin.system.permissions.*'], 'icon' => 'shield', 'permissions' => ['permissions.list']],
                ],
            ],
            [
                'label' => __('menu.groups.plans_payments'),
                'icon' => 'credit-card',
                'items' => [
                    ['label' => __('menu.items.plans'), 'route' => 'admin.plans.index', 'active' => ['admin.plans.*'], 'icon' => 'package', 'permissions' => ['plans.list']],
                    ['label' => __('menu.items.billing'), 'route' => 'admin.billing.index', 'active' => ['admin.billing.*'], 'icon' => 'invoice', 'permissions' => ['billing.list']],
                    ['label' => __('menu.items.payment_gateways'), 'route' => 'admin.system.payment-gateways.index', 'active' => ['admin.system.payment-gateways.*'], 'icon' => 'credit-card', 'permissions' => ['payment_gateways.list']],
                ],
            ],
            [
                'label' => __('menu.groups.sms'),
                'icon' => 'message',
                'items' => [
                    ['label' => __('menu.items.system_sms_gateways'), 'route' => 'admin.system.sms-gateways.index', 'active' => ['admin.system.sms-gateways.*'], 'icon' => 'message', 'permissions' => ['sms_gateways.list']],
                ],
            ],
            [
                'label' => __('menu.groups.system_management'),
                'icon' => 'settings',
                'items' => [
                    ['label' => __('menu.items.ai_models'), 'route' => 'admin.ai-models.index', 'active' => ['admin.ai-models.*', 'admin.clinics.ai-models.*'], 'icon' => 'sparkles', 'permissions' => ['ai_models.list']],
                ],
            ],
            [
                'label' => __('menu.groups.reports'),
                'icon' => 'chart',
                'items' => [
                    ['label' => __('menu.items.user_reports'), 'route' => 'admin.reports', 'active' => ['admin.reports'], 'icon' => 'chart', 'permissions' => ['reports.user.view'], 'planRestricted' => true],
                    ['label' => __('menu.items.management_reports'), 'route' => 'admin.system.reports.index', 'active' => ['admin.system.reports.*'], 'icon' => 'chart', 'permissions' => ['reports.management.view']],
                ],
            ],
            [
                'label' => __('menu.groups.settings'),
                'icon' => 'settings',
                'items' => [
                    ['label' => __('menu.items.settings'), 'route' => 'admin.settings', 'active' => ['admin.settings'], 'icon' => 'settings', 'permissions' => ['settings.view']],
                    ['label' => __('menu.items.my_sms_gateways'), 'route' => 'admin.sms-gateways.index', 'active' => ['admin.sms-gateways.*'], 'icon' => 'message', 'planRestricted' => true, 'clinicOnly' => true],
                    ['label' => __('menu.items.system_settings'), 'route' => 'admin.system.settings.edit', 'active' => ['admin.system.settings.*'], 'icon' => 'settings', 'permissions' => ['settings.manage']],
                ],
            ],
        ];

        $canSeeItem = function (array $item) use ($user, $tenant): bool {
            if (($item['clinicOnly'] ?? false) && ($user?->isSuperAdmin() ?? false)) {
                return false;
            }

            $permissions = $item['permissions'] ?? [];

            return empty($permissions) || ($user?->hasAnyPermission($permissions, $tenant?->id) ?? false);
        };

        $itemIsLocked = function (array $item) use ($planAllowsAccess): bool {
            return (bool) ($item['planRestricted'] ?? false) && ! $planAllowsAccess;
        };

        $itemIsActive = function (array $item): bool {
            foreach ($item['active'] ?? [] as $pattern) {
                if (request()->routeIs($pattern)) {
                    return true;
                }
            }

            return false;
        };

        $visibleItemsForGroup = fn (array $group) => collect($group['items'])->filter(fn (array $item): bool => $canSeeItem($item))->values();
        $groupHasVisibleItems = fn (array $group): bool => $visibleItemsForGroup($group)->isNotEmpty();
        $groupIsActive = fn (array $group): bool => $visibleItemsForGroup($group)->contains(fn (array $item): bool => $itemIsActive($item));
        $menuItemClass = fn (bool $active, bool $locked = false): string => 'admin-menu-link'.($active ? ' is-active' : '').($locked ? ' is-locked' : '');
    @endphp

    <div class="admin-app-shell h-screen overflow-hidden lg:grid lg:grid-cols-[18.5rem_1fr]">
        <aside class="admin-sidebar hidden lg:block lg:h-screen">
            <div class="flex h-full min-h-0 flex-col">
                <div class="admin-sidebar-logo flex h-18.25 shrink-0 items-center justify-center px-5">
                    <a href="{{ route('admin.dashboard') }}" class="admin-brand-logo" aria-label="{{ __('dashboard.product_name') }}">
                        <img src="/theme/admin-logo-light.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-light">
                        <img src="/theme/admin-logo-dark.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-dark">
                    </a>
                </div>

                @include('admin.partials.navigation-menu')

            </div>
        </aside>

        <div class="admin-mobile-menu-layer lg:hidden" data-admin-mobile-menu>
            <x-admin.button type="button" variant="unstyled" size="none" class="admin-mobile-menu-backdrop" aria-label="{{ __('dashboard.mobile_menu.close') }}" data-admin-mobile-menu-close />

            <aside id="admin-mobile-drawer" class="admin-mobile-drawer" role="dialog" aria-modal="true" aria-labelledby="admin-mobile-menu-title" aria-hidden="true" data-admin-mobile-drawer>
                <div class="admin-mobile-drawer-header">
                    <a href="{{ route('admin.dashboard') }}" class="admin-brand-logo" aria-label="{{ __('dashboard.product_name') }}" data-admin-mobile-menu-close>
                        <img src="/theme/admin-logo-light.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-light">
                        <img src="/theme/admin-logo-dark.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-dark">
                    </a>
                    <x-admin.button type="button" variant="unstyled" size="none" class="admin-mobile-menu-button" aria-label="{{ __('dashboard.mobile_menu.close') }}" title="{{ __('dashboard.mobile_menu.close') }}" data-admin-mobile-menu-close data-admin-mobile-menu-autofocus>
                        <x-admin.icon name="x" class="h-5 w-5" />
                    </x-admin.button>
                </div>

                <p id="admin-mobile-menu-title" class="sr-only">{{ __('dashboard.mobile_menu.title') }}</p>
                @include('admin.partials.navigation-menu', [
                    'navigationClass' => 'admin-menu-scroll min-h-0 flex-1 space-y-1 overflow-y-auto p-3 text-sm font-bold',
                    'closeOnNavigate' => true,
                ])
            </aside>
        </div>

        <div class="admin-main-panel flex h-screen min-h-0 min-w-0 flex-col">
            <header class="admin-topbar z-20 shrink-0 backdrop-blur">
                <div class="admin-mobile-topbar lg:hidden">
                    <div class="admin-mobile-topbar-side admin-mobile-topbar-menu">
                        <x-admin.button type="button" variant="unstyled" size="none" class="admin-mobile-menu-button" aria-controls="admin-mobile-drawer" aria-expanded="false" aria-label="{{ __('dashboard.mobile_menu.open') }}" title="{{ __('dashboard.mobile_menu.open') }}" data-admin-mobile-menu-open>
                            <x-admin.icon name="menu-dots-vertical" class="h-5 w-5" />
                        </x-admin.button>
                    </div>

                    <div class="admin-mobile-brand-center">
                        <a href="{{ route('admin.dashboard') }}" class="admin-brand-logo" aria-label="{{ __('dashboard.product_name') }}">
                            <img src="/theme/admin-logo-light.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-light">
                            <img src="/theme/admin-logo-dark.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-dark">
                        </a>
                    </div>

                    <div class="admin-mobile-topbar-side admin-mobile-topbar-user">
                        @include('admin.partials.user-menu', [
                            'menuClass' => 'admin-user-menu admin-mobile-user-menu',
                            'triggerClass' => 'admin-user-menu-trigger admin-user-menu-avatar-trigger',
                        ])
                    </div>
                </div>

                <div class="admin-topbar-wrapper hidden min-h-18.25 flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:flex">
                    <div class="admin-topbar-heading flex min-w-0 items-center gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-teal-600">{{ __('dashboard.management_panel') }}</p>
                            <h1 class="mt-1 truncate text-lg font-bold">@yield('page_title', __('dashboard.dashboard'))</h1>
                        </div>
                    </div>
                    <div class="admin-topbar-actions flex flex-wrap items-center gap-2">
                        @if($activeSubscription)
                            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                                {{ $activeSubscription->plan?->name }} - {{ __('dashboard.plan.remaining_credit', ['count' => number_format(max(0, $activeSubscription->usage_limit - $activeSubscription->usage_used))]) }}
                            </span>
                        @elseif(($planAccess['status']->value ?? null) !== 'exempt')
                            <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('dashboard.plan.'.($planAccess['status']->value ?? 'inactive')) }}</span>
                        @endif

                        @include('admin.partials.user-menu')

                    </div>
                </div>
            </header>

            <main class="admin-content min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6 lg:px-8">
                @if(session('status'))
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <x-admin.modal id="admin-confirm-modal" :label="__('messages.confirm_action')" :title="__('messages.confirm_action')" icon="trash" :trigger="false">
        <div data-admin-confirm-modal>
            <p class="text-sm leading-7 text-zinc-600" data-admin-confirm-message>{{ __('messages.confirm_delete') }}</p>
            <div class="mt-5 flex flex-wrap items-center justify-end gap-2">
                <x-admin.button type="button" variant="secondary" size="compact" data-admin-confirm-cancel>
                    {{ __('messages.cancel') }}
                </x-admin.button>
                <x-admin.button type="button" variant="danger" size="compact" icon="trash" data-admin-confirm-submit>
                    <span>{{ __('messages.delete') }}</span>
                </x-admin.button>
            </div>
        </div>
    </x-admin.modal>
</body>
</html>
