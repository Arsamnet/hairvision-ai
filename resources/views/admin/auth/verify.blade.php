<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تایید ورود HairVision AI</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-950 antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <section class="admin-panel w-full max-w-md shadow-xl shadow-slate-200/70">
            <div class="mb-6">
                <p class="text-sm font-bold text-blue-700">ورود امن</p>
                <h1 class="mt-2 text-2xl font-black text-slate-950">کد ورود را وارد کنید</h1>
                <p class="mt-3 text-sm leading-7 text-slate-500">کد یکبارمصرف برای شماره {{ $phone }} آماده شد.</p>
            </div>

            @if(session('debug_sms'))
                <div class="admin-filter-panel mb-4 border-cyan-200 bg-cyan-50 text-sm font-bold text-cyan-800">{{ session('debug_sms') }}</div>
            @endif

            @if($errors->any())
                <div class="admin-filter-panel mb-4 border-rose-200 bg-rose-50 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.otp.verify') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <div>
                    <label for="code" class="block text-sm font-bold text-slate-800">کد تایید</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="۱۲۳۴۵۶" class="admin-input mt-2 h-12 text-center text-lg font-bold">
                </div>
                <button class="admin-button admin-button-primary h-12 w-full">ورود به داشبورد</button>
            </form>

            <a href="{{ route('admin.login') }}" class="mt-5 block text-center text-sm font-bold text-slate-500 hover:text-blue-700">تغییر شماره موبایل</a>
        </section>
    </main>
</body>
</html>
