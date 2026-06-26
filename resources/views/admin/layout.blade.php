<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('dashboard.admin_title'))</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $faviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png'); ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f8fb] font-sans text-zinc-950 antialiased">
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
                'items' => [
                    ['label' => __('menu.items.dashboard'), 'route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'icon' => 'layout-dashboard'],
                ],
            ],
            [
                'label' => __('menu.groups.crm'),
                'items' => [
                    ['label' => __('menu.items.leads'), 'route' => 'admin.leads.index', 'active' => ['admin.leads.*'], 'icon' => 'contact', 'permissions' => ['leads.list'], 'planRestricted' => true],
                ],
            ],
            [
                'label' => __('menu.groups.users_access'),
                'items' => [
                    ['label' => __('menu.items.users'), 'route' => 'admin.users.index', 'active' => ['admin.users.*'], 'icon' => 'users', 'permissions' => ['users.list'], 'planRestricted' => true],
                ],
            ],
            [
                'label' => __('menu.groups.plans_payments'),
                'items' => [
                    ['label' => __('menu.items.plans'), 'route' => 'admin.plans.index', 'active' => ['admin.plans.*'], 'icon' => 'sparkles', 'permissions' => ['plans.list']],
                    ['label' => __('menu.items.billing'), 'route' => 'admin.billing.index', 'active' => ['admin.billing.*'], 'icon' => 'credit-card', 'permissions' => ['billing.list']],
                ],
            ],
            [
                'label' => __('menu.groups.sms'),
                'items' => [
                    ['label' => __('menu.items.my_sms_gateways'), 'route' => 'admin.sms-gateways.index', 'active' => ['admin.sms-gateways.*'], 'icon' => 'message', 'planRestricted' => true],
                ],
            ],
            [
                'label' => __('menu.groups.reports'),
                'items' => [
                    ['label' => __('menu.items.user_reports'), 'route' => 'admin.reports', 'active' => ['admin.reports'], 'icon' => 'chart', 'permissions' => ['reports.user.view'], 'planRestricted' => true],
                ],
            ],
            [
                'label' => __('menu.groups.settings'),
                'items' => [
                    ['label' => __('menu.items.settings'), 'route' => 'admin.settings', 'active' => ['admin.settings'], 'icon' => 'settings', 'permissions' => ['settings.view']],
                ],
            ],
        ];

        $systemGroups = [
            [
                'label' => __('menu.groups.system_management'),
                'items' => [
                    ['label' => __('menu.items.system_dashboard'), 'route' => 'admin.system.dashboard', 'active' => ['admin.system.dashboard'], 'icon' => 'shield', 'permissions' => ['system.dashboard']],
                ],
            ],
            [
                'label' => __('menu.groups.users_access'),
                'items' => [
                    ['label' => __('menu.items.access'), 'route' => 'admin.system.access.index', 'active' => ['admin.system.access.*'], 'icon' => 'users', 'permissions' => ['users.manage']],
                    ['label' => __('menu.items.roles'), 'route' => 'admin.system.roles.index', 'active' => ['admin.system.roles.*'], 'icon' => 'shield', 'permissions' => ['roles.list']],
                    ['label' => __('menu.items.permissions'), 'route' => 'admin.system.permissions.index', 'active' => ['admin.system.permissions.*'], 'icon' => 'shield', 'permissions' => ['permissions.list']],
                ],
            ],
            [
                'label' => __('menu.groups.sms'),
                'items' => [
                    ['label' => __('menu.items.system_sms_gateways'), 'route' => 'admin.system.sms-gateways.index', 'active' => ['admin.system.sms-gateways.*'], 'icon' => 'message', 'permissions' => ['sms_gateways.list']],
                ],
            ],
            [
                'label' => __('menu.groups.plans_payments'),
                'items' => [
                    ['label' => __('menu.items.payment_gateways'), 'route' => 'admin.system.payment-gateways.index', 'active' => ['admin.system.payment-gateways.*'], 'icon' => 'credit-card', 'permissions' => ['payment_gateways.list']],
                ],
            ],
            [
                'label' => __('menu.groups.reports'),
                'items' => [
                    ['label' => __('menu.items.management_reports'), 'route' => 'admin.system.reports.index', 'active' => ['admin.system.reports.*'], 'icon' => 'chart', 'permissions' => ['reports.management.view']],
                ],
            ],
            [
                'label' => __('menu.groups.settings'),
                'items' => [
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

        $groupHasVisibleItems = fn (array $group): bool => collect($group['items'])->contains(fn (array $item): bool => $canSeeItem($item));
        $groupIsActive = fn (array $group): bool => collect($group['items'])->contains(fn (array $item): bool => $itemIsActive($item));
    @endphp

    <div class="min-h-screen lg:grid lg:grid-cols-[18.5rem_1fr]">
        <aside class="hidden border-l border-zinc-200/80 bg-white lg:block">
            <div class="flex h-full min-h-screen flex-col">
                <div class="border-b border-zinc-100 px-5 py-5">
                    <a href="{{ route('admin.dashboard') }}" class="block">
                        <span class="block text-xs font-bold text-teal-600">{{ __('dashboard.product_name') }}</span>
                        <span class="mt-1 block text-lg font-bold">{{ __('dashboard.clinic_panel') }}</span>
                    </a>
                    <p class="mt-2 text-xs leading-6 text-zinc-500">{{ $tenant?->name ?? __('dashboard.all_clinics') }}</p>
                    @if($planAccess['status'] ?? null)
                        <div class="mt-4 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-xs font-bold text-zinc-700">
                            <span class="text-zinc-500">{{ __('dashboard.plan.status_title') }}:</span>
                            <span>{{ __('dashboard.plan.'.($planAccess['status']->value ?? 'active')) }}</span>
                        </div>
                    @endif
                </div>

                <nav class="flex-1 space-y-5 overflow-y-auto p-3 text-sm font-bold">
                    @foreach($menuGroups as $group)
                        @if($groupHasVisibleItems($group))
                            <section>
                                <p class="mb-2 flex items-center gap-2 px-3 text-xs font-bold {{ $groupIsActive($group) ? 'text-teal-700' : 'text-zinc-400' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $groupIsActive($group) ? 'bg-teal-600' : 'bg-zinc-300' }}"></span>
                                    {{ $group['label'] }}
                                </p>
                                <div class="space-y-1">
                                    @foreach($group['items'] as $item)
                                        @continue(! $canSeeItem($item))
                                        @php
                                            $locked = $itemIsLocked($item);
                                            $active = $itemIsActive($item);
                                            $classes = $active
                                                ? 'bg-zinc-950 text-white shadow-sm'
                                                : ($locked
                                                    ? 'cursor-not-allowed bg-zinc-50 text-zinc-400 ring-1 ring-zinc-100'
                                                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950');
                                        @endphp

                                        @if($locked)
                                            <span class="flex items-center justify-between rounded-lg px-3 py-2.5 {{ $classes }}" aria-disabled="true" title="{{ __('permissions.plan_required') }}">
                                                <span class="flex items-center gap-2">
                                                    <x-admin.icon :name="$item['icon']" />
                                                    <span>{{ $item['label'] }}</span>
                                                </span>
                                                <x-admin.icon name="lock" class="h-4 w-4" />
                                            </span>
                                        @else
                                            <a href="{{ route($item['route']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 transition {{ $classes }}" @if($active) aria-current="page" @endif>
                                                <x-admin.icon :name="$item['icon']" />
                                                <span>{{ $item['label'] }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    @endforeach

                    @if(collect($systemGroups)->contains(fn (array $group): bool => $groupHasVisibleItems($group)))
                        <div class="border-t border-zinc-100 pt-5">
                            @foreach($systemGroups as $group)
                                @if($groupHasVisibleItems($group))
                                    <section class="mb-5 last:mb-0">
                                        <p class="mb-2 flex items-center gap-2 px-3 text-xs font-bold {{ $groupIsActive($group) ? 'text-teal-700' : 'text-zinc-400' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $groupIsActive($group) ? 'bg-teal-600' : 'bg-zinc-300' }}"></span>
                                            {{ $group['label'] }}
                                        </p>
                                        <div class="space-y-1">
                                            @foreach($group['items'] as $item)
                                                @continue(! $canSeeItem($item))
                                                @php
                                                    $active = $itemIsActive($item);
                                                    $classes = $active ? 'bg-zinc-950 text-white shadow-sm' : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950';
                                                @endphp
                                                <a href="{{ route($item['route']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2.5 transition {{ $classes }}" @if($active) aria-current="page" @endif>
                                                    <x-admin.icon :name="$item['icon']" />
                                                    <span>{{ $item['label'] }}</span>
                                                </a>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </nav>

                <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-zinc-100 p-3">
                    @csrf
                    <button class="flex w-full items-center gap-2 rounded-lg px-3 py-2.5 text-right text-sm font-bold text-rose-700 transition hover:bg-rose-50">
                        <x-admin.icon name="lock" class="h-4 w-4" />
                        <span>{{ __('dashboard.logout') }}</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/90 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div>
                        <p class="text-xs font-bold text-teal-600">{{ __('dashboard.management_panel') }}</p>
                        <h1 class="mt-1 text-lg font-bold">@yield('page_title', __('dashboard.dashboard'))</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if($activeSubscription)
                            <span class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800">
                                {{ $activeSubscription->plan?->name }} - {{ __('dashboard.plan.remaining_credit', ['count' => number_format(max(0, $activeSubscription->usage_limit - $activeSubscription->usage_used))]) }}
                            </span>
                        @elseif(($planAccess['status']->value ?? null) !== 'exempt')
                            <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">{{ __('dashboard.plan.'.($planAccess['status']->value ?? 'inactive')) }}</span>
                        @endif

                        @isset($availableTenants)
                            <form method="POST" action="{{ route('admin.tenant.switch') }}">
                                @csrf
                                <select name="tenant_id" onchange="this.form.submit()" class="h-10 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-bold text-zinc-700 outline-none focus:border-teal-500">
                                    @if($user?->isSuperAdmin())
                                        <option value="all" @selected($tenant === null)>{{ __('dashboard.all_clinics') }}</option>
                                    @endif
                                    @foreach($availableTenants as $optionTenant)
                                        <option value="{{ $optionTenant->id }}" @selected($tenant?->id === $optionTenant->id)>{{ $optionTenant->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endisset

                        <nav class="flex max-w-full items-center gap-1 overflow-x-auto lg:hidden">
                            @foreach($menuGroups as $group)
                                @foreach($group['items'] as $item)
                                    @continue(! $canSeeItem($item) || $itemIsLocked($item))
                                    <a href="{{ route($item['route']) }}" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-zinc-700 hover:bg-zinc-100" title="{{ $item['label'] }}">
                                        <x-admin.icon :name="$item['icon']" />
                                    </a>
                                @endforeach
                            @endforeach
                        </nav>
                    </div>
                </div>
            </header>

            <main class="px-4 py-5 sm:px-6 lg:px-8">
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
</body>
</html>
