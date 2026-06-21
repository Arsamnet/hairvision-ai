<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود به پنل HairVision AI</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[linear-gradient(135deg,#f0fdfa_0%,#f8fafc_36%,#eef2ff_100%)] font-sans text-zinc-950 antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl shadow-indigo-100 ring-1 ring-zinc-200">
            <div class="mb-6">
                <p class="text-sm font-bold text-teal-600">HairVision AI</p>
                <h1 class="mt-2 text-2xl font-bold">ورود به پنل مدیریت</h1>
                <p class="mt-3 text-sm leading-7 text-zinc-500">برای ورود، شماره موبایل ثبت‌شده در حساب کلینیک را وارد کنید.</p>
            </div>

            @if($errors->any())
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.otp.request') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="phone" class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="mt-2 h-12 w-full rounded-lg border border-zinc-200 px-4 text-right outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                </div>
                <button class="h-12 w-full rounded-lg bg-zinc-950 px-4 text-sm font-bold text-white transition hover:bg-teal-700 focus:outline-none focus:ring-4 focus:ring-teal-100">دریافت کد ورود</button>
            </form>

            <a href="{{ url('/') }}" class="mt-5 block text-center text-sm font-bold text-zinc-500 hover:text-teal-700">بازگشت به سایت</a>
        </section>
    </main>
</body>
</html>
