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
                linear-gradient(120deg, #14b8a6, #22d3ee, #6366f1, #a855f7, #14b8a6) border-box;
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
                linear-gradient(120deg, #14b8a6, #22d3ee, #6366f1, #a855f7, #14b8a6) border-box;
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
    </style>
</head>
<body class="min-h-screen bg-[#eef2ff] font-sans text-zinc-900 antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="w-full bg-white/85 shadow-sm shadow-zinc-200/60 backdrop-blur">
            <?php echo $theme?->header_html ?? '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-5 sm:px-6"><div><p class="text-sm font-bold text-emerald-700">کلینیک کاشت مو</p><h1 class="mt-1 text-xl font-bold text-zinc-950">مشاوره و پیش نمایش اختصاصی کاشت مو</h1></div></div>'; ?>

        </header>

        <main class="w-full flex-1">
            <section class="relative isolate overflow-hidden px-4 py-6 sm:px-6 lg:py-8">
                <div class="absolute inset-0 -z-10 bg-[linear-gradient(135deg,#ecfeff_0%,#f8fafc_32%,#eef2ff_68%,#faf5ff_100%)]"></div>

                <div id="landingApp" data-preparing-message="<?php echo e(e(config('hairvision.visitor_messages.preparing_image'))); ?>" class="mx-auto grid w-full max-w-6xl gap-5 lg:grid-cols-[.86fr_1.14fr]">
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
                                    <p class="text-sm font-bold text-emerald-300">پیش نمایش اختصاصی</p>
                                    <h2 class="mt-3 text-2xl font-bold leading-9 text-white">تصویر خود را ثبت کنید و نمای زمانی رشد مو را ببینید.</h2>
                                    <p class="mt-4 text-sm leading-8 text-zinc-200">فرآیند کوتاه است: تایید شماره، بارگذاری تصویر و دریافت چهار نمای قابل مشاهده.</p>
                                </div>
                            </div>

                            <div class="grid gap-2 text-sm text-zinc-200">
                                <div class="hv-lift rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                                    <p class="font-bold text-white">همراهی تا مشاوره</p>
                                    <p class="mt-2 leading-7">اطلاعات شما به تیم کلینیک کمک می‌کند با آمادگی بهتر و پیشنهاد دقیق‌تر کنارتان باشد.</p>
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
                                    <span class="block font-bold">موبایل</span>
                                    <span class="text-xs text-white/70">ارسال کد</span>
                                </button>
                                <button type="button" data-step-indicator="2" class="step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500">۲</span>
                                    <span class="block font-bold">تایید</span>
                                    <span class="text-xs text-zinc-500">ثبت کد</span>
                                </button>
                                <button type="button" data-step-indicator="3" class="step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500">۳</span>
                                    <span class="block font-bold">تصویر</span>
                                    <span class="text-xs text-zinc-500">بارگذاری</span>
                                </button>
                                <button type="button" data-step-indicator="4" class="step-indicator hv-step hv-step-upcoming rounded-2xl px-3 py-3 text-right text-sm transition">
                                    <span class="step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500">۴</span>
                                    <span class="block font-bold">نتیجه</span>
                                    <span class="text-xs text-zinc-500">مشاهده</span>
                                </button>
                                </div>
                            </div>

                            <section class="flow-step hv-enter space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step1">
                                <div>
                                    <p class="text-sm font-bold text-indigo-600">شروع</p>
                                    <h2 class="mt-2 text-xl font-bold text-zinc-950">شماره موبایل خود را وارد کنید</h2>
                                </div>
                                <div>
                                    <label for="phone" class="block text-sm font-bold text-zinc-800">شماره موبایل</label>
                                    <input id="phone" type="text" inputmode="numeric" autocomplete="tel" maxlength="11" pattern="[0-9۰-۹٠-٩]*" placeholder="۰۹۱۲۳۴۵۶۷۸۹" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-right text-base outline-none transition" />
                                    <p id="phoneError" class="mt-2 hidden text-sm text-rose-600"></p>
                                </div>
                                <button id="requestOtp" class="hv-primary-action w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-60">ارسال کد تایید</button>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step2">
                                <div>
                                    <p class="text-sm font-bold text-indigo-600">تایید شماره</p>
                                    <h2 class="mt-2 text-xl font-bold text-zinc-950">کد تایید را وارد کنید</h2>
                                    <p class="mt-2 text-sm leading-7 text-zinc-500">کد ارسال شده به <span id="displayPhone" class="font-bold text-zinc-900"></span></p>
                                </div>
                                <div>
                                    <label for="otp" class="block text-sm font-bold text-zinc-800">کد تایید</label>
                                    <input id="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9۰-۹٠-٩]*" placeholder="۱۲۳۴۵۶" class="hv-ai-input mt-2 w-full rounded-2xl px-4 py-3 text-right text-base outline-none transition" />
                                    <p id="otpError" class="mt-2 hidden text-sm text-rose-600"></p>
                                </div>
                                <div class="grid gap-3 lg:grid-cols-2">
                                    <button id="verifyOtp" class="hv-primary-action w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5">تایید و ادامه</button>
                                    <button id="backToPhone" class="hv-lift w-full rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">تغییر شماره</button>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step3">
                                <div>
                                    <p class="text-sm font-bold text-indigo-600">بارگذاری تصویر</p>
                                    <h2 class="mt-2 text-xl font-bold text-zinc-950">تصویر خود را انتخاب کنید</h2>
                                </div>

                                <div class="grid gap-4 lg:grid-cols-[.9fr_1.1fr]">
                                    <label for="image" class="hv-upload-zone group relative flex min-h-56 cursor-pointer flex-col items-center justify-center overflow-hidden rounded-3xl bg-white p-4 text-center ring-1 ring-zinc-200 transition hover:bg-indigo-50/60 hover:ring-indigo-200">
                                        <input id="image" type="file" accept="image/*" class="sr-only" />
                                        <img id="imagePreview" alt="پیش نمایش تصویر" class="hidden h-full max-h-72 w-full rounded-2xl object-cover" />
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
                                                <p id="castMessage" class="mt-3 min-h-14 text-sm leading-7 text-zinc-700"></p>
                                            </div>
                                        </div>
                                        <div class="grid gap-3 lg:grid-cols-2">
                                            <button id="upload" class="hv-primary-action w-full rounded-2xl px-5 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5">دریافت پیش نمایش</button>
                                            <button id="backToVerify" class="hv-lift w-full rounded-2xl bg-white px-5 py-3 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">بازگشت</button>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="flow-step hidden space-y-5 rounded-3xl bg-white p-5 shadow-xl shadow-indigo-100/60" id="step4">
                                <div class="flex flex-wrap items-end justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-bold text-indigo-600">پیش نمایش آماده شد</p>
                                        <h2 class="mt-2 text-xl font-bold text-zinc-950">نتایج شما</h2>
                                        <p class="mt-2 text-sm leading-7 text-zinc-500">برای بزرگنمایی روی هر تصویر کلیک کنید.</p>
                                    </div>
                                    <button id="newSession" class="hv-lift rounded-2xl bg-white px-4 py-2 text-sm font-bold text-zinc-700 ring-1 ring-zinc-200">ثبت تصویر جدید</button>
                                </div>
                                <div id="resultsContainer" class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4"></div>
                            </section>
                        </div>
                    </section>
                </div>
            </section>
        </main>

        <footer class="w-full bg-white/85 shadow-[0_-1px_20px_rgba(24,24,27,0.04)] backdrop-blur">
            <?php echo $theme?->footer_html ?? '<div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-5 text-sm text-zinc-500 sm:px-6"><p>یک پیش‌نمایش الهام‌بخش برای شروع گفت‌وگوی دقیق‌تر با کلینیک.</p><p class="flex items-center gap-2"><span>قدرت گرفته از</span><span class="grid h-7 w-7 place-items-center rounded-xl bg-linear-to-br from-emerald-500 via-indigo-500 to-fuchsia-500"><img src="/theme/hairvision-icon.svg" alt="" class="h-5 w-5"></span></p></div>'; ?>

        </footer>
    </div>

    <div id="imageModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-950/85 p-4 backdrop-blur">
        <button id="closeImageModal" class="absolute left-4 top-4 inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-bold text-zinc-950 shadow-lg transition hover:-translate-y-0.5">
            <span class="fi fi-rr-x h-4 w-4" aria-hidden="true"></span>
            <span>بستن</span>
        </button>
        <button id="prevImageModal" class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-xl font-bold text-zinc-950 shadow-lg transition hover:scale-105 sm:right-8" aria-label="تصویر قبلی">
            <span class="fi fi-rr-arrow-small-right h-5 w-5" aria-hidden="true"></span>
        </button>
        <figure class="w-full max-w-5xl">
            <img id="modalImage" alt="نمای بزرگ تصویر" class="hv-modal-image mx-auto max-h-[82vh] max-w-full rounded-3xl object-contain shadow-2xl" />
            <figcaption id="modalCaption" class="mt-4 text-center text-sm font-bold text-white"></figcaption>
        </figure>
        <button id="nextImageModal" class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/95 text-xl font-bold text-zinc-950 shadow-lg transition hover:scale-105 sm:left-8" aria-label="تصویر بعدی">
            <span class="fi fi-rr-arrow-small-left h-5 w-5" aria-hidden="true"></span>
        </button>
    </div>

    <script>
        const PREPARING_MESSAGE = document.getElementById('landingApp')?.dataset.preparingMessage || 'تصویر شما در حال آماده سازی است.';
        const STATE = { phone: null, verified: false, uploadId: null, generationGroupId: null, originalPreviewUrl: null, galleryItems: [], activeGalleryIndex: 0 };
        const CAST_MESSAGES = [
            PREPARING_MESSAGE,
            'کیفیت تصویر بررسی شد و برای پیش نمایش مناسب است.',
            'نمای ۳ ماهه در حال آماده سازی است.',
            'نمای ۶ ماهه در حال آماده سازی است.',
            'نمای ۱۲ ماهه در حال آماده سازی است.',
            'چیدمان نهایی تصاویر در حال انجام است.'
        ];
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
                        marker.innerHTML = done
                            ? '<span class="fi fi-rr-check h-4 w-4" aria-hidden="true"></span>'
                            : toPersianDigits(index);
                        marker.className = active
                            ? 'step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white/15 text-xs font-bold text-white'
                            : done
                                ? 'step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-emerald-700 text-xs font-bold text-white'
                                : 'step-marker mb-2 grid h-7 w-7 place-items-center rounded-xl bg-white text-xs font-bold text-zinc-500';
                    }

                    const textSpans = el.querySelectorAll('span');
                    const title = textSpans[1];
                    const caption = textSpans[2];
                    if (title) title.className = 'block font-bold';
                    if (caption) {
                        caption.className = active ? 'text-xs text-white/70' : done ? 'text-xs text-emerald-700/75' : 'text-xs text-zinc-500';
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
            showStatus(message) {
                document.getElementById('statusBox').classList.remove('hidden');
                document.getElementById('castMessage').textContent = message;
            },
            clearStatus() {
                document.getElementById('castMessage').textContent = '';
                document.getElementById('statusBox').classList.add('hidden');
            },
        };

        const API = {
            async requestOtp(phone) {
                const response = await fetch('/api/v1/auth/otp/request', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone }),
                    credentials: 'same-origin',
                });
                return response.json();
            },
            async verifyOtp(phone, code) {
                const response = await fetch('/api/v1/auth/otp/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone, code }),
                    credentials: 'same-origin',
                });
                return response.json();
            },
            async uploadImage(file, consent) {
                const formData = new FormData();
                formData.append('image', file);
                formData.append('consent', consent ? '1' : '0');
                const response = await fetch('/api/v1/uploads', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });
                return response.json();
            },
            async createGeneration(uploadId) {
                const response = await fetch('/api/v1/generations', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ upload_id: uploadId }),
                    credentials: 'same-origin',
                });
                return response.json();
            },
            async getGenerationStatus(groupId) {
                const response = await fetch(`/api/v1/generations/${groupId}/status`, { credentials: 'same-origin' });
                return response.json();
            },
            async getGenerationResults(groupId) {
                const response = await fetch(`/api/v1/generations/${groupId}/results`, { credentials: 'same-origin' });
                return response.json();
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
        });

        document.getElementById('requestOtp').addEventListener('click', async () => {
            const phone = normalizeDigits(document.getElementById('phone').value);
            UI.clearError('phoneError');
            if (phone.length !== 11) return UI.showError('phoneError', 'شماره موبایل باید ۱۱ رقم باشد.');
            const btn = document.getElementById('requestOtp');
            if (btn.disabled) return;
            btn.disabled = true;
            btn.textContent = 'در حال ارسال...';
            const result = await API.requestOtp(phone);
            btn.disabled = false;
            btn.textContent = 'ارسال کد تایید';
            if (result.debug_sms) console.debug('Mock SMS:', result.debug_sms);
            if (result.success) {
                STATE.phone = phone;
                sessionStorage.setItem('hairvisionPhone', phone);
                document.getElementById('displayPhone').textContent = toPersianDigits(phone);
                UI.showStep(2);
                document.getElementById('otp').focus();
            } else {
                UI.showError('phoneError', result.message || 'ارسال کد تایید ناموفق بود.');
            }
        });

        document.getElementById('verifyOtp').addEventListener('click', async () => {
            const code = normalizeDigits(document.getElementById('otp').value);
            UI.clearError('otpError');
            if (!code || code.length !== 6) return UI.showError('otpError', 'کد ۶ رقمی را وارد کنید.');
            const btn = document.getElementById('verifyOtp');
            if (btn.disabled) return;
            btn.disabled = true;
            btn.textContent = 'در حال تایید...';
            const result = await API.verifyOtp(STATE.phone, code);
            btn.disabled = false;
            btn.textContent = 'تایید و ادامه';
            if (result.success) {
                STATE.verified = true;
                sessionStorage.setItem('hairvisionPhone', STATE.phone);
                sessionStorage.setItem('hairvisionVerified', '1');
                UI.showStep(3);
            } else {
                UI.showError('otpError', result.message || 'تایید کد ناموفق بود.');
            }
        });

        document.getElementById('backToPhone').addEventListener('click', () => {
            document.getElementById('otp').value = '';
            UI.clearError('otpError');
            UI.showStep(1);
        });

        document.getElementById('upload').addEventListener('click', async () => {
            const fileInput = document.getElementById('image');
            const consent = document.getElementById('consent').checked;
            UI.clearError('uploadError');
            if (!fileInput.files.length) return UI.showError('uploadError', 'لطفا یک تصویر انتخاب کنید.');
            if (!consent) return UI.showError('uploadError', 'برای ادامه باید رضایت پردازش تصویر را تایید کنید.');

            const btn = document.getElementById('upload');
            btn.disabled = true;
            btn.textContent = 'در حال ارسال...';
            await playCastMessages([CAST_MESSAGES[0]]);

            const result = await API.uploadImage(fileInput.files[0], consent);
            btn.disabled = false;
            btn.textContent = 'دریافت پیش نمایش';
            if (result.success && result.upload?.id) {
                STATE.uploadId = result.upload.id;
                const genResult = await API.createGeneration(STATE.uploadId);
                if (genResult.success && genResult.generation_group?.id) {
                    STATE.generationGroupId = genResult.generation_group.id;
                    const completedNow = genResult.generation_group.status === 'completed'
                        || (Array.isArray(genResult.jobs) && genResult.jobs.every((job) => job.status === 'completed'));

                    if (completedNow) {
                        await playCastMessages(CAST_MESSAGES.slice(1), 900);
                        await showResultsNow();
                    } else {
                        await pollUntilReady();
                    }
                } else {
                    UI.clearStatus();
                    UI.showError('uploadError', 'ساخت نتیجه ناموفق بود.');
                }
            } else {
                UI.clearStatus();
                UI.showError('uploadError', result.message || 'بارگذاری تصویر ناموفق بود.');
            }
        });

        document.getElementById('backToVerify').addEventListener('click', () => {
            resetImageInput();
            UI.clearError('uploadError');
            UI.clearStatus();
            UI.showStep(2);
        });

        document.getElementById('newSession').addEventListener('click', () => {
            STATE.uploadId = null;
            STATE.generationGroupId = null;
            document.getElementById('otp').value = '';
            resetImageInput();
            UI.clearError('phoneError');
            UI.clearError('otpError');
            UI.clearError('uploadError');
            UI.clearStatus();
            document.getElementById('resultsContainer').innerHTML = '';
            if (STATE.verified) {
                UI.showStep(3);
            } else if (STATE.phone) {
                document.getElementById('displayPhone').textContent = toPersianDigits(STATE.phone);
                UI.showStep(2);
            } else {
                UI.showStep(1);
            }
        });

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

        async function showResultsNow() {
            const resultData = await API.getGenerationResults(STATE.generationGroupId);
            if (resultData.success && resultData.results) {
                UI.clearStatus();
                displayResults(resultData.results);
                UI.showStep(4);
            } else {
                UI.clearStatus();
                UI.showError('uploadError', resultData.message || 'دریافت نتایج ناموفق بود.');
            }
        }

        async function pollUntilReady() {
            let attempts = 0;
            const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
            while (attempts < 60) {
                attempts++;
                await playCastMessages([CAST_MESSAGES[attempts % CAST_MESSAGES.length]], 750);
                const statusResult = await API.getGenerationStatus(STATE.generationGroupId);
                if (statusResult.status === 'completed') {
                    await playCastMessages(['نتایج آماده شدند و در حال نمایش هستند.'], 750);
                    await showResultsNow();
                    return;
                }
                await wait(700);
            }
            UI.clearStatus();
            UI.showError('uploadError', 'آماده سازی تصاویر بیشتر از حد انتظار طول کشید. لطفا دوباره تلاش کنید.');
        }

        async function playCastMessages(messages, delay = 850) {
            const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
            for (const message of messages) {
                UI.showStatus(message);
                await wait(delay);
            }
        }

        function displayResults(results) {
            const container = document.getElementById('resultsContainer');
            container.innerHTML = '';
            const items = [
                { period: 'original', label: 'تصویر اولیه', caption: 'تصویر بارگذاری شده', url: STATE.originalPreviewUrl },
                { period: '3_months', label: '۳ ماه', caption: 'نمای آغاز مسیر', url: results.find((r) => r.period === '3_months')?.url },
                { period: '6_months', label: '۶ ماه', caption: 'نمای میانی', url: results.find((r) => r.period === '6_months')?.url },
                { period: '12_months', label: '۱۲ ماه', caption: 'نمای کامل تر', url: results.find((r) => r.period === '12_months')?.url },
            ];
            STATE.galleryItems = items.filter((item) => item.url);

            items.forEach((item, index) => {
                const card = document.createElement('article');
                card.className = 'hv-result-card hv-enter overflow-hidden rounded-3xl bg-white shadow-lg shadow-indigo-100/70';
                card.style.animationDelay = `${index * 70}ms`;
                const galleryIndex = STATE.galleryItems.findIndex((galleryItem) => galleryItem.period === item.period);
                card.innerHTML = item.url
                    ? `<button type="button" class="block w-full text-right" data-gallery-index="${galleryIndex}"><span class="relative block overflow-hidden"><img src="${item.url}" alt="${item.label}" class="hv-result-image aspect-[4/5] w-full object-cover" loading="lazy" /><span class="absolute left-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-white/95 text-zinc-950 shadow-lg"><span class="fi fi-rr-zoom-in h-4 w-4" aria-hidden="true"></span></span></span><span class="block p-4"><span class="block text-base font-bold text-zinc-950">${item.label}</span><span class="mt-1 block text-sm leading-6 text-zinc-500">${item.caption}</span></span></button>`
                    : `<div class="aspect-[4/5] w-full bg-zinc-100"></div><div class="p-4"><p class="text-base font-bold text-zinc-950">${item.label}</p><p class="mt-1 text-sm leading-6 text-zinc-500">تصویر آماده نشد.</p></div>`;
                container.appendChild(card);
            });

            container.querySelectorAll('[data-gallery-index]').forEach((button) => {
                button.addEventListener('click', () => openImageModal(Number(button.dataset.galleryIndex)));
            });
        }

        function openImageModal(index) {
            STATE.activeGalleryIndex = Number.isFinite(index) ? index : 0;
            const modal = document.getElementById('imageModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            renderModalImage(false);
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('modalImage').src = '';
            document.getElementById('modalCaption').textContent = '';
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
            const caption = document.getElementById('modalCaption');

            const applyImage = () => {
                image.src = item.url;
                image.alt = item.label || 'نمای بزرگ تصویر';
                caption.textContent = `${item.label} - ${item.caption}`;
                image.classList.remove('is-changing');
            };

            if (!animate) {
                applyImage();
                return;
            }

            image.classList.add('is-changing');
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
        }

        function loadSessionState() {
            const storedPhone = sessionStorage.getItem('hairvisionPhone');
            const storedVerified = sessionStorage.getItem('hairvisionVerified') === '1';
            if (storedPhone) {
                STATE.phone = normalizeDigits(storedPhone).slice(0, 11);
                document.getElementById('phone').value = toPersianDigits(STATE.phone);
                document.getElementById('displayPhone').textContent = toPersianDigits(STATE.phone);
            }
            if (storedVerified) {
                STATE.verified = true;
                UI.showStep(3);
            } else if (storedPhone) {
                UI.showStep(2);
            } else {
                UI.showStep(1);
            }
        }

        loadSessionState();
    </script>
</body>
</html>
