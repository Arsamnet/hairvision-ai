<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تایید ورود HairVision AI</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[linear-gradient(135deg,#f0fdfa_0%,#f8fafc_36%,#eef2ff_100%)] font-sans text-zinc-950 antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl shadow-indigo-100 ring-1 ring-zinc-200">
            <div class="mb-6">
                <p class="text-sm font-bold text-teal-600">ورود امن</p>
                <h1 class="mt-2 text-2xl font-bold">کد ورود را وارد کنید</h1>
                <p class="mt-3 text-sm leading-7 text-zinc-500">کد یکبارمصرف برای شماره {{ $phone }} آماده شد.</p>
            </div>

            @if(session('debug_sms'))
                <div class="mb-4 rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-800">{{ session('debug_sms') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.otp.verify') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="phone" value="{{ $phone }}">
                <div>
                    <label for="code" class="block text-sm font-bold text-zinc-800">کد تایید</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="۱۲۳۴۵۶" class="mt-2 h-12 w-full rounded-lg border border-zinc-200 px-4 text-center text-lg font-bold outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                </div>
                <button class="h-12 w-full rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700 focus:outline-none focus:ring-4 focus:ring-teal-100">ورود به داشبورد</button>
            </form>

            <a href="{{ route('admin.login') }}" class="mt-5 block text-center text-sm font-bold text-zinc-500 hover:text-teal-700">تغییر شماره موبایل</a>
        </section>
    </main>
</body>
</html>
