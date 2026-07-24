<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <?php $landingTheme = $landingTheme ?? app(\App\Services\Landing\LandingTemplateResolver::class)->resolve($tenant); ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo e($tenant->name ?? 'پیش‌نمایی هوشمند'); ?></title>
    <?php
        $defaultFaviconHref = app('Illuminate\Foundation\Vite')->asset('resources/img/favicon.png');
        $faviconHref = app(\App\Services\Landing\LandingTemplateResolver::class)->faviconHref($landingTheme, $defaultFaviconHref);
        $primaryColor = (string) ($landingTheme['primary_color'] ?? '#0f766e');
        $primaryColor = preg_match('/^#[0-9a-fA-F]{6}$/', $primaryColor) ? $primaryColor : '#0f766e';
        $hairConsultationHref = '/theme/category-icons/hair-transplant.svg';
        $faceConsultationHref = '/theme/category-icons/eyebrow-transplant.svg';
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
        .hv-service-visual {
            object-fit: contain;
            transition: transform .35s ease, filter .35s ease;
        }
        .hv-treatment-card:hover .hv-service-visual,
        .hv-treatment-card.is-selected .hv-service-visual {
            transform: scale(1.045);
            filter: saturate(1.08) contrast(1.04);
        }
        .hv-result-card img {
            transition: transform .35s ease, filter .35s ease;
        }
        .hv-result-card:hover .hv-result-image {
            transform: scale(1.045);
            filter: saturate(1.08) contrast(1.03);
        }
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
            min-height: 15rem;
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
            cursor: pointer;
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
        #landingApp button:not(:disabled),
        #imageModal button:not(:disabled),
        #imageModal a,
        [data-gallery-index] {
            cursor: pointer;
        }
        #landingApp button:disabled,
        #imageModal button:disabled {
            cursor: not-allowed;
        }
    </style>
