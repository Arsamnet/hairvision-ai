{{-- @since 2.1.0 Uses the code-owned HairVision copyright identity strip. --}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HairVision | نرم‌افزار مدیریت لید و پیش‌نمایش کاشت مو</title>
    <meta name="description" content="HairVision نرم‌افزار چندکلینیکی مدیریت لید، ورود امن با OTP و پیش‌نمایش هوشمند مسیر کاشت مو برای کلینیک‌های تخصصی است.">
    <meta property="og:title" content="HairVision">
    <meta property="og:description" content="داشبورد چندکلینیکی، مدیریت لید و تجربه امن پیش‌نمایش کاشت مو برای کلینیک‌ها.">
    <meta property="og:type" content="website">
    <?php $faviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png'); ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white font-sans text-zinc-950 antialiased">
    <header class="absolute inset-x-0 top-0 z-20">
        <div class="mx-auto flex w-full max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="text-sm font-bold text-white">
                <img src="/theme/admin-logo-dark.svg" alt="HairVision Logo" class="h-11">
            </a>
            <nav class="flex items-center gap-2 text-sm font-bold">
                <a href="#features" class="rounded-lg px-3 py-2 text-white/80 hover:bg-white/10 hover:text-white">قابلیت‌ها</a>
                <a href="{{ url('/admin') }}" class="rounded-lg bg-white px-4 py-2 text-zinc-950 hover:bg-teal-50">ورود به پنل</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="relative isolate min-h-[88svh] overflow-hidden">
            <img src="/theme/hairvision-ai-hero.png" alt="نمای داشبورد HairVision" class="absolute inset-0 -z-20 h-full w-full object-cover">
            <div class="absolute inset-0 -z-10 bg-[linear-gradient(45deg,rgba(9,9,11,.28)_0%,rgba(15,23,42,.76)_70%,rgba(9,9,11,.90)_100%)]"></div>
            <div class="mx-auto flex min-h-[88svh] w-full max-w-7xl items-center px-4 pb-14 pt-24 sm:px-6 lg:px-8">
                <div class="max-w-2xl text-white">
                    <p class="text-sm font-bold text-teal-200">نرم‌افزار کلینیک‌های کاشت مو</p>
                    <h1 class="mt-4 text-4xl font-bold leading-tight sm:text-5xl">HairVision</h1>
                    <p class="mt-5 max-w-xl text-base leading-8 text-zinc-100">داشبورد چندکلینیکی برای جذب لید، ورود امن با کد یکبارمصرف، مدیریت تیم درمان و ارائه تجربه پیش‌نمایش هوشمند کاشت مو.</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <a href="{{ url('/admin') }}" class="rounded-lg bg-white px-5 py-3 text-sm font-bold text-zinc-950 hover:bg-teal-50">ورود به پنل</a>
                        <a href="#features" class="rounded-lg bg-white/10 px-5 py-3 text-sm font-bold text-white ring-1 ring-white/20 hover:bg-white/15">مشاهده قابلیت‌ها</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="bg-white px-4 py-16 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="max-w-3xl">
                    <h2 class="text-2xl font-bold">قابلیت‌های اصلی HairVision</h2>
                    <p class="mt-3 text-sm leading-7 text-zinc-600">این پلتفرم برای کلینیک‌هایی طراحی شده که می‌خواهند تجربه دیجیتال، مدیریت لید و پیگیری مشاوره را در یک مسیر امن و سریع نگه دارند.</p>
                </div>
                <div class="mt-8 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach([
                        ['title' => 'پیش‌نمایش هوشمند', 'body' => 'نمایش مرحله‌ای مسیر کاشت مو از یک روز بعد از کاشت تا نتیجه یک‌ساله برای شروع گفت‌وگوی دقیق‌تر.'],
                        ['title' => 'مدیریت لید کلینیک', 'body' => 'ثبت، جستجو، فیلتر و پیگیری لیدهای هر کلینیک با جداسازی کامل داده‌ها.'],
                        ['title' => 'ورود امن OTP', 'body' => 'احراز هویت موبایلی با کد هش‌شده، زمان انقضا، محدودیت تلاش و نرخ درخواست.'],
                        ['title' => 'نقش‌های دسترسی', 'body' => 'مدیر سیستم، مدیر کلینیک، ویرایشگر و مشاهده‌گر با کنترل دسترسی سمت سرور.'],
                        ['title' => 'داشبورد RTL', 'body' => 'رابط فارسی، واکنش‌گرا و مناسب استفاده روزانه تیم پذیرش و مشاوره.'],
                        ['title' => 'گزارش و تحلیل', 'body' => 'نمای وضعیت لیدها، حجم لیدها و عملکرد کلینیک‌ها برای تصمیم‌گیری سریع‌تر.'],
                    ] as $feature)
                        <article class="rounded-lg border border-zinc-200 bg-zinc-50 p-5">
                            <h3 class="font-bold">{{ $feature['title'] }}</h3>
                            <p class="mt-3 text-sm leading-7 text-zinc-600">{{ $feature['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="bg-[#f6f8fb] px-4 py-16 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-8 lg:grid-cols-2">
                <div>
                    <h2 class="text-2xl font-bold">فرآیند کار</h2>
                    <div class="mt-6 space-y-4">
                        @foreach(['کلینیک از دامنه اختصاصی خود لید دریافت می‌کند.', 'کاربر پنل با موبایل و OTP وارد می‌شود.', 'تیم کلینیک لیدها را مرحله‌بندی، ویرایش و پیگیری می‌کند.', 'گزارش‌ها به بهبود نرخ تبدیل و زمان پاسخ کمک می‌کنند.'] as $step)
                            <p class="rounded-lg bg-white px-4 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">{{ $step }}</p>
                        @endforeach
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-bold">امنیت و معماری</h2>
                    <p class="mt-4 text-sm leading-8 text-zinc-600">HairVision با Laravel، جداسازی Tenant از روی Host، ذخیره خصوصی فایل‌ها، OTP هش‌شده، سیاست‌های دسترسی و معماری API-first ساخته شده است. داده هر کلینیک در کوئری‌های سمت سرور scoped می‌شود و مسیر عمومی بازدیدکننده از ورود کاربران پنل جدا می‌ماند.</p>
                    <a href="{{ url('/admin') }}" class="mt-6 inline-block rounded-lg bg-zinc-950 px-5 py-3 text-sm font-bold text-white hover:bg-teal-700">شروع مدیریت کلینیک</a>
                </div>
            </div>
        </section>
    </main>
    @include('public.partials.copyright')
</body>
</html>
