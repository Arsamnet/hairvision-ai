<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تایید ورود HairVision</title>
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
                <p class="text-sm font-bold text-teal-600">ورود امن</p>
                <h1 class="mt-1 text-2xl font-bold text-zinc-950">کد ورود را وارد کنید</h1>
                <p class="mt-4 text-sm leading-7 text-zinc-500">کد یکبارمصرف برای شماره <span class="font-bold text-zinc-900" dir="ltr">{{ $phone }}</span> ارسال شد.</p>
            </div>

            <div class="p-6">
                @if($errors->any())
                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{{ $errors->first() }}</div>
                @endif

                @if(! empty($debugSms))
                    <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-bold text-sky-900">
                        <span class="font-semibold">کد ورود آزمایشی:</span>
                        <span dir="ltr">{{ $debugSms }}</span>
                    </div>
                @endif

                <form id="adminOtpVerifyForm" method="POST" action="{{ route('admin.otp.verify') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $phone }}">
                    <div>
                        <label for="code" class="block text-sm font-bold text-zinc-800">کد تایید</label>
                        <input id="code" name="code" inputmode="numeric" data-digit-input autocomplete="one-time-code" maxlength="6" pattern="[0-9۰-۹٠-٩]*" placeholder="۱۲۳۴۵۶" class="mt-2 h-12 w-full rounded-lg border border-zinc-200 px-4 text-center text-lg font-bold tracking-[0.3em] outline-none transition focus:border-teal-500 focus:ring-4 focus:ring-teal-100" dir="ltr">
                    </div>
                    <x-admin.button type="submit" size="lg" block class="focus:ring-4 focus:ring-teal-100">ورود به داشبورد</x-admin.button>
                </form>

                <div class="mt-4 rounded-xl bg-zinc-50 p-3 ring-1 ring-zinc-200">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span id="adminOtpTimerText" class="font-bold text-zinc-700">زمان باقی‌مانده: <span id="adminOtpCountdown" dir="ltr">۰۵:۰۰</span></span>
                        <x-admin.button form="adminOtpResendForm" id="adminResendOtp" type="submit" variant="secondary" size="compact-sm" class="bg-white text-teal-700 ring-1 ring-teal-100 hover:bg-teal-50 disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-500" disabled>ارسال مجدد کد</x-admin.button>
                    </div>
                </div>

                <form id="adminOtpResendForm" method="POST" action="{{ route('admin.otp.request') }}" class="hidden">
                    @csrf
                    <input type="hidden" name="phone" value="{{ $phone }}">
                </form>

                <a href="{{ route('admin.login') }}" class="mt-5 block text-center text-sm font-bold text-zinc-500 hover:text-teal-700">تغییر شماره موبایل</a>
            </div>
        </section>
        </div>
    </main>
    <script>
        (() => {
            const expiresIn = Math.max(1, Number(@json($expiresIn ?? 300)) || 300);
            const countdown = document.getElementById('adminOtpCountdown');
            const timerText = document.getElementById('adminOtpTimerText');
            const resendButton = document.getElementById('adminResendOtp');
            const codeInput = document.getElementById('code');
            const verifyForm = document.getElementById('adminOtpVerifyForm');
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            const digitMap = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9'
            };

            const toPersianDigits = (value) => String(value).replace(/[0-9]/g, (digit) => persianDigits[Number(digit)]);
            const formatSeconds = (seconds) => {
                const safeSeconds = Math.max(0, Number(seconds) || 0);
                const minutes = Math.floor(safeSeconds / 60);
                const remainingSeconds = safeSeconds % 60;

                return toPersianDigits(`${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`);
            };

            const expiresAt = Date.now() + (expiresIn * 1000);
            const renderTimer = () => {
                const remainingSeconds = Math.ceil((expiresAt - Date.now()) / 1000);

                if (remainingSeconds <= 0) {
                    countdown.textContent = formatSeconds(0);
                    timerText.firstChild.textContent = 'کد منقضی شد. ';
                    resendButton.disabled = false;
                    resendButton.textContent = 'ارسال مجدد کد';
                    window.clearInterval(timer);

                    return;
                }

                countdown.textContent = formatSeconds(remainingSeconds);
                resendButton.textContent = 'ارسال مجدد کد';
            };

            codeInput?.addEventListener('beforeinput', (event) => {
                if (event.data && !/^[0-9۰-۹٠-٩]+$/u.test(event.data)) {
                    event.preventDefault();
                }
            });

            codeInput?.addEventListener('input', (event) => {
                const normalized = Array.from(event.target.value || '')
                    .map((char) => digitMap[char] ?? char)
                    .filter((char) => /[0-9]/.test(char))
                    .join('')
                    .slice(0, 6);

                event.target.value = toPersianDigits(normalized);

                if (normalized.length === 6) {
                    verifyForm.requestSubmit();
                }
            });

            const timer = window.setInterval(renderTimer, 1000);
            renderTimer();
        })();
    </script>
</body>
</html>
