<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'پنل مدیریت HairVision AI')</title>
    <meta name="robots" content="noindex,nofollow">
    <?php $faviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png'); ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f6f8fb] font-sans text-zinc-950 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
        <aside class="hidden border-l border-zinc-200 bg-white lg:block">
            <div class="flex h-full min-h-screen flex-col">
                <div class="border-b border-zinc-100 px-5 py-5">
                    <a href="{{ route('admin.dashboard') }}" class="block">
                        <span class="block text-xs font-bold text-teal-600">HairVision AI</span>
                        <span class="mt-1 block text-lg font-bold">پنل کلینیک</span>
                    </a>
                    <p class="mt-2 text-xs leading-6 text-zinc-500">{{ $tenant?->name ?? 'همه کلینیک‌ها' }}</p>
                </div>
                <nav class="flex-1 space-y-1 p-3 text-sm font-bold">
                    <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('admin.dashboard') ? 'bg-zinc-950 text-white' : 'text-zinc-600 hover:bg-zinc-100' }}">داشبورد</a>
                    <a href="{{ route('admin.leads.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('admin.leads.*') ? 'bg-zinc-950 text-white' : 'text-zinc-600 hover:bg-zinc-100' }}">لیدها</a>
                    <a href="{{ route('admin.users.index') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('admin.users.*') ? 'bg-zinc-950 text-white' : 'text-zinc-600 hover:bg-zinc-100' }}">کاربران</a>
                    <a href="{{ route('admin.reports') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('admin.reports') ? 'bg-zinc-950 text-white' : 'text-zinc-600 hover:bg-zinc-100' }}">گزارش‌ها</a>
                    <a href="{{ route('admin.settings') }}" class="block rounded-lg px-3 py-2.5 {{ request()->routeIs('admin.settings') ? 'bg-zinc-950 text-white' : 'text-zinc-600 hover:bg-zinc-100' }}">تنظیمات</a>
                </nav>
                <form method="POST" action="{{ route('admin.logout') }}" class="border-t border-zinc-100 p-3">
                    @csrf
                    <button class="w-full rounded-lg px-3 py-2.5 text-right text-sm font-bold text-rose-700 hover:bg-rose-50">خروج</button>
                </form>
            </div>
        </aside>

        <div class="min-w-0">
            <header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/90 backdrop-blur">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div>
                        <p class="text-xs font-bold text-teal-600">پنل مدیریت</p>
                        <h1 class="mt-1 text-lg font-bold">@yield('page_title', 'داشبورد')</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @isset($availableTenants)
                            <form method="POST" action="{{ route('admin.tenant.switch') }}">
                                @csrf
                                <select name="tenant_id" onchange="this.form.submit()" class="h-10 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-bold text-zinc-700 outline-none focus:border-teal-500">
                                    @if(auth()->user()?->isSuperAdmin())
                                        <option value="all" @selected($tenant === null)>همه کلینیک‌ها</option>
                                    @endif
                                    @foreach($availableTenants as $optionTenant)
                                        <option value="{{ $optionTenant->id }}" @selected($tenant?->id === $optionTenant->id)>{{ $optionTenant->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @endisset
                        <nav class="flex items-center gap-1 lg:hidden">
                            <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-bold text-zinc-700 hover:bg-zinc-100">داشبورد</a>
                            <a href="{{ route('admin.leads.index') }}" class="rounded-lg px-3 py-2 text-sm font-bold text-zinc-700 hover:bg-zinc-100">لیدها</a>
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