</head>
<body class="min-h-screen bg-[#eef2ff] font-sans text-zinc-900 antialiased" style="--hv-primary-color: <?php echo e($primaryColor); ?>;" data-landing-template="<?php echo e($landingTheme['template_code']); ?>">
    <div class="flex min-h-screen flex-col">
        <header class="w-full bg-white/85 shadow-sm shadow-zinc-200/60 backdrop-blur">
            <?php echo $landingTheme['header_html']; ?>

        </header>

        <main class="w-full flex-1">
            <?php if(! empty($landingTheme['body_html'])): ?>
                <section data-landing-template-body>
                    <?php echo $landingTheme['body_html']; ?>
                </section>
            <?php endif; ?>
            <section class="relative isolate overflow-hidden px-4 py-6 sm:px-6 lg:py-8">
                <div class="absolute inset-0 -z-10 bg-[linear-gradient(135deg,#ecfeff_0%,#f8fafc_32%,#eef2ff_68%,#faf5ff_100%)]"></div>

                <div id="landingApp" class="mx-auto w-full max-w-6xl">
                    <section class="hv-app-shell rounded-3xl p-4 shadow-2xl shadow-indigo-100/90 backdrop-blur sm:p-5 lg:p-6">
                        <?php if(! empty($landingTheme['back_link_url'])): ?>
                            <div class="mb-4 flex justify-end">
                                <a href="<?php echo e($landingTheme['back_link_url']); ?>" class="hv-lift rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">
                                    <?php echo e($landingTheme['back_link_title'] ?? 'بازگشت به سایت'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
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
                                    <h2 class="mt-1 text-xl font-bold text-zinc-950">اول کاربرد موردنظر را انتخاب کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">گزینه‌ها بر اساس سرویس‌های فعال همین کلینیک نمایش داده می‌شوند.</p>
                                </div>

                                <div id="categoryOptions" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>
                                <div id="noApplicationsPanel" class="hidden rounded-3xl bg-zinc-50 p-5 text-sm leading-7 text-zinc-600 ring-1 ring-zinc-200">
                                    در حال حاضر این کلینیک کاربرد فعال قابل درخواست ندارد.
                                </div>

                                <p id="treatmentError" class="hidden text-sm text-rose-600"></p>
                                <div class="flex justify-end">
                                    <button id="continueToImage" type="button" class="hv-primary-action inline-flex min-w-44 justify-center rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60" disabled>ادامه و انتخاب تصویر</button>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step2">
                                <div>
                                    <p class="text-sm font-bold text-indigo-600">بارگذاری تصویر</p>
                                    <h2 class="mt-2 text-xl font-bold text-zinc-950">تصویر خود را انتخاب کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">تصویر بعد از تایید شماره به‌صورت خصوصی برای همین کلینیک ذخیره می‌شود؛ سپس خروجی موردنظر را انتخاب می‌کنید.</p>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-[minmax(15rem,22rem)_minmax(0,1fr)]">
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

                                    <div class="max-w-2xl space-y-4 rounded-3xl bg-white p-4 ring-1 ring-zinc-200 shadow-lg shadow-zinc-100/80">
                                        <div class="flex items-start gap-3">
                                            <input id="consent" type="checkbox" class="mt-1 h-4 w-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                                            <label for="consent" class="text-sm leading-7 text-zinc-600"><?php echo e($landingTheme['consent_text']); ?></label>
                                        </div>
                                        <p id="uploadError" class="hidden text-sm text-rose-600"></p>
                                        <div class="flex flex-wrap gap-3">
                                            <button id="continueToPhone" class="hv-primary-action inline-flex min-w-44 justify-center rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5">ادامه و تایید شماره</button>
                                            <button id="backToTreatment" class="hv-lift inline-flex min-w-28 justify-center rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">بازگشت</button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step3">
                                <div class="hv-otp-brand rounded-3xl border p-4">
                                    <p class="text-sm font-bold" style="color: var(--hv-primary-color);">تایید امن شماره</p>
                                    <h2 class="mt-1 text-xl font-bold text-zinc-950">شماره موبایل را تایید کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">بعد از تایید OTP، تصویر ذخیره می‌شود و اگر چند خروجی فعال باشد، خروجی موردنظر را همین‌جا انتخاب می‌کنید.</p>
                                </div>

                                <div id="phoneEntryPanel" class="max-w-xl">
                                    <div>
                                        <label for="phone" class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                                        <input id="phone" type="text" inputmode="numeric" autocomplete="tel" maxlength="11" pattern="[0-9۰-۹٠-٩]*" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-right text-base outline-none transition" />
                                        <p id="phoneError" class="mt-2 hidden text-sm text-rose-600"></p>
                                        <button id="requestOtp" type="button" class="hv-primary-action mt-3 inline-flex min-w-40 justify-center rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">ارسال کد تایید</button>
                                    </div>
                                </div>

                                <div id="otpEntryPanel" class="hidden max-w-xl space-y-4">
                                    <div class="rounded-2xl bg-zinc-50 p-4 ring-1 ring-zinc-200">
                                        <p class="text-xs font-bold text-zinc-500">شماره تاییدشده برای دریافت کد</p>
                                        <p id="lockedPhone" class="mt-1 text-lg font-bold text-zinc-950" dir="ltr"></p>
                                    </div>
                                    <div>
                                        <label for="otp" class="block text-sm font-bold text-zinc-800">کد تایید</label>
                                        <input id="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9۰-۹٠-٩]*" placeholder="۱۲۳۴۵۶" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-center text-lg font-bold tracking-[0.3em] outline-none transition" dir="ltr" />
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">کد ارسال شده به <span id="displayPhone" class="font-bold text-zinc-900"></span></p>
                                        <p id="otpDebug" class="mt-2 hidden text-sm text-sky-700"></p>
                                        <p id="otpError" class="mt-2 hidden text-sm text-rose-600"></p>
                                    </div>
                                </div>

                                <div class="hidden rounded-2xl bg-zinc-50 p-3 ring-1 ring-zinc-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                        <span id="otpTimerText" class="font-bold text-zinc-700">زمان باقی‌مانده: <span id="otpCountdown" dir="ltr">۰۲:۰۰</span></span>
                                        <button id="resendOtp" type="button" class="hv-secondary-action rounded-xl px-3 py-2 text-xs font-bold transition disabled:cursor-not-allowed" disabled>ارسال مجدد کد</button>
                                    </div>
                                </div>

                                <p id="mobileError" class="hidden text-sm text-rose-600"></p>

                                <div id="otpActionPanel" class="hidden max-w-xl flex-wrap gap-3">
                                    <button id="verifyOtp" type="button" class="hv-primary-action inline-flex min-w-44 justify-center rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">تایید و شروع درخواست</button>
                                    <button id="backToPhone" type="button" class="hv-lift inline-flex min-w-32 justify-center rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">تغییر شماره</button>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step4">
                                <div class="flex flex-wrap items-end justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-bold text-indigo-600">انتخاب خروجی و مشاهده نتیجه</p>
                                        <h2 class="mt-2 text-xl font-bold text-zinc-950">خروجی موردنظر را انتخاب کنید</h2>
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">هر خروجی آماده شود همان لحظه نمایش داده می‌شود؛ می‌توانید خروجی دیگری از همین خدمت را هم به صف اضافه کنید.</p>
                                    </div>
                                </div>
                                <div id="outputChoicePanel" class="rounded-3xl bg-zinc-50 p-4 ring-1 ring-zinc-200">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-bold text-zinc-900">پیشنهادهای قابل ساخت</p>
                                            <p class="mt-1 text-xs leading-6 text-zinc-500">روی هر خروجی بزنید تا همان تصویر وارد صف شود؛ خروجی‌های دیگر تا زمان انتخاب خالی می‌مانند.</p>
                                        </div>
                                        <span id="outputChoiceCount" class="rounded-full bg-white px-3 py-1 text-xs font-bold text-zinc-500 ring-1 ring-zinc-200"></span>
                                    </div>
                                    <div id="outputOptions" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>
                                    <button id="confirmOutputRequest" type="button" class="hidden hv-primary-action mt-4 w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60" disabled>تایید و ساخت خروجی انتخاب‌شده</button>
                                </div>
                                <p id="generationError" class="hidden text-sm text-rose-600"></p>
                                <div id="resultsContainer" class="hidden grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4"></div>
                            </section>
                        </div>
                    </section>
                </div>
            </section>
        </main>

        <footer class="w-full bg-white/85 shadow-[0_-1px_20px_rgba(24,24,27,0.04)] backdrop-blur">
            <?php echo $landingTheme['footer_html']; ?>

        </footer>
    </div>

    <div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/85 p-4 backdrop-blur">
        <button id="closeImageModal" class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg transition hover:-translate-y-0.5" aria-label="بستن تصویر">
            <span class="fi fi-rr-cross-small h-4 w-4" aria-hidden="true"></span>
            <span>بستن</span>
        </button>
        <a id="downloadImageModal" class="absolute right-4 top-4 hidden items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg transition hover:-translate-y-0.5" href="#" download data-gallery-download>
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
    $generationFrontend = [
        'messages' => __('ai.generation.messages'),
        'statuses' => \App\Support\StateCatalog::options('generation.statuses'),
        'serviceVisuals' => [
            'hair_transplant' => $hairConsultationHref,
            'eyebrow_transplant' => $faceConsultationHref,
            'default' => $faceConsultationHref,
        ],
        'messageIntervalMs' => config('hairvision.generation.message_interval_ms'),
        'pollIntervalMs' => config('hairvision.generation.poll_interval_ms'),
        'softTimeoutMs' => config('hairvision.generation.soft_timeout_ms'),
        'maxWaitMs' => config('hairvision.generation.max_wait_ms'),
        'allowBackgroundCompletion' => config('hairvision.generation.allow_background_completion'),
        'pollTimeoutMs' => config('hairvision.generation.poll_timeout_ms'),
    ];
?>
<script>
    window.HAIRVISION_GENERATION_CONFIG = <?php echo json_encode($generationFrontend, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
</body>
</html>
