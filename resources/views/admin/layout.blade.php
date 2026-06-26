<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل مدیریت HairVision AI')</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $faviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png'); ?>
    <?php $logoHref = app('Illuminate\Foundation\Vite')->asset('resources/img/logo.svg'); ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <?php
        $adminMenu = [
            ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'label' => 'داشبورد', 'icon' => 'M3 13h8V3H3v10Zm10 8h8V11h-8v10ZM3 21h8v-6H3v6Zm10-12h8V3h-8v6Z'],
            ['route' => 'admin.leads.index', 'active' => 'admin.leads.*', 'label' => 'لیدها', 'icon' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75'],
            ['route' => 'admin.users.index', 'active' => 'admin.users.*', 'label' => 'کاربران', 'icon' => 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'],
            ['route' => 'admin.sms.providers.index', 'active' => 'admin.sms.*', 'label' => 'پیامک', 'icon' => 'M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8Z'],
            ['route' => 'admin.reports', 'active' => 'admin.reports', 'label' => 'گزارش‌ها', 'icon' => 'M3 3v18h18M8 17V9m5 8V5m5 12v-6'],
            ['route' => 'admin.settings', 'active' => 'admin.settings', 'label' => 'تنظیمات', 'icon' => 'M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5ZM19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 1.55V21a2 2 0 1 1-4 0v-.08A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 1 1 0-4h.08A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 1 1 4 0v.08A1.7 1.7 0 0 0 15 4.6a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9c.2.6.78 1 1.55 1H21a2 2 0 1 1 0 4h-.08A1.7 1.7 0 0 0 19.4 15Z'],
        ];
        $currentAdminTenant = $tenant ?? null;
    ?>

    <nav class="fixed top-0 z-50 w-full border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="px-4 py-3 lg:px-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button type="button" data-drawer-target="admin-sidebar" data-drawer-toggle="admin-sidebar" data-drawer-placement="right" aria-controls="admin-sidebar" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 text-sm text-slate-500 hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-blue-100 lg:hidden">
                        <span class="sr-only">باز کردن منو</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 5A.75.75 0 0 1 2.75 9h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 9.75Zm0 5A.75.75 0 0 1 2.75 14h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 14.75Z" />
                        </svg>
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 rounded-lg px-1 py-1 hover:bg-slate-50">
                        <img src="<?php echo e($logoHref); ?>" alt="HairVision AI" class="h-9 w-auto">
                    </a>
                </div>
                <div class="hidden min-w-0 flex-1 px-6 lg:block">
                    <div class="relative max-w-md">
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m21 21-4.35-4.35M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15Z" />
                            </svg>
                        </span>
                        <input type="search" aria-label="جستجوی سریع" placeholder="جستجوی سریع در پنل" class="admin-input h-10 pr-9">
                    </div>
                </div>
                <div class="flex min-w-0 items-center gap-3 text-left">
                    <div class="hidden text-left sm:block">
                        <p class="text-xs font-bold text-slate-500">{{ auth()->user()?->name ?? 'کاربر پنل' }}</p>
                        <h1 class="mt-0.5 truncate text-sm font-bold text-slate-950">@yield('page_title', 'داشبورد')</h1>
                    </div>
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50 text-sm font-black text-blue-700 ring-1 ring-blue-100">
                        {{ mb_substr(auth()->user()?->name ?? 'H', 0, 1) }}
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <aside id="admin-sidebar" class="fixed right-0 top-0 z-40 h-screen w-64 translate-x-full border-l border-slate-200 bg-white pt-16 transition-transform lg:translate-x-0" aria-label="Sidebar">
        <div class="flex h-full flex-col overflow-y-auto bg-white px-3 pb-4">
            <div class="mb-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-bold text-slate-500">کلینیک فعال</p>
                <p class="mt-1 truncate text-sm font-black text-slate-950">{{ $currentAdminTenant?->name ?? 'نمای کل پلتفرم' }}</p>
            </div>
            <nav class="flex-1 space-y-1 py-3">
                @foreach($adminMenu as $item)
                    <a href="{{ route($item['route']) }}" class="admin-sidebar-link {{ request()->routeIs($item['active']) ? 'admin-sidebar-link-active' : '' }}">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $item['icon'] }}" />
                        </svg>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-slate-100 pt-3">
                @csrf
                <button class="admin-sidebar-link text-rose-700 hover:bg-rose-50 hover:text-rose-700">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                    <span>خروج</span>
                </button>
            </form>
        </div>
    </aside>

    <main class="p-4 pt-20 lg:mr-64 lg:p-6 lg:pt-20">
        @if(session('status'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-800 shadow-sm">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-bold text-rose-700 shadow-sm">{{ $errors->first() }}</div>
        @endif

        @yield('content')
    </main>
</body>
</html>
