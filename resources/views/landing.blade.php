<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <?php $theme = $theme ?? null; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($tenant->name ?? 'پیش‌نمایی هوشمند'); ?></title>
    <?php
        $defaultFaviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png');
        $faviconHref = $theme?->faviconHref($defaultFaviconHref) ?? $defaultFaviconHref;
        $primaryColor = (string) ($theme?->primary_color ?: '#0f766e');
        $primaryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) ? $primaryColor : '#0f766e';
    ?>
    <link rel="icon" href="<?php echo e($faviconHref); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <style>
        @keyframes hv-enter {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes hv-shift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes hv-soft-pulse {
            0%, 100% { opacity: .55; transform: scale(.98); }
            50% { opacity: 1; transform: scale(1); }
        }

        @keyframes hv-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        @keyframes hv-aurora {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes hv-skeleton {
            0% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .hv-enter { animation: hv-enter .25s ease-out both; }
        .hv-ai-cast {
            background: linear-gradient(120deg, rgba(20,184,166,.22), rgba(99,102,241,.24), rgba(168,85,247,.2), rgba(14,165,233,.2), rgba(45,212,191,.2));
            background-size: 320% 320%;
            animation: hv-shift 2.6s ease-in-out infinite;
        }
        .hv-live-dot { animation: hv-soft-pulse 1.1s ease-in-out infinite; }
        .hv-hero-panel {
            background:
                radial-gradient(circle at 12% 18%, rgba(45,212,191,.24), transparent 26%),
                radial-gradient(circle at 90% 8%, rgba(168,85,247,.24), transparent 28%),
                linear-gradient(135deg, rgba(9,9,11,.98), rgba(30,27,75,.96) 48%, rgba(6,78,59,.93)),
                linear-gradient(45deg, rgba(20,184,166,.26), rgba(99,102,241,.16));
        }
        .hv-app-shell {
            background:
                linear-gradient(180deg, rgba(255,255,255,.95), rgba(248,250,252,.9)),
                linear-gradient(135deg, rgba(255,255,255,.82), rgba(199,210,254,.18));
        }
        .hv-step {
            position: relative;
            isolation: isolate;
            overflow: hidden;
        }
        .hv-step::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            opacity: 0;
            transition: opacity .25s ease;
            background: linear-gradient(135deg, rgba(20,184,166,.18), rgba(99,102,241,.18), rgba(168,85,247,.12));
        }
        .hv-step:hover::before { opacity: 1; }
        .hv-step-active {
            color: #fff;
            background: linear-gradient(135deg, #0f172a, #312e81 52%, #0f766e);
            box-shadow: 0 18px 48px rgba(49,46,129,.28);
        }
        .hv-step-done {
            color: #065f46;
            background: linear-gradient(135deg, #d1fae5, #ecfeff);
        }
        .hv-step-upcoming {
            color: #52525b;
            background: rgba(244,244,245,.78);
        }
        .hv-lift {
            transition: transform .22s ease, box-shadow .22s ease, background-color .22s ease;
        }
        .hv-lift:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 46px rgba(79,70,229,.16);
        }
        .hv-primary-action {
            border: 2px solid transparent;
            background:
                linear-gradient(135deg, #0f172a, #111827) padding-box,
                linear-gradient(120deg, var(--hv-primary-color, #14b8a6), #22d3ee, #6366f1, #a855f7, var(--hv-primary-color, #14b8a6)) border-box;
            background-size: 100% 100%, 260% 260%;
            animation: hv-shift 4.5s ease-in-out infinite;
            box-shadow: 0 14px 34px rgba(49,46,129,.24);
        }
        .hv-primary-action:hover {
            box-shadow: 0 18px 42px rgba(20,184,166,.24);
        }
        .hv-upload-zone::after {
            content: '';
            position: absolute;
            inset: 12px;
            border-radius: 1.25rem;
            opacity: 0;
            transition: opacity .25s ease;
            background: linear-gradient(135deg, rgba(20,184,166,.1), rgba(99,102,241,.12), rgba(168,85,247,.08));
        }
        .hv-upload-zone:hover::after { opacity: 1; }
        .hv-ai-input {
            background:
                linear-gradient(#fff, #fff) padding-box,
                linear-gradient(120deg, var(--hv-primary-color, #14b8a6), #22d3ee, #6366f1, #a855f7, var(--hv-primary-color, #14b8a6)) border-box;
            background-size: 100% 100%, 260% 260%;
            border: 1px solid transparent;
            animation: hv-shift 4.5s ease-in-out infinite;
            box-shadow: 0 10px 30px rgba(79,70,229,.08);
        }
        .hv-ai-input:focus {
            box-shadow: 0 0 0 4px rgba(45,212,191,.16), 0 16px 36px rgba(79,70,229,.12);
        }
        .hv-logo-slot {
            background: rgba(255,255,255,.1);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.16), 0 12px 30px rgba(49,46,129,.24);
        }
        .hv-logo-slot img {
            max-height: 2.75rem;
            max-width: 9rem;
        }
        .hv-result-card img {
            transition: transform .35s ease, filter .35s ease;
        }
        .hv-result-card:hover .hv-result-image {
            transform: scale(1.045);
            filter: saturate(1.08) contrast(1.03);
        }
        .hv-float { animation: hv-float 4s ease-in-out infinite; }
        .hv-modal-image {
            transition: opacity .22s ease, transform .22s ease;
        }
        .hv-modal-image.is-changing {
            opacity: 0;
            transform: scale(.985) translateY(6px);
        }
        .hv-generation-stage {
            border: 1px solid rgba(228,228,231,.95);
            background: rgba(255,255,255,.82);
            transition: border-color .2s ease, background-color .2s ease, box-shadow .2s ease;
        }
        .hv-generation-stage.is-processing {
            border-color: rgba(79,70,229,.36);
            background: linear-gradient(135deg, rgba(238,242,255,.98), rgba(236,253,245,.9));
            box-shadow: 0 12px 30px rgba(79,70,229,.12);
        }
        .hv-generation-stage.is-completed {
            border-color: rgba(16,185,129,.34);
            background: rgba(236,253,245,.92);
        }
        .hv-generation-stage.is-failed {
            border-color: rgba(244,63,94,.36);
            background: rgba(255,241,242,.92);
        }
        .hv-modal-toast {
            background: linear-gradient(135deg, rgba(15,23,42,.92), rgba(49,46,129,.9));
            box-shadow: 0 16px 44px rgba(0,0,0,.28);
        }
        .hv-thinking-bubble {
            position: relative;
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            box-shadow: 0 12px 30px rgba(79,70,229,.1);
        }
        .hv-thinking-bubble::after {
            content: '';
            position: absolute;
            inset-inline-start: 1.25rem;
            bottom: -7px;
            width: 14px;
            height: 14px;
            transform: rotate(45deg);
            background: #fff;
            border-inline-end: 1px solid rgba(226,232,240,.8);
            border-bottom: 1px solid rgba(226,232,240,.8);
        }
        .hv-thinking-dots span {
            animation: hv-soft-pulse 1.2s ease-in-out infinite;
        }
        .hv-thinking-dots span:nth-child(2) { animation-delay: .16s; }
        .hv-thinking-dots span:nth-child(3) { animation-delay: .32s; }
        .hv-skeleton {
            background: linear-gradient(110deg, #f4f4f5 8%, #e0e7ff 18%, #f4f4f5 33%);
            background-size: 220% 100%;
            animation: hv-skeleton 1.6s linear infinite;
        }
        .hv-upload-zone {
            min-height: 17rem;
            aspect-ratio: 4 / 5;
        }
        .hv-otp-brand {
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 22%, white);
            background:
                radial-gradient(circle at 12% 10%, color-mix(in srgb, var(--hv-primary-color, #0f766e) 18%, transparent), transparent 34%),
                linear-gradient(135deg, #ffffff, #f8fafc);
        }
        .hv-secondary-action {
            border: 1px solid color-mix(in srgb, var(--hv-primary-color, #0f766e) 26%, #e4e4e7);
            color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 86%, #18181b);
            background: color-mix(in srgb, var(--hv-primary-color, #0f766e) 6%, white);
        }
        .hv-secondary-action:hover:not(:disabled) {
            background: color-mix(in srgb, var(--hv-primary-color, #0f766e) 12%, white);
        }
        .hv-secondary-action:disabled {
            color: #71717a;
            background: #f4f4f5;
        }
        .hv-treatment-card {
            border: 1px solid rgba(228,228,231,.9);
            background:
                linear-gradient(135deg, rgba(255,255,255,.96), rgba(248,250,252,.9)),
                linear-gradient(135deg, color-mix(in srgb, var(--hv-primary-color, #0f766e) 10%, transparent), rgba(99,102,241,.08));
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        .hv-treatment-card:hover,
        .hv-treatment-card.is-selected {
            transform: translateY(-3px);
            border-color: color-mix(in srgb, var(--hv-primary-color, #0f766e) 44%, #a5b4fc);
            box-shadow: 0 22px 46px rgba(79,70,229,.16);
        }
        .hv-treatment-icon {
            background: linear-gradient(135deg, var(--hv-primary-color, #0f766e), #4f46e5 56%, #c026d3);
        }
        .hv-info-band {
            background:
                linear-gradient(135deg, rgba(15,23,42,.92), rgba(49,46,129,.84)),
                linear-gradient(135deg, color-mix(in srgb, var(--hv-primary-color, #0f766e) 34%, transparent), transparent);
        }
    </style>
</head>
<body class="min-h-screen bg-[#eef2ff] font-sans text-zinc-900 antialiased" style="--hv-primary-color: <?php echo e($primaryColor); ?>;">
    <div class="flex min-h-screen flex-col">
        <header class="w-full bg-white/85 shadow-sm shadow-zinc-200/60 backdrop-blur">
            <?php echo $theme?->header_html ?? '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6"><div><p class="text-sm font-bold text-emerald-700">کلینیک کاشت مو</p><h1 class="mt-1 text-xl font-bold text-zinc-950">مشاوره و پیش نمایش اختصاصی کاشت مو</h1></div></div>'; ?>

        </header>

        <main class="w-full flex-1">
            <section class="relative isolate overflow-hidden px-4 py-6 sm:px-6 lg:py-8">
                <div class="absolute inset-0 -z-10 bg-[linear-gradient(135deg,#ecfeff_0%,#f8fafc_32%,#eef2ff_68%,#faf5ff_100%)]"></div>

                <div id="landingApp" class="mx-auto grid w-full max-w-6xl gap-5 lg:grid-cols-[2fr_3fr]">
                    <aside class="hv-hero-panel rounded-3xl p-5 text-white shadow-2xl shadow-indigo-950/20 lg:p-6">
                        <div class="flex h-full flex-col justify-between gap-8">
                            <div class="space-y-6">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-emerald-200 ring-1 ring-white/10">پیش‌نمایش هوشمند</span>
                                    <?php if(! empty($theme?->back_link_url)): ?>
                                        <a href="<?php echo e($theme?->back_link_url); ?>" class="hv-lift rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-950">
                                            <?php echo e($theme?->back_link_title ?? 'بازگشت به سایت'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div class="hv-float">
                                    <p class="text-sm font-bold text-emerald-300">مشاوره تصویری اختصاصی</p>
                                    <h2 class="mt-3 text-2xl font-bold leading-9 text-white">مسیر کاشت مو یا ابرو را با تصویر خودتان ببینید.</h2>
                                    <p class="mt-4 text-sm leading-8 text-zinc-200">ابتدا نوع درمان را انتخاب می‌کنید، تصویرتان را آماده می‌کنید، شماره موبایل را تایید می‌کنید و نتیجه مرحله‌ای روی همین صفحه ساخته می‌شود.</p>
                                </div>
                            </div>

                            <div class="grid gap-2 text-sm text-zinc-200">
                                <div class="hv-info-band rounded-2xl p-4 ring-1 ring-white/10">
                                    <p class="font-bold text-white">مو: سه نمای رشد</p>
                                    <p class="mt-2 leading-7">دو هفته بعد از کاشت، رشد میانی و نتیجه نهایی به ترتیب ساخته و نمایش داده می‌شوند.</p>
                                </div>
                                <div class="hv-lift rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                                    <p class="font-bold text-white">ابرو: یک نتیجه متمرکز</p>
                                    <p class="mt-2 leading-7">برای کاشت ابرو یک تصویر نهایی طبیعی با تمرکز روی فرم، تراکم و هماهنگی چهره ساخته می‌شود.</p>
                                </div>
                                <div class="hv-lift rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                                    <p class="font-bold text-white">آرام و کنترل‌شده</p>
                                    <p class="mt-2 leading-7">تصاویر فقط برای ساخت همین پیش‌نمایش و پیگیری درخواست شما در همین کلینیک استفاده می‌شوند.</p>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <section class="hv-app-shell rounded-3xl p-4 shadow-2xl shadow-indigo-100/90 backdrop-blur sm:p-5 lg:p-6">
                        <div class="space-y-5">
                            <div class="space-y-3">
                                <div class="h-2 overflow-hidden rounded-full bg-zinc-200/80">
                                    <div id="stepProgress" class="h-full w-1/4 rounded-full bg-linear-to-l from-emerald-500 via-indigo-500 to-fuchsia-500 transition-all duration-500"></div>
                                </div>
                                <div class="grid gap-2 lg:grid-cols-4" aria-label="مراحل">
                                <button type="button" data-step-indicator="1" class="step-indicator hv-step hv-step-active rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white/15 text-xs font-bold">۱</span>
                                    <span class="block font-bold">انتخاب</span>
                                    <span class="text-xs text-white/70">نوع درمان</span>
                                </button>
                                <button type="button" data-step-indicator="2" class="step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500">۲</span>
                                    <span class="block font-bold">تصویر</span>
                                    <span class="text-xs text-zinc-500">انتخاب فایل</span>
                                </button>
                                <button type="button" data-step-indicator="3" class="step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500">۳</span>
                                    <span class="block font-bold">موبایل</span>
                                    <span class="text-xs text-zinc-500">OTP</span>
                                </button>
                                <button type="button" data-step-indicator="4" class="step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500">۴</span>
                                    <span class="block font-bold">نتیجه</span>
                                    <span class="text-xs text-zinc-500">مشاهده</span>
                                </button>
                                </div>
                            </div>

                            <section class="flow-step hv-enter space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step1">
                                <div class="hv-otp-brand rounded-3xl border p-4">
                                    <p class="text-sm font-bold" style="color: var(--hv-primary-color);"><?php echo e($tenant->name ?? 'HairVision'); ?></p>
                                    <h2 class="mt-1 text-xl font-bold text-zinc-950">اول نوع پیش‌نمایش را انتخاب کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">کاشت مو سه تصویر مرحله‌ای می‌سازد؛ کاشت ابرو یک تصویر نهایی دقیق و متمرکز روی فرم ابرو.</p>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <button type="button" data-treatment-option="hair_transplant" class="hv-treatment-card is-selected rounded-3xl p-5 text-right">
                                        <span class="hv-treatment-icon grid h-14 w-14 place-items-center rounded-2xl text-2xl text-white shadow-lg shadow-indigo-100">
                                            <span class="fi fi-rr-sparkles h-6 w-6" aria-hidden="true"></span>
                                        </span>
                                        <span class="mt-5 block text-xl font-bold text-zinc-950">کاشت مو</span>
                                        <span class="mt-2 block text-sm leading-7 text-zinc-600">سه تصویر: دو هفته بعد از کاشت، رشد میانی و نتیجه نهایی.</span>
                                        <span class="mt-4 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">۳ خروجی</span>
                                    </button>

                                    <button type="button" data-treatment-option="eyebrow_transplant" class="hv-treatment-card rounded-3xl p-5 text-right">
                                        <span class="hv-treatment-icon grid h-14 w-14 place-items-center rounded-2xl text-2xl text-white shadow-lg shadow-indigo-100">
                                            <span class="fi fi-rr-eye h-6 w-6" aria-hidden="true"></span>
                                        </span>
                                        <span class="mt-5 block text-xl font-bold text-zinc-950">کاشت ابرو</span>
                                        <span class="mt-2 block text-sm leading-7 text-zinc-600">یک تصویر نهایی با تمرکز روی تراکم، جهت خواب مو و هماهنگی با چهره.</span>
                                        <span class="mt-4 inline-flex rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700">۱ خروجی</span>
                                    </button>
                                </div>

                                <p id="treatmentError" class="hidden text-sm text-rose-600"></p>
                                <button id="continueToImage" type="button" class="hv-primary-action w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5">ادامه و انتخاب تصویر</button>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step2">
                                <div>
                                    <p class="text-sm font-bold text-indigo-600">بارگذاری تصویر</p>
                                    <h2 class="mt-2 text-xl font-bold text-zinc-950">تصویر خود را انتخاب کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">فایل در این مرحله فقط در مرورگر شما آماده می‌شود و بعد از تایید شماره به‌صورت امن آپلود خواهد شد.</p>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-[.9fr_1.1fr]">
                                    <label for="image" class="hv-upload-zone group relative flex cursor-pointer flex-col items-center justify-center overflow-hidden rounded-3xl bg-white p-4 text-center ring-1 ring-zinc-200 transition hover:bg-indigo-50/60 hover:ring-indigo-200">
                                        <input id="image" type="file" accept="image/*" class="sr-only" />
                                        <img id="imagePreview" alt="پیش نمایش تصویر" class="hidden h-full w-full cursor-zoom-in rounded-2xl object-cover" />
                                        <span id="imagePlaceholder" class="space-y-2">
                                            <span class="relative z-10 mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-linear-to-br from-zinc-950 via-indigo-800 to-emerald-700 text-xl text-white shadow-lg shadow-indigo-200 transition group-hover:scale-110" aria-hidden="true">
                                                <span class="fi fi-rr-upload h-6 w-6"></span>
                                            </span>
                                            <span class="block text-sm font-bold text-zinc-800">انتخاب تصویر</span>
                                            <span class="block text-xs text-zinc-500">JPG یا PNG تا ۵ مگابایت</span>
                                        </span>
                                    </label>

                                    <div class="space-y-4 rounded-3xl bg-white p-4 ring-1 ring-zinc-200 shadow-lg shadow-zinc-100/80">
                                        <div class="flex items-start gap-3">
                                            <input id="consent" type="checkbox" class="mt-1 h-4 w-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                                            <label for="consent" class="text-sm leading-7 text-zinc-600"><?php echo e($theme?->consent_text ?? 'با پردازش تصویر برای این سرویس موافقم.'); ?></label>
                                        </div>
                                        <p id="uploadError" class="hidden text-sm text-rose-600"></p>
                                        <div id="statusBox" class="hidden rounded-3xl p-px hv-ai-cast">
                                            <div class="rounded-3xl bg-white/86 p-4 backdrop-blur">
                                                <div class="flex items-center gap-2 text-xs font-bold text-zinc-500">
                                                    <span class="hv-live-dot h-2 w-2 rounded-full bg-emerald-500"></span>
                                                    <span>پردازش هوشمند</span>
                                                </div>
                                                <div class="hv-thinking-bubble mt-3 rounded-2xl border border-zinc-200 px-4 py-3">
                                                    <div class="mb-2 flex items-center gap-1 hv-thinking-dots" aria-hidden="true">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                    </div>
                                                    <p id="castMessage" class="min-h-12 text-sm leading-7 text-zinc-700"></p>
                                                </div>
                                                <div id="processingStageList" class="mt-4 grid gap-2"></div>
                                            </div>
                                        </div>
                                        <div class="grid gap-3 lg:grid-cols-2">
                                            <button id="continueToPhone" class="hv-primary-action w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5">ادامه و تایید شماره</button>
                                            <button id="backToTreatment" class="hv-lift w-full rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">بازگشت</button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step3">
                                <div class="hv-otp-brand rounded-3xl border p-4">
                                    <p class="text-sm font-bold" style="color: var(--hv-primary-color);">تایید امن شماره</p>
                                    <h2 class="mt-1 text-xl font-bold text-zinc-950">شماره موبایل را تایید کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">بعد از تایید OTP، تصویر آپلود می‌شود و درخواست ساخت نتیجه برای مدل انتخاب‌شده ارسال خواهد شد.</p>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-2">
                                    <div>
                                        <label for="phone" class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                                        <input id="phone" type="text" inputmode="numeric" autocomplete="tel" maxlength="11" pattern="[0-9۰-۹٠-٩]*" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-right text-base outline-none transition" />
                                        <p id="phoneError" class="mt-2 hidden text-sm text-rose-600"></p>
                                        <button id="requestOtp" type="button" class="hv-primary-action mt-3 w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">ارسال کد تایید</button>
                                    </div>

                                    <div>
                                        <label for="otp" class="block text-sm font-bold text-zinc-800">کد تایید</label>
                                        <input id="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9۰-۹٠-٩]*" placeholder="۱۲۳۴۵۶" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-center text-lg font-bold tracking-[0.3em] outline-none transition" dir="ltr" />
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">کد ارسال شده به <span id="displayPhone" class="font-bold text-zinc-900"></span></p>
                                        <p id="otpDebug" class="mt-2 hidden text-sm text-sky-700"></p>
                                        <p id="otpError" class="mt-2 hidden text-sm text-rose-600"></p>
                                    </div>
                                </div>

                                <div class="rounded-2xl bg-zinc-50 p-3 ring-1 ring-zinc-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span id="otpTimerText" class="font-bold text-zinc-700">زمان باقی‌مانده: <span id="otpCountdown" dir="ltr">۰۲:۰۰</span></span>
                                        <button id="resendOtp" type="button" class="hv-secondary-action rounded-xl px-3 py-2 text-xs font-bold transition disabled:cursor-not-allowed" disabled>ارسال مجدد کد</button>
                                    </div>
                                </div>

                                <p id="generationError" class="hidden text-sm text-rose-600"></p>

                                <div class="grid gap-3 lg:grid-cols-3">
                                    <button id="verifyOtp" type="button" class="hv-primary-action w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">تایید و شروع ساخت</button>
                                    <button id="backToImage" type="button" class="hv-lift w-full rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">بازگشت به تصویر</button>
                                    <button id="backToPhone" type="button" class="hv-lift w-full rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">تغییر شماره</button>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step4">
                                <div class="flex flex-wrap items-end justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-bold text-indigo-600">پیش نمایش آماده شد</p>
                                        <h2 class="mt-2 text-xl font-bold text-zinc-950">نتایج شما</h2>
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">برای بزرگنمایی روی هر تصویر کلیک کنید.</p>
                                    </div>
                                </div>
                                <div id="resultsStatusBox" class="hidden rounded-3xl p-px hv-ai-cast">
                                    <div class="rounded-3xl bg-white/90 p-4 backdrop-blur">
                                        <div class="flex items-center gap-2 text-xs font-bold text-zinc-500">
                                            <span class="hv-live-dot h-2 w-2 rounded-full bg-emerald-500"></span>
                                            <span>پردازش مرحله‌ای</span>
                                        </div>
                                        <div class="hv-thinking-bubble mt-3 rounded-2xl border border-zinc-200 px-4 py-3">
                                            <div class="mb-2 flex items-center gap-1 hv-thinking-dots" aria-hidden="true">
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                            </div>
                                            <p id="resultsCastMessage" class="text-sm leading-7 text-zinc-700"></p>
                                        </div>
                                        <div id="resultsStageList" class="mt-4 grid gap-2 lg:grid-cols-3"></div>
                                    </div>
                                </div>
                                <div id="resultsContainer" class="grid grid-cols-2 gap-3 lg:grid-cols-4"></div>
                            </section>
                        </div>
                    </section>
                </div>
            </section>
        </main>

        <footer class="w-full bg-white/85 shadow-[0_-1px_20px_rgba(24,24,27,0.04)] backdrop-blur">
            <?php echo $theme?->footer_html ?? '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.</p><p class="flex items-center gap-2"><span>قدرت گرفته از</span><span class="grid h-7 w-7 place-items-center rounded-xl bg-linear-to-br from-emerald-500 via-indigo-500 to-fuchsia-500"><img src="/theme/admin-logo-dark.svg" alt="" class="h-5 w-5"></span></p></div>'; ?>

        </footer>
    </div>

    <div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/85 p-4 backdrop-blur">
        <button id="closeImageModal" class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg transition hover:-translate-y-0.5" aria-label="بستن تصویر">
            <span class="fi fi-rr-cross-small h-4 w-4" aria-hidden="true"></span>
            <span>بستن</span>
        </button>
        <a id="downloadImageModal" class="absolute right-4 top-4 hidden items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg transition hover:-translate-y-0.5" href="#" download>
            <span class="fi fi-rr-arrow-down h-4 w-4" aria-hidden="true"></span>
            <span>دانلود</span>
        </a>
        <button id="prevImageModal" class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-xl font-bold text-zinc-950 shadow-lg transition hover:scale-105 sm:right-8" aria-label="تصویر قبلی">
            <span class="fi fi-rr-arrow-small-right h-5 w-5" aria-hidden="true"></span>
        </button>
        <figure class="w-full max-w-5xl">
            <div id="modalSkeleton" class="hv-skeleton mx-auto hidden aspect-4/5 max-h-[82vh] w-full max-w-xl rounded-3xl shadow-2xl"></div>
            <img id="modalImage" alt="نمای بزرگ تصویر" class="hv-modal-image mx-auto max-h-[82vh] max-w-full rounded-3xl object-contain shadow-2xl" />
            <figcaption id="modalCaption" class="mt-4 text-center text-sm font-bold text-white"></figcaption>
        </figure>
        <p id="modalToast" class="hv-modal-toast absolute bottom-4 left-4 right-4 hidden rounded-2xl px-4 py-3 text-center text-sm font-bold leading-7 text-white sm:left-1/2 sm:right-auto sm:w-full sm:max-w-xl sm:-translate-x-1/2"></p>
        <button id="nextImageModal" class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-xl font-bold text-zinc-950 shadow-lg transition hover:scale-105 sm:left-8" aria-label="تصویر بعدی">
            <span class="fi fi-rr-arrow-small-left h-5 w-5" aria-hidden="true"></span>
        </button>
    </div>

<?php
    $stageCatalog = app(\App\Services\Generation\GenerationStageCatalog::class);

    $generationFrontend = [
        'stages' => $stageCatalog->publicStages(),
        'stagesByService' => [
            'hair_transplant' => $stageCatalog->publicStages('hair_transplant'),
            'eyebrow_transplant' => $stageCatalog->publicStages('eyebrow_transplant'),
        ],
        'messages' => __('ai.generation.messages'),
        'statuses' => __('ai.generation.statuses'),
        'messageIntervalMs' => config('hairvision.generation.message_interval_ms'),
        'pollIntervalMs' => config('hairvision.generation.poll_interval_ms'),
        'softTimeoutMs' => config('hairvision.generation.soft_timeout_ms'),
        'maxWaitMs' => config('hairvision.generation.max_wait_ms'),
        'allowBackgroundCompletion' => config('hairvision.generation.allow_background_completion'),
        'pollTimeoutMs' => config('hairvision.generation.poll_timeout_ms'),
    ];
?>
<script>
    const GENERATION = <?php echo json_encode($generationFrontend, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    const STORAGE_KEYS = {
        phone: 'hairvisionPhone',
        verified: 'hairvisionVerified',
        activeGenerationGroupId: 'hairvisionActiveGenerationGroupId',
    };

    const STATE = {
        phone: null,
        verified: false,
        selectedTreatment: 'hair_transplant',
        uploadId: null,
        generationGroupId: null,
        originalPreviewUrl: null,
        galleryItems: [],
        activeGalleryIndex: 0,
        stageJobs: [],
        currentStageKey: null,
        currentMessage: '',
        messageIndex: 0,
        messageTimer: null,
        generationComplete: false,
        longRunningMessageShown: false,
        otpExpiresAt: null,
        otpTimer: null,
        otpRequestInFlight: false,
    };

    const DIGIT_MAP = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
    };

    const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    const SUPPORTED_DIGIT_PATTERN = /^[0-9۰-۹٠-٩]+$/u;

    function normalizeDigits(value) {
        return Array.from(value || '')
            .map((char) => DIGIT_MAP[char] ?? char)
            .filter((char) => /[0-9]/.test(char))
            .join('');
    }

    function toPersianDigits(value) {
        return String(value || '').replace(/[0-9]/g, (digit) => PERSIAN_DIGITS[Number(digit)]);
    }

    function isSupportedDigits(value) {
        return !value || SUPPORTED_DIGIT_PATTERN.test(value);
    }

    function bindDigitOnlyInput(input, maxLength, onComplete = null) {
        input.addEventListener('beforeinput', (event) => {
            if (!event.data) return;
            if (!isSupportedDigits(event.data)) event.preventDefault();
        });

        input.addEventListener('input', (event) => {
            const normalized = normalizeDigits(event.target.value).slice(0, maxLength);
            event.target.value = toPersianDigits(normalized);

            if (normalized.length === maxLength && typeof onComplete === 'function') {
                onComplete(normalized);
            }
        });
    }

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';
        return element.innerHTML;
    }

    function downloadFileName(item) {
        const period = String(item?.period || 'result').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();

        return `hairvision-${period}.jpg`;
    }

    function downloadLabel(item) {
        return `دانلود ${item?.label || 'تصویر'}`;
    }

    function wait(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function formatSeconds(seconds) {
        const safeSeconds = Math.max(0, Number(seconds) || 0);
        const minutes = Math.floor(safeSeconds / 60);
        const remainingSeconds = safeSeconds % 60;

        return toPersianDigits(`${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`);
    }

    function stopOtpTimer() {
        if (STATE.otpTimer) {
            clearInterval(STATE.otpTimer);
        }

        STATE.otpTimer = null;
        STATE.otpExpiresAt = null;
    }

    function updateOtpTimer() {
        const countdown = document.getElementById('otpCountdown');
        const timerText = document.getElementById('otpTimerText');
        const resendButton = document.getElementById('resendOtp');

        if (!countdown || !timerText || !resendButton || !STATE.otpExpiresAt) return;

        const remainingSeconds = Math.ceil((STATE.otpExpiresAt - Date.now()) / 1000);

        if (remainingSeconds <= 0) {
            countdown.textContent = formatSeconds(0);
            timerText.firstChild.textContent = 'کد منقضی شد. ';
            resendButton.disabled = STATE.otpRequestInFlight;
            resendButton.textContent = STATE.otpRequestInFlight ? 'در حال ارسال...' : 'ارسال مجدد کد';
            stopOtpTimer();

            return;
        }

        timerText.firstChild.textContent = 'زمان باقی‌مانده: ';
        countdown.textContent = formatSeconds(remainingSeconds);
        resendButton.disabled = true;
        resendButton.textContent = 'ارسال مجدد کد';
    }

    function startOtpTimer(seconds) {
        stopOtpTimer();

        STATE.otpExpiresAt = Date.now() + (Math.max(1, Number(seconds) || 120) * 1000);
        updateOtpTimer();
        STATE.otpTimer = setInterval(updateOtpTimer, 1000);
    }

    function markOtpExpired() {
        stopOtpTimer();

        const countdown = document.getElementById('otpCountdown');
        const timerText = document.getElementById('otpTimerText');
        const resendButton = document.getElementById('resendOtp');

        if (countdown) countdown.textContent = formatSeconds(0);
        if (timerText?.firstChild) timerText.firstChild.textContent = 'کد قبلی منقضی شده است. ';
        if (resendButton) {
            resendButton.disabled = false;
            resendButton.textContent = 'ارسال مجدد کد';
        }
    }


    function firstStageKey() {
        return GENERATION.stages?.[0]?.key || 'two_weeks';
    }

    function selectedStages() {
        return GENERATION.stagesByService?.[STATE.selectedTreatment] || GENERATION.stages || [];
    }

    function setActiveStages(stages) {
        if (Array.isArray(stages) && stages.length) {
            GENERATION.stages = stages;
            return;
        }

        GENERATION.stages = selectedStages();
    }

    function generationButton() {
        return document.getElementById('verifyOtp');
    }

    function stageMessages(stageKey) {
        return Array.isArray(GENERATION.messages?.[stageKey]) ? GENERATION.messages[stageKey] : [];
    }

    function completionMessage() {
        return GENERATION.messages?.completed?.[0] || 'پیش‌نمایش شما آماده شد.';
    }

    function failedMessage() {
        return GENERATION.messages?.failed?.[0] || 'ساخت پیش‌نمایش ناموفق بود. لطفاً دوباره تلاش کنید.';
    }

    function statusLabel(status) {
        return GENERATION.statuses?.[status] || status || '';
    }

    function verificationStatusCodes() {
        return [401, 403, 404, 419];
    }

    function friendlyApiMessage(status, fallback = 'خطایی رخ داد. لطفاً دوباره تلاش کنید.') {
        if (verificationStatusCodes().includes(Number(status))) {
            return 'نشست تایید شما معتبر نیست. لطفاً دوباره کد تایید دریافت کنید.';
        }

        return fallback;
    }

    function longRunningMessage() {
        return 'ساخت تصاویر با مدل واقعی کمی زمان‌بر است. پردازش همچنان در سرور ادامه دارد و نتیجه پس از آماده شدن نمایش داده می‌شود.';
    }

    function backgroundContinueMessage() {
        return 'پردازش تصاویر همچنان در پس‌زمینه ادامه دارد. می‌توانید چند دقیقه بعد دوباره همین صفحه را بررسی کنید.';
    }

    async function parseJsonResponse(response) {
        const text = await response.text();

        if (!text) return {};

        try {
            return JSON.parse(text);
        } catch (error) {
            return {};
        }
    }

    async function requestJson(url, options = {}) {
        const body = options.body;
        const isFormData = body instanceof FormData;

        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
                ...(options.headers || {}),
            },
        });

        const payload = await parseJsonResponse(response);

        if (!response.ok) {
            return {
                ...payload,
                success: false,
                status: response.status,
                message: payload.message || friendlyApiMessage(response.status),
                requiresVerification: verificationStatusCodes().includes(response.status),
            };
        }

        return { ...payload, status: response.status };
    }

    function clearActiveGeneration() {
        sessionStorage.removeItem(STORAGE_KEYS.activeGenerationGroupId);
    }

    function saveActiveGeneration(groupId) {
        if (!groupId) return;
        sessionStorage.setItem(STORAGE_KEYS.activeGenerationGroupId, groupId);
    }

    function handleApiFailure(payload, errorId = 'uploadError') {
        const message = payload?.requiresVerification
            ? 'نشست تایید شما معتبر نیست. لطفاً دوباره کد تایید دریافت کنید.'
            : (payload?.message || friendlyApiMessage(payload?.status));

        stopStageMessages();
        UI.clearStatus();
        UI.showError(errorId, message);

        const actionButton = generationButton();
        if (actionButton) {
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع ساخت';
        }

        if (payload?.requiresVerification) {
            STATE.verified = false;
            stopOtpTimer();
            sessionStorage.removeItem(STORAGE_KEYS.verified);
            document.getElementById('otp').value = '';
            UI.clearError('otpError');
            UI.clearDebugOtp();

            if (STATE.phone) {
                document.getElementById('displayPhone').textContent = toPersianDigits(STATE.phone);
                UI.showStep(3);
                UI.showError('otpError', message);
                document.getElementById('otp').focus();
            } else {
                UI.showStep(3);
                UI.showError('phoneError', message);
                document.getElementById('requestOtp').focus();
            }
        }
    }

    const UI = {
        showStep(stepNum) {
            document.querySelectorAll('#landingApp .flow-step').forEach((el) => {
                el.classList.add('hidden');
                el.classList.remove('hv-enter');
            });

            const nextStep = document.getElementById(`step${stepNum}`);
            nextStep.classList.remove('hidden');
            nextStep.classList.add('hv-enter');
            this.updateStepper(stepNum);
        },

        updateStepper(stepNum) {
            const progress = document.getElementById('stepProgress');

            if (progress) {
                progress.style.width = `${Math.max(25, stepNum * 25)}%`;
            }

            document.querySelectorAll('[data-step-indicator]').forEach((el) => {
                const index = Number(el.dataset.stepIndicator);
                const active = index === stepNum;
                const done = index < stepNum;
                const marker = el.querySelector('.step-marker');

                el.className = active
                    ? 'step-indicator hv-step hv-step-active rounded-2xl px-3 py-3 text-right text-sm transition'
                    : done
                        ? 'step-indicator hv-step hv-step-done rounded-2xl px-3 py-3 text-right text-sm transition'
                        : 'step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition hover:bg-indigo-50';

                if (marker) {
                    marker.innerHTML = toPersianDigits(index);
                    marker.className = active
                        ? 'step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white/15 text-xs font-bold text-white'
                        : done
                            ? 'step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-emerald-700 ring-1 ring-emerald-300'
                            : 'step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500';
                }

                const textSpans = el.querySelectorAll('span');
                const title = textSpans[1];
                const caption = textSpans[2];

                if (title) title.className = 'block font-bold';

                if (caption) {
                    caption.className = active
                        ? 'text-xs text-white/70'
                        : done
                            ? 'text-xs text-emerald-700/75'
                            : 'text-xs text-zinc-500';
                }
            });
        },

        clearError(id) {
            const el = document.getElementById(id);
            if (!el) return;

            el.textContent = '';
            el.classList.add('hidden');
        },

        showError(id, msg) {
            const el = document.getElementById(id);
            if (!el) return;

            el.textContent = msg;
            el.classList.remove('hidden');
        },

        showDebugOtp(code) {
            const el = document.getElementById('otpDebug');
            if (!el) return;

            el.textContent = `کد اشکال‌زدایی: ${toPersianDigits(code)}`;
            el.classList.remove('hidden');
        },

        clearDebugOtp() {
            const el = document.getElementById('otpDebug');
            if (!el) return;

            el.textContent = '';
            el.classList.add('hidden');
        },

        showStatus(message) {
            STATE.currentMessage = message || '';

            document.getElementById('statusBox').classList.remove('hidden');
            document.getElementById('resultsStatusBox').classList.remove('hidden');
            document.getElementById('castMessage').textContent = STATE.currentMessage;
            document.getElementById('resultsCastMessage').textContent = STATE.currentMessage;

            this.showModalToast(STATE.currentMessage);
        },

        clearStatus() {
            document.getElementById('castMessage').textContent = '';
            document.getElementById('resultsCastMessage').textContent = '';
            document.getElementById('statusBox').classList.add('hidden');
            document.getElementById('resultsStatusBox').classList.add('hidden');

            this.hideModalToast();
        },

        showModalToast(message) {
            const modal = document.getElementById('imageModal');
            const toast = document.getElementById('modalToast');

            if (!toast || modal.classList.contains('hidden') || STATE.generationComplete || !message) return;

            toast.textContent = message;
            toast.classList.remove('hidden');
        },

        hideModalToast() {
            const toast = document.getElementById('modalToast');
            if (!toast) return;

            toast.textContent = '';
            toast.classList.add('hidden');
        },
    };

    const API = {
        async requestOtp(phone) {
            return requestJson('/api/v1/auth/otp/request', {
                method: 'POST',
                body: JSON.stringify({ phone }),
            });
        },

        async verifyOtp(phone, code) {
            return requestJson('/api/v1/auth/otp/verify', {
                method: 'POST',
                body: JSON.stringify({ phone, code }),
            });
        },

        async uploadImage(file, consent) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('consent', consent ? '1' : '0');

            return requestJson('/api/v1/uploads', {
                method: 'POST',
                body: formData,
            });
        },

        async createGeneration(uploadId) {
            return requestJson('/api/v1/generations', {
                method: 'POST',
                body: JSON.stringify({
                    upload_id: uploadId,
                    usage_category_key: STATE.selectedTreatment,
                }),
            });
        },

        async getGenerationStatus(groupId) {
            return requestJson(`/api/v1/generations/${groupId}/status`);
        },

        async getGenerationResults(groupId) {
            return requestJson(`/api/v1/generations/${groupId}/results`);
        },
    };

    bindDigitOnlyInput(document.getElementById('phone'), 11);

    document.getElementById('phone').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('requestOtp').click();
        }
    });

    bindDigitOnlyInput(document.getElementById('otp'), 6, () => {
        const button = document.getElementById('verifyOtp');

        if (!button.disabled) {
            document.getElementById('verifyOtp').click();
        }
    });

    document.querySelectorAll('[data-treatment-option]').forEach((button) => {
        button.addEventListener('click', () => {
            STATE.selectedTreatment = button.dataset.treatmentOption || 'hair_transplant';
            setActiveStages();
            resetGenerationProgress();
            UI.clearError('treatmentError');

            document.querySelectorAll('[data-treatment-option]').forEach((option) => {
                option.classList.toggle('is-selected', option === button);
            });
        });
    });

    document.getElementById('continueToImage').addEventListener('click', () => {
        if (!STATE.selectedTreatment) {
            return UI.showError('treatmentError', 'لطفاً نوع پیش‌نمایش را انتخاب کنید.');
        }

        setActiveStages();
        renderStagePanels([]);
        UI.showStep(2);
    });

    document.getElementById('image').addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('imagePlaceholder');

        if (STATE.originalPreviewUrl) URL.revokeObjectURL(STATE.originalPreviewUrl);

        STATE.originalPreviewUrl = URL.createObjectURL(file);
        preview.src = STATE.originalPreviewUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');

        primeGallery();
    });

    document.getElementById('imagePreview').addEventListener('click', (event) => {
        if (!STATE.originalPreviewUrl) return;

        event.preventDefault();
        event.stopPropagation();

        primeGallery();
        openImageModal(0);
    });

    async function requestOtpForPhone(phone, mode = 'initial') {
        const initialButton = document.getElementById('requestOtp');
        const resendButton = document.getElementById('resendOtp');
        const activeButton = mode === 'resend' ? resendButton : initialButton;

        UI.clearError(mode === 'resend' ? 'otpError' : 'phoneError');

        if (phone.length !== 11) {
            return UI.showError('phoneError', 'شماره موبایل باید ۱۱ رقم باشد.');
        }

        if (STATE.otpRequestInFlight || activeButton?.disabled) return;

        STATE.otpRequestInFlight = true;
        activeButton.disabled = true;
        activeButton.textContent = 'در حال ارسال...';

        const result = await API.requestOtp(phone);

        STATE.otpRequestInFlight = false;
        initialButton.disabled = false;
        initialButton.textContent = 'ارسال کد تایید';
        if (mode === 'resend') {
            resendButton.disabled = false;
            resendButton.textContent = 'ارسال مجدد کد';
        }

        if (result.success) {
            STATE.phone = phone;
            STATE.verified = false;

            sessionStorage.setItem(STORAGE_KEYS.phone, phone);
            sessionStorage.removeItem(STORAGE_KEYS.verified);

            document.getElementById('displayPhone').textContent = toPersianDigits(phone);
            document.getElementById('otp').value = '';

            if (result.debug_sms) {
                UI.showDebugOtp(result.debug_sms);
            } else {
                UI.clearDebugOtp();
            }

            startOtpTimer(result.expires_in || 120);
            UI.showStep(3);
            document.getElementById('otp').focus();

            return;
        }

        updateOtpTimer();
        if (mode === 'resend' && !STATE.otpExpiresAt) {
            resendButton.disabled = false;
            resendButton.textContent = 'ارسال مجدد کد';
        }
        UI.showError(mode === 'resend' ? 'otpError' : 'phoneError', result.message || 'ارسال کد تایید ناموفق بود.');
    }

    document.getElementById('requestOtp').addEventListener('click', async () => {
        const phone = normalizeDigits(document.getElementById('phone').value);

        await requestOtpForPhone(phone);
    });

    document.getElementById('resendOtp').addEventListener('click', async () => {
        if (!STATE.phone) {
            UI.showStep(3);
            return UI.showError('phoneError', 'برای ارسال کد، ابتدا شماره موبایل خود را وارد کنید.');
        }

        await requestOtpForPhone(STATE.phone, 'resend');
    });

    document.getElementById('verifyOtp').addEventListener('click', async () => {
        const code = normalizeDigits(document.getElementById('otp').value);

        UI.clearError('otpError');

        if (!code || code.length !== 6) {
            return UI.showError('otpError', 'کد ۶ رقمی را وارد کنید.');
        }

        const btn = document.getElementById('verifyOtp');

        if (btn.disabled) return;

        btn.disabled = true;
        btn.textContent = 'در حال تایید...';

        const result = await API.verifyOtp(STATE.phone, code);

        btn.disabled = false;
        btn.textContent = 'تایید و شروع ساخت';

        if (result.success) {
            STATE.verified = true;
            stopOtpTimer();

            sessionStorage.setItem(STORAGE_KEYS.phone, STATE.phone);
            sessionStorage.setItem(STORAGE_KEYS.verified, '1');

            await beginGenerationProcess(btn);

            return;
        }

        UI.showError('otpError', result.message || 'تایید کد ناموفق بود.');
    });

    document.getElementById('backToPhone').addEventListener('click', () => {
        document.getElementById('otp').value = '';

        STATE.verified = false;
        stopOtpTimer();
        sessionStorage.removeItem(STORAGE_KEYS.verified);

        UI.clearError('otpError');
        UI.clearDebugOtp();
        UI.showStep(3);
    });

    document.getElementById('continueToPhone').addEventListener('click', () => {
        const fileInput = document.getElementById('image');
        const consent = document.getElementById('consent').checked;

        UI.clearError('uploadError');

        if (!fileInput.files.length) {
            return UI.showError('uploadError', 'لطفاً یک تصویر انتخاب کنید.');
        }

        if (!consent) {
            return UI.showError('uploadError', 'برای ادامه باید رضایت پردازش تصویر را تایید کنید.');
        }

        UI.showStep(3);
        document.getElementById('phone').focus();
    });

    document.getElementById('backToTreatment').addEventListener('click', () => {
        UI.clearError('uploadError');
        UI.showStep(1);
    });

    document.getElementById('backToImage').addEventListener('click', () => {
        UI.clearError('generationError');
        UI.clearError('otpError');
        UI.showStep(2);
    });

    async function beginGenerationProcess(button = null) {
        const fileInput = document.getElementById('image');
        const consent = document.getElementById('consent').checked;
        const btn = button || generationButton();

        UI.clearError('generationError');

        if (!STATE.verified) {
            sessionStorage.removeItem(STORAGE_KEYS.verified);
            UI.showStep(3);
            UI.showError('phoneError', 'برای ادامه، لطفاً ابتدا شماره موبایل خود را تایید کنید.');
            return;
        }

        if (STATE.generationGroupId && !STATE.generationComplete) {
            btn.disabled = true;
            btn.textContent = 'در حال بررسی نتیجه...';

            try {
                const statusResult = await API.getGenerationStatus(STATE.generationGroupId);

                if (statusResult.success === false) {
                    btn.disabled = false;
                    btn.textContent = 'تایید و شروع ساخت';
                    handleApiFailure(statusResult, 'generationError');
                    return;
                }

                const state = applyGenerationStatus(statusResult);

                if (state === 'processing') {
                    const pollState = await pollUntilComplete();

                    if (pollState === 'background') {
                        btn.disabled = false;
                        btn.textContent = 'بررسی وضعیت نتیجه';
                    }
                }
            } catch (error) {
                btn.disabled = false;
                btn.textContent = 'بررسی وضعیت نتیجه';
                UI.showStatus('ارتباط موقتاً قطع شد، اما اگر پردازش در سرور شروع شده باشد، ساخت تصاویر در پس‌زمینه ادامه دارد.');
            }

            return;
        }

        if (!fileInput.files.length) {
            UI.showStep(2);
            return UI.showError('uploadError', 'لطفاً یک تصویر انتخاب کنید.');
        }

        if (!consent) {
            UI.showStep(2);
            return UI.showError('uploadError', 'برای ادامه باید رضایت پردازش تصویر را تایید کنید.');
        }

        UI.clearDebugOtp();
        resetGenerationProgress(false);

        btn.disabled = true;
        btn.textContent = 'در حال ارسال...';

        UI.showStep(4);
        startStageMessages(firstStageKey(), true);

        try {
            const result = await API.uploadImage(fileInput.files[0], consent);

            if (result.success && result.upload?.id) {
                STATE.uploadId = result.upload.id;
                btn.textContent = 'در حال ساخت مرحله اول...';

                const genResult = await API.createGeneration(STATE.uploadId);

                if (genResult.success && genResult.generation_group?.id) {
                    STATE.generationGroupId = genResult.generation_group.id;
                    saveActiveGeneration(STATE.generationGroupId);

                    const state = applyGenerationStatus(genResult);

                    if (state === 'processing') {
                        const pollState = await pollUntilComplete();

                        if (pollState === 'background') {
                            btn.disabled = false;
                            btn.textContent = 'بررسی وضعیت نتیجه';
                        }
                    }

                    return;
                }

                btn.disabled = false;
                btn.textContent = 'تایید و شروع ساخت';
                handleApiFailure(genResult, 'generationError');

                return;
            }

            btn.disabled = false;
            btn.textContent = 'تایید و شروع ساخت';
            handleApiFailure(result, 'generationError');
        } catch (error) {
            stopStageMessages();

            btn.disabled = false;
            btn.textContent = 'تایید و شروع ساخت';

            UI.clearStatus();
            UI.showError('generationError', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    document.getElementById('closeImageModal').addEventListener('click', closeImageModal);
    document.getElementById('prevImageModal').addEventListener('click', showPreviousImage);
    document.getElementById('nextImageModal').addEventListener('click', showNextImage);

    document.getElementById('imageModal').addEventListener('click', (event) => {
        if (event.target.id === 'imageModal') closeImageModal();
    });

    document.addEventListener('keydown', (event) => {
        const modal = document.getElementById('imageModal');

        if (modal.classList.contains('hidden')) return;

        if (event.key === 'Escape') closeImageModal();
        if (event.key === 'ArrowRight') showPreviousImage();
        if (event.key === 'ArrowLeft') showNextImage();
    });

    async function pollUntilComplete() {
        const startedAt = Date.now();
        const intervalMs = Number(GENERATION.pollIntervalMs || 5000);
        const softTimeoutMs = Number(GENERATION.softTimeoutMs || 120000);
        const legacyTimeoutMs = Number(GENERATION.pollTimeoutMs || 900000);
        const maxWaitMs = Number(GENERATION.maxWaitMs || legacyTimeoutMs || 900000);
        let softMessageShown = false;

        while (Date.now() - startedAt < maxWaitMs) {
            await wait(intervalMs);

            const elapsed = Date.now() - startedAt;
            const statusResult = await API.getGenerationStatus(STATE.generationGroupId);

            if (statusResult.success === false) {
                handleApiFailure(statusResult);
                return 'failed';
            }

            const state = applyGenerationStatus(statusResult);

            if (state === 'completed' || state === 'failed') {
                return state;
            }

            if (elapsed >= softTimeoutMs && !softMessageShown) {
                softMessageShown = true;
                STATE.longRunningMessageShown = true;
                UI.showStatus(longRunningMessage());
            }
        }

        stopStageMessages();
        UI.showStatus(backgroundContinueMessage());
        UI.showError('generationError', backgroundContinueMessage());

        return 'background';
    }

    function applyGenerationStatus(payload) {
        setActiveStages(payload.stages);

        const jobs = Array.isArray(payload.jobs) ? payload.jobs : [];

        STATE.stageJobs = jobs;
        renderStagePanels(jobs);

        const readyJobs = jobs.filter((job) => job.asset_url);
        const failed = payload.status === 'failed' || jobs.some((job) => job.status === 'failed');
        const completed = payload.status === 'completed' || (jobs.length > 0 && jobs.every((job) => job.status === 'completed'));

        if (readyJobs.length > 0 || !document.getElementById('step4').classList.contains('hidden')) {
            displayGenerationResults(jobs);
            UI.showStep(4);
        }

        if (failed) {
            clearActiveGeneration();
            stopStageMessages();

            STATE.generationComplete = false;

            UI.showStatus(failedMessage());
            UI.showError('generationError', failedMessage());

            const actionButton = generationButton();
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع ساخت';

            return 'failed';
        }

        if (completed) {
            clearActiveGeneration();
            displayGenerationResults(jobs);
            UI.showStep(4);
            showCompletionMessage();

            const actionButton = generationButton();
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع ساخت';

            return 'completed';
        }

        const current = payload.current_stage?.key || currentStageFromJobs(jobs)?.key || firstStageKey();
        startStageMessages(current);

        return 'processing';
    }

    function currentStageFromJobs(jobs) {
        return jobs.find((job) => job.status === 'processing')
            || jobs.find((job) => job.status === 'pending')
            || jobs.find((job) => job.status === 'failed')
            || null;
    }

    function startStageMessages(stageKey, force = false) {
        const key = stageKey || firstStageKey();
        const messages = stageMessages(key);

        if (!messages.length) return;

        if (!force && STATE.currentStageKey === key && STATE.messageTimer) {
            return;
        }

        stopStageMessages();

        STATE.currentStageKey = key;
        STATE.messageIndex = 0;

        UI.showStatus(messages[0]);

        STATE.messageTimer = window.setInterval(() => {
            STATE.messageIndex = (STATE.messageIndex + 1) % messages.length;
            UI.showStatus(messages[STATE.messageIndex]);
        }, Number(GENERATION.messageIntervalMs || 10000));
    }

    function stopStageMessages() {
        if (STATE.messageTimer) {
            window.clearInterval(STATE.messageTimer);
        }

        STATE.messageTimer = null;
    }

    function showCompletionMessage() {
        stopStageMessages();

        STATE.generationComplete = true;

        UI.showStatus(completionMessage());
        UI.hideModalToast();
    }

    function renderStagePanels(jobs) {
        renderStageList('processingStageList', jobs);
        renderStageList('resultsStageList', jobs);
    }

    function renderStageList(containerId, jobs) {
        const container = document.getElementById(containerId);

        if (!container) return;

        container.innerHTML = (GENERATION.stages || []).map((stage, index) => {
            const job = jobs.find((item) => item.key === stage.key || item.period === stage.key) || {};
            const status = job.status || 'pending';
            const failed = status === 'failed';
            const completed = status === 'completed';
            const processing = status === 'processing';
            const statusClass = failed ? 'is-failed' : completed ? 'is-completed' : processing ? 'is-processing' : '';
            const markerClass = completed
                ? 'bg-emerald-700 text-white'
                : failed
                    ? 'bg-rose-600 text-white'
                    : processing
                        ? 'bg-indigo-700 text-white'
                        : 'bg-white text-zinc-500';
            const marker = toPersianDigits(index + 1);
            const error = failed && job.error
                ? `<p class="mt-2 text-xs leading-6 text-rose-700">${escapeHtml(job.error)}</p>`
                : '';

            return `
                <article class="hv-generation-stage ${statusClass} rounded-2xl p-3">
                    <div class="flex items-start gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-xs font-bold ${markerClass}">${marker}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-zinc-900">${escapeHtml(stage.label)}</span>
                            <span class="mt-1 block text-xs leading-6 text-zinc-500">${escapeHtml(stage.caption || '')}</span>
                            <span class="mt-2 inline-flex rounded-full bg-white/75 px-2 py-1 text-xs font-bold text-zinc-600 ring-1 ring-zinc-200">${escapeHtml(statusLabel(status))}</span>
                            ${error}
                        </span>
                    </div>
                </article>
            `;
        }).join('');
    }

    function galleryItemsFromJobs(jobs = STATE.stageJobs) {
        return [
            {
                period: 'original',
                label: 'تصویر اولیه',
                caption: 'تصویر بارگذاری شده',
                status: STATE.originalPreviewUrl ? 'completed' : 'pending',
                url: STATE.originalPreviewUrl,
            },
            ...(GENERATION.stages || []).map((stage) => {
                const job = jobs.find((item) => item.key === stage.key || item.period === stage.key) || {};

                return {
                    period: stage.key,
                    label: stage.label,
                    caption: stage.caption,
                    status: job.status || 'pending',
                    url: job.asset_url || null,
                };
            }),
        ];
    }

    function primeGallery() {
        STATE.galleryItems = galleryItemsFromJobs();
    }

    function displayGenerationResults(jobs) {
        const container = document.getElementById('resultsContainer');
        container.innerHTML = '';

        const items = galleryItemsFromJobs(jobs);
        STATE.galleryItems = items;

        items.forEach((item, index) => {
            const card = document.createElement('article');
            card.className = 'hv-result-card hv-enter overflow-hidden rounded-3xl bg-white shadow-lg shadow-indigo-100/70';
            card.style.animationDelay = `${index * 70}ms`;

            const safeUrl = item.url ? escapeHtml(item.url) : '';
            const media = item.url
                ? `<img src="${safeUrl}" alt="${escapeHtml(item.label)}" class="hv-result-image aspect-[4/5] w-full object-cover" loading="lazy" />`
                : `<span class="hv-skeleton block aspect-[4/5] w-full"></span><span class="absolute inset-0 grid place-items-center px-4 text-center"><span><span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white/90 text-sm font-bold text-zinc-500 shadow-sm">${toPersianDigits(index + 1)}</span><span class="mt-3 block text-sm font-bold text-zinc-600">${escapeHtml(statusLabel(item.status))}</span></span></span>`;

            const status = item.period === 'original'
                ? `<span class="mt-3 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">${escapeHtml(statusLabel(item.status))}</span>`
                : `<span class="mt-3 inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-600">${escapeHtml(statusLabel(item.status))}</span>`;

            const downloadAction = item.url
                ? `<a class="inline-flex items-center gap-1.5 rounded-full bg-zinc-950 px-3 py-1.5 text-xs font-bold text-white transition hover:-translate-y-0.5 hover:bg-zinc-800" href="${safeUrl}" download="${escapeHtml(downloadFileName(item))}" aria-label="${escapeHtml(downloadLabel(item))}" data-gallery-download><span class="fi fi-rr-arrow-down h-3.5 w-3.5" aria-hidden="true"></span><span>دانلود</span></a>`
                : '';

            card.innerHTML = `
                <button type="button" class="block w-full text-right" data-gallery-index="${index}">
                    <span class="relative block overflow-hidden bg-zinc-100">${media}</span>
                </button>
                <div class="p-4">
                    <button type="button" class="block w-full text-right" data-gallery-index="${index}">
                        <span class="block text-base font-bold text-zinc-950">${escapeHtml(item.label)}</span>
                        <span class="mt-1 block text-sm leading-6 text-zinc-500">${escapeHtml(item.caption || '')}</span>
                    </button>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        ${status}
                        ${downloadAction}
                    </div>
                </div>
            `;

            container.appendChild(card);
        });

        container.querySelectorAll('[data-gallery-index]').forEach((button) => {
            button.addEventListener('click', () => openImageModal(Number(button.dataset.galleryIndex)));
        });

        if (!document.getElementById('imageModal').classList.contains('hidden') && STATE.galleryItems.length) {
            STATE.activeGalleryIndex = Math.min(STATE.activeGalleryIndex, STATE.galleryItems.length - 1);
            renderModalImage(false);
        }
    }

    function openImageModal(index) {
        if (!STATE.galleryItems.length) {
            primeGallery();
        }

        if (!STATE.galleryItems.length) return;

        STATE.activeGalleryIndex = Math.min(
            Math.max(Number.isFinite(index) ? index : 0, 0),
            STATE.galleryItems.length - 1
        );

        const modal = document.getElementById('imageModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        renderModalImage(false);
        UI.showModalToast(STATE.currentMessage);
    }

    function closeImageModal() {
        const modal = document.getElementById('imageModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.getElementById('modalSkeleton').classList.add('hidden');
        document.getElementById('modalImage').src = '';
        document.getElementById('modalImage').classList.remove('hidden', 'is-changing');
        document.getElementById('modalCaption').textContent = '';
        document.getElementById('downloadImageModal').classList.add('hidden');
        document.getElementById('downloadImageModal').classList.remove('inline-flex');
        document.getElementById('downloadImageModal').removeAttribute('href');
        document.getElementById('downloadImageModal').removeAttribute('download');

        UI.hideModalToast();
    }

    function showPreviousImage() {
        if (!STATE.galleryItems.length) return;

        STATE.activeGalleryIndex = (STATE.activeGalleryIndex - 1 + STATE.galleryItems.length) % STATE.galleryItems.length;
        renderModalImage(true);
    }

    function showNextImage() {
        if (!STATE.galleryItems.length) return;

        STATE.activeGalleryIndex = (STATE.activeGalleryIndex + 1) % STATE.galleryItems.length;
        renderModalImage(true);
    }

    function renderModalImage(animate = true) {
        const item = STATE.galleryItems[STATE.activeGalleryIndex];

        if (!item) return;

        const image = document.getElementById('modalImage');
        const skeleton = document.getElementById('modalSkeleton');
        const caption = document.getElementById('modalCaption');
        const downloadLink = document.getElementById('downloadImageModal');

        const applyImage = () => {
            if (!item.url) {
                image.classList.add('hidden');
                image.classList.remove('is-changing');
                image.removeAttribute('src');
                downloadLink.classList.add('hidden');
                downloadLink.classList.remove('inline-flex');
                downloadLink.removeAttribute('href');
                downloadLink.removeAttribute('download');

                skeleton.classList.remove('hidden');
                caption.textContent = `${item.label} - ${statusLabel(item.status || 'pending')}`;

                UI.showModalToast(STATE.currentMessage);

                return;
            }

            skeleton.classList.add('hidden');
            image.classList.remove('hidden');
            image.src = item.url;
            image.alt = item.label || 'نمای بزرگ تصویر';
            caption.textContent = `${item.label} - ${item.caption}`;
            downloadLink.href = item.url;
            downloadLink.download = downloadFileName(item);
            downloadLink.setAttribute('aria-label', downloadLabel(item));
            downloadLink.classList.remove('hidden');
            downloadLink.classList.add('inline-flex');
            image.classList.remove('is-changing');
        };

        if (!animate) {
            applyImage();
            return;
        }

        if (item.url) {
            image.classList.add('is-changing');
        }

        window.setTimeout(applyImage, 160);
    }

    function resetImageInput() {
        document.getElementById('image').value = '';
        document.getElementById('consent').checked = false;

        const preview = document.getElementById('imagePreview');
        const placeholder = document.getElementById('imagePlaceholder');

        preview.src = '';
        preview.classList.add('hidden');
        placeholder.classList.remove('hidden');

        if (STATE.originalPreviewUrl) {
            URL.revokeObjectURL(STATE.originalPreviewUrl);
            STATE.originalPreviewUrl = null;
        }

        STATE.galleryItems = [];
    }

    function resetGenerationProgress(clearStatus = true) {
        stopStageMessages();

        STATE.uploadId = null;
        STATE.generationGroupId = null;
        STATE.stageJobs = [];
        STATE.galleryItems = [];
        STATE.currentStageKey = null;
        STATE.currentMessage = '';
        STATE.messageIndex = 0;
        STATE.generationComplete = false;
        STATE.longRunningMessageShown = false;

        clearActiveGeneration();
        renderStagePanels([]);

        document.getElementById('resultsContainer').innerHTML = '';

        const actionButton = generationButton();
        if (actionButton) {
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع ساخت';
        }

        if (clearStatus) UI.clearStatus();
    }

    function loadSessionState() {
        const storedPhone = sessionStorage.getItem(STORAGE_KEYS.phone);

        /*
         * Do not fully trust sessionStorage as server-side verification.
         * Laravel lead verification lives in the backend session/cookie.
         * After session driver changes, expired cookies, or server cleanup,
         * sessionStorage may still say verified while the backend session is gone.
         */
        sessionStorage.removeItem(STORAGE_KEYS.verified);
        STATE.verified = false;

        if (storedPhone) {
            STATE.phone = normalizeDigits(storedPhone).slice(0, 11);
            document.getElementById('phone').value = toPersianDigits(STATE.phone);
            document.getElementById('displayPhone').textContent = toPersianDigits(STATE.phone);
            markOtpExpired();
        }

        setActiveStages();
        UI.showStep(1);
    }

    async function resumeActiveGenerationIfPresent() {
        const activeGroupId = sessionStorage.getItem(STORAGE_KEYS.activeGenerationGroupId);

        if (!activeGroupId) return;

        STATE.generationGroupId = activeGroupId;

        UI.showStep(4);
        UI.showStatus('در حال بررسی وضعیت پردازش قبلی...');

        try {
            const statusResult = await API.getGenerationStatus(activeGroupId);

            if (statusResult.success === false) {
                clearActiveGeneration();
                handleApiFailure(statusResult);
                return;
            }

            const state = applyGenerationStatus(statusResult);

            if (state === 'processing') {
                const actionButton = generationButton();
                if (actionButton) actionButton.textContent = 'بررسی وضعیت نتیجه';
                await pollUntilComplete();
            }
        } catch (error) {
            UI.showStatus('ارتباط موقتاً قطع شد، اما اگر پردازش در سرور شروع شده باشد، ساخت تصاویر در پس‌زمینه ادامه دارد.');
        }
    }

    renderStagePanels([]);
    loadSessionState();
    resumeActiveGenerationIfPresent();
</script>
</body>
</html>
