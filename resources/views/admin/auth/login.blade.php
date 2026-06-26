<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود به پنل HairVision AI</title>
    <meta name="robots" content="noindex,nofollow">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <?php $logoHref = app('Illuminate\Foundation\Vite')->asset('resources/img/logo.svg'); ?>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-950 antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <section class="admin-panel w-full max-w-md shadow-xl shadow-slate-200/70">
            <div class="mb-6">
                <img src="<?php echo e($logoHref); ?>" alt="HairVision AI" class="h-10 w-auto">
                <h1 class="mt-4 text-2xl font-black text-slate-950">ورود به پنل مدیریت</h1>
                <p class="mt-3 text-sm leading-7 text-slate-500">برای ورود، شماره موبایل ثبت‌شده در حساب کلینیک را وارد کنید.</p>
            </div>

            @if($errors->any())
                <div class="admin-filter-panel mb-4 border-rose-200 bg-rose-50 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.otp.request') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="phone" class="block text-sm font-bold text-slate-800">شماره موبایل</label>
                    <input id="phone" name="phone" value="{{ old('phone') }}" inputmode="numeric" autocomplete="tel" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="admin-input mt-2 h-12 text-right">
                </div>
                <button class="admin-button admin-button-primary h-12 w-full">دریافت کد ورود</button>
            </form>

            <a href="{{ url('/') }}" class="mt-5 block text-center text-sm font-bold text-slate-500 hover:text-blue-700">بازگشت به سایت</a>
        </section>
    </main>
</body>
</html>
