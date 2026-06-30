<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('dashboard.admin_title'))</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $faviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png'); ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    <script data-admin-theme-script>
        (() => {
            const key = 'hairvision.admin.theme';
            const root = document.documentElement;
            let stored = null;

            try {
                stored = localStorage.getItem(key);
            } catch (error) {
                stored = null;
            }

            const theme = ['dark', 'light'].includes(stored)
                ? stored
                : (window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

            root.dataset.adminTheme = theme;
            root.classList.toggle('dark', theme === 'dark');
            root.style.colorScheme = theme;
        })();
    </script>
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
                    ['label' => __('menu.items.plans'), 'route' => 'admin.plans.index', 'active' => ['admin.plans.*'], 'icon' => 'sparkles', 'permissions' => ['plans.list']],
                    ['label' => __('menu.items.billing'), 'route' => 'admin.billing.index', 'active' => ['admin.billing.*'], 'icon' => 'invoice', 'permissions' => ['billing.list']],
                    ['label' => __('menu.items.payment_gateways'), 'route' => 'admin.system.payment-gateways.index', 'active' => ['admin.system.payment-gateways.*'], 'icon' => 'credit-card', 'permissions' => ['payment_gateways.list']],
                ],
            ],
            [
                'label' => __('menu.groups.sms'),
                'icon' => 'message',
                'items' => [
                    ['label' => __('menu.items.my_sms_gateways'), 'route' => 'admin.sms-gateways.index', 'active' => ['admin.sms-gateways.*'], 'icon' => 'message', 'planRestricted' => true],
                    ['label' => __('menu.items.system_sms_gateways'), 'route' => 'admin.system.sms-gateways.index', 'active' => ['admin.system.sms-gateways.*'], 'icon' => 'message', 'permissions' => ['sms_gateways.list']],
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
                    ['label' => __('menu.items.system_settings'), 'route' => 'admin.system.settings.edit', 'active' => ['admin.system.settings.*'], 'icon' => 'settings', 'permissions' => ['settings.manage']],
                ],
            ],
        ];

        $canSeeItem = function (array $item) use ($user, $tenant): bool {
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
                <div class="admin-sidebar-logo flex h-[73px] shrink-0 items-center justify-center px-5">
                    <a href="{{ route('admin.dashboard') }}" class="admin-brand-logo" aria-label="{{ __('dashboard.product_name') }}">
                        <img src="/theme/admin-logo-light.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-light">
                        <img src="/theme/admin-logo-dark.svg" alt="{{ __('dashboard.product_name') }}" class="admin-logo admin-logo-dark">
                    </a>
                </div>

                <nav data-admin-menu-scroll class="admin-menu-scroll min-h-0 flex-1 space-y-1 overflow-y-auto p-3 text-sm font-bold">
                    @foreach($menuGroups as $group)
                        @php
                            $visibleItems = $visibleItemsForGroup($group);
                        @endphp
                        @continue($visibleItems->isEmpty())

                        @if($visibleItems->count() === 1)
                            @php
                                $item = $visibleItems->first();
                                $locked = $itemIsLocked($item);
                                $active = $itemIsActive($item);
                            @endphp

                            @if($locked)
                                <span data-menu-item="{{ $item['route'] }}" class="{{ $menuItemClass($active, true) }}" aria-disabled="true" title="{{ __('permissions.plan_required') }}">
                                    <span class="admin-menu-label">
                                        <x-admin.icon :name="$item['icon']" />
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                    <x-admin.icon name="lock" class="admin-menu-lock h-4 w-4" />
                                </span>
                            @else
                                <a data-menu-item="{{ $item['route'] }}" href="{{ route($item['route']) }}" class="{{ $menuItemClass($active) }}" @if($active) aria-current="page" @endif>
                                    <span class="admin-menu-label">
                                        <x-admin.icon :name="$item['icon']" />
                                        <span>{{ $item['label'] }}</span>
                                    </span>
                                </a>
                            @endif
                        @else
                            <details data-menu-group="{{ $group['label'] }}" class="admin-menu-details" @if($groupIsActive($group)) open @endif>
                                <summary class="{{ $menuItemClass(false) }} admin-menu-summary">
                                    <span class="admin-menu-label">
                                        <x-admin.icon :name="$group['icon']" />
                                        <span>{{ $group['label'] }}</span>
                                    </span>
                                    <x-admin.icon name="chevron-down" class="admin-menu-caret h-4 w-4" />
                                </summary>
                                <div class="admin-submenu">
                                    @foreach($visibleItems as $item)
                                        @php
                                            $locked = $itemIsLocked($item);
                                            $active = $itemIsActive($item);
                                        @endphp

                                        @if($locked)
                                            <span data-menu-item="{{ $item['route'] }}" class="{{ $menuItemClass($active, true) }}" aria-disabled="true" title="{{ __('permissions.plan_required') }}">
                                                <span class="admin-menu-label">
                                                    <x-admin.icon :name="$item['icon']" />
                                                    <span>{{ $item['label'] }}</span>
                                                </span>
                                                <x-admin.icon name="lock" class="admin-menu-lock h-4 w-4" />
                                            </span>
                                        @else
                                            <a data-menu-item="{{ $item['route'] }}" href="{{ route($item['route']) }}" class="{{ $menuItemClass($active) }}" @if($active) aria-current="page" @endif>
                                                <span class="admin-menu-label">
                                                    <x-admin.icon :name="$item['icon']" />
                                                    <span>{{ $item['label'] }}</span>
                                                </span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endforeach
                </nav>

            </div>
        </aside>

        <div class="admin-main-panel flex h-screen min-h-0 min-w-0 flex-col">
            <header class="admin-topbar z-20 shrink-0 backdrop-blur">
                <div class="flex min-h-[73px] flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div>
                        <p class="text-xs font-bold text-teal-600">{{ __('dashboard.management_panel') }}</p>
                        <h1 class="mt-1 text-lg font-bold">@yield('page_title', __('dashboard.dashboard'))</h1>
                    </div>
                    <div class="admin-topbar-actions flex flex-wrap items-center gap-2">
                        @if($activeSubscription)
                            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                                {{ $activeSubscription->plan?->name }} - {{ __('dashboard.plan.remaining_credit', ['count' => number_format(max(0, $activeSubscription->usage_limit - $activeSubscription->usage_used))]) }}
                            </span>
                        @elseif(($planAccess['status']->value ?? null) !== 'exempt')
                            <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('dashboard.plan.'.($planAccess['status']->value ?? 'inactive')) }}</span>
                        @endif

                        <details class="admin-user-menu" data-admin-user-menu>
                            <summary class="admin-user-menu-trigger" aria-label="{{ __('dashboard.user_menu.open') }}" title="{{ __('dashboard.user_menu.open') }}">
                                <x-admin.icon name="more-horizontal" class="h-5 w-5" />
                            </summary>
                            <div class="admin-user-menu-panel">
                                <div class="admin-user-menu-header">
                                    <span class="admin-user-avatar">
                                        <x-admin.icon name="users" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold">{{ $user?->name ?? __('dashboard.user_menu.account') }}</p>
                                        <p class="mt-1 truncate text-xs">{{ $user?->role ? __('system.roles.'.$user->role) : __('dashboard.user_menu.account') }}</p>
                                    </div>
                                </div>

                                <button type="button" class="admin-user-menu-item" data-theme-toggle data-light-label="{{ __('dashboard.theme.light') }}" data-dark-label="{{ __('dashboard.theme.dark') }}" aria-label="{{ __('dashboard.theme.toggle') }}" title="{{ __('dashboard.theme.toggle') }}">
                                    <span class="admin-user-menu-item-label">
                                        <x-admin.icon name="moon" class="theme-icon-moon h-4 w-4" />
                                        <x-admin.icon name="sun" class="theme-icon-sun h-4 w-4" />
                                        <span>{{ __('dashboard.theme.menu_item') }}</span>
                                    </span>
                                </button>

                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="admin-user-menu-item is-danger">
                                        <span class="admin-user-menu-item-label">
                                            <x-admin.icon name="lock" class="h-4 w-4" />
                                            <span>{{ __('dashboard.logout') }}</span>
                                        </span>
                                    </button>
                                </form>
                            </div>
                        </details>

                        <nav class="flex max-w-full items-center gap-1 overflow-x-auto lg:hidden">
                            @foreach($menuGroups as $group)
                                @foreach($group['items'] as $item)
                                    @continue(! $canSeeItem($item) || $itemIsLocked($item))
                                    <a href="{{ route($item['route']) }}" class="admin-mobile-menu-link" title="{{ $item['label'] }}">
                                        <x-admin.icon :name="$item['icon']" />
                                    </a>
                                @endforeach
                            @endforeach
                        </nav>
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
                <button type="button" class="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-bold text-zinc-700 transition hover:border-teal-300 hover:text-teal-700" data-admin-confirm-cancel>
                    {{ __('messages.cancel') }}
                </button>
                <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-rose-700" data-admin-confirm-submit>
                    <x-admin.icon name="trash" class="h-4 w-4" />
                    <span>{{ __('messages.delete') }}</span>
                </button>
            </div>
        </div>
    </x-admin.modal>
</body>
</html>
