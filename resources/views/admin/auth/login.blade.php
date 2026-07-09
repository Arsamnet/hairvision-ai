<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود به پنل HairVision</title>
    <meta name="robots" content="noindex,nofollow">
    @include('admin.partials.theme-script')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-auth-shell min-h-screen font-sans antialiased">
    <main class="grid min-h-screen place-items-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="mb-5 flex justify-center">
                <img src="{{ asset('theme/admin-logo-light.svg') }}" alt="HairVision" class="h-16 w-auto object-contain">
            </div>
        <section class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl shadow-indigo-100 ring-1 ring-zinc-200">
            <div class="bg-linear-to-l from-teal-50 via-indigo-50 to-white p-6">
                <p class="text-sm font-bold text-teal-600">HairVision</p>
                <h1 class="mt-1 text-2xl font-bold text-zinc-950">ورود امن به پنل</h1>
                <p class="mt-4 text-sm leading-7 text-zinc-500">شماره موبایل ثبت‌شده در حساب کلینیک را وارد کنید تا کد یکبارمصرف ورود ارسال شود.</p>
            </div>

            <div class="p-6">
                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('admin.otp.request') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="phone" class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                        <input id="phone" name="phone" value="{{ old('phone') }}" inputmode="numeric" autocomplete="tel" maxlength="11" pattern="[0-9۰-۹٠-٩]*" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="mt-2 h-12 w-full rounded-lg border border-zinc-200 px-4 text-right outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-100">
                    </div>
                    <x-admin.button type="submit" size="lg" block class="focus:ring-4 focus:ring-teal-100">دریافت کد ورود</x-admin.button>
                </form>

                <a href="{{ url('/') }}" class="mt-5 block text-center text-sm font-bold text-zinc-500 hover:text-teal-700">بازگشت به سایت</a>
            </div>
        </section>
        </div>
    </main>
</body>
</html>
