export function bootLanding() {
    const app = document.getElementById('landingApp');

    if (!app) return;

    const GENERATION = window.HAIRVISION_GENERATION_CONFIG || {};

    const STORAGE_KEYS = {
        phone: 'hairvisionPhone',
        verified: 'hairvisionVerified',
        activeGenerationIds: 'hairvisionActiveGenerationIds',
    };

    const STATE = {
        phone: null,
        verified: false,
        categories: [],
        selectedCategoryId: null,
        selectedOutputId: null,
        sourceImageId: null,
        originalPreviewUrl: null,
        galleryItems: [],
        activeGalleryIndex: 0,
        generations: [],
        currentMessage: '',
        messageIndex: 0,
        messageTimer: null,
        generationComplete: false,
        awaitingOutputSelection: false,
        otpExpiresAt: null,
        otpTimer: null,
        otpRequestInFlight: false,
        hasRequestedAnyOutput: false,
        pendingOutputIds: new Set(),
        sourceImageFromProfile: false,
        serverSessionVerified: false,
    };

    const DIGIT_MAP = {
        '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
        '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
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

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = value ?? '';

        return element.innerHTML;
    }

    function wait(ms) {
        return new Promise((resolve) => setTimeout(resolve, ms));
    }

    function selectedCategory() {
        return STATE.categories.find((category) => category.id === STATE.selectedCategoryId) || null;
    }

    function selectedOutputs() {
        const output = selectedOutput();

        return output ? [output] : [];
    }

    function categoryOutputs() {
        return (selectedCategory()?.outputs || []).filter((output) => output?.id);
    }

    function selectedOutput() {
        const outputs = categoryOutputs();

        if (!outputs.length) return null;

        return outputs.find((output) => output.id === STATE.selectedOutputId) || null;
    }

    function outputCountLabel(count) {
        return `${toPersianDigits(count)} خروجی`;
    }

    function serviceVisual(category) {
        return category?.card_image_url || GENERATION.serviceVisuals?.[category?.code] || GENERATION.serviceVisuals?.default || '';
    }

    function iconClass(icon) {
        const safe = String(icon || 'sparkles').replace(/^fi-rr-/, '').replace(/[^a-z0-9-]/gi, '');

        return `fi fi-rr-${safe || 'sparkles'} h-6 w-6`;
    }

    function statusLabel(status) {
        return GENERATION.statuses?.[status] || status || '';
    }

    function completionMessage() {
        return GENERATION.messages?.completed?.[0] || 'پیش‌نمایش شما آماده شد.';
    }

    function failedMessage() {
        return GENERATION.messages?.failed?.[0] || 'ساخت پیش‌نمایش ناموفق بود. لطفاً دوباره تلاش کنید.';
    }

    function backgroundContinueMessage() {
        return 'درخواست‌ها ثبت شده‌اند و پردازش در پس‌زمینه ادامه دارد. می‌توانید چند دقیقه بعد دوباره همین صفحه را بررسی کنید.';
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

    const API = {
        bootstrap: () => requestJson('/api/v1/bootstrap'),
        profile: () => requestJson('/api/v1/profile'),
        profileCategories: () => requestJson('/api/v1/profile/categories'),
        profileGenerations: () => requestJson('/api/v1/profile/generations'),
        requestOtp: (phone) => requestJson('/api/v1/auth/otp/request', {
            method: 'POST',
            body: JSON.stringify({ phone }),
        }),
        verifyOtp: (phone, code) => requestJson('/api/v1/auth/otp/verify', {
            method: 'POST',
            body: JSON.stringify({ phone, code }),
        }),
        uploadSourceImage(file, consent) {
            const formData = new FormData();
            formData.append('image', file);
            formData.append('consent', consent ? '1' : '0');

            return requestJson('/api/v1/profile/source-image', {
                method: 'POST',
                body: formData,
            });
        },
        createGeneration(sourceImageId, categoryId, outputId) {
            return requestJson('/api/v1/generations', {
                method: 'POST',
                body: JSON.stringify({
                    source_image_id: sourceImageId,
                    category_id: categoryId,
                    output_id: outputId,
                    idempotency_key: newIdempotencyKey(),
                }),
            });
        },
        getGeneration: (generationId) => requestJson(`/api/v1/generations/${generationId}`),
    };

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
                const caption = textSpans[2];

                if (textSpans[1]) textSpans[1].className = 'block font-bold';
                if (caption) {
                    caption.className = active
                        ? 'text-xs text-white/70'
                        : done ? 'text-xs text-emerald-700/75' : 'text-xs text-zinc-500';
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
            document.getElementById('statusBox')?.classList.remove('hidden');
            document.getElementById('resultsStatusBox')?.classList.remove('hidden');
            const castMessage = document.getElementById('castMessage');
            const resultsCastMessage = document.getElementById('resultsCastMessage');
            if (castMessage) castMessage.textContent = STATE.currentMessage;
            if (resultsCastMessage) resultsCastMessage.textContent = STATE.currentMessage;
            this.showModalToast(STATE.currentMessage);
        },
        clearStatus() {
            const castMessage = document.getElementById('castMessage');
            const resultsCastMessage = document.getElementById('resultsCastMessage');
            if (castMessage) castMessage.textContent = '';
            if (resultsCastMessage) resultsCastMessage.textContent = '';
            document.getElementById('statusBox')?.classList.add('hidden');
            document.getElementById('resultsStatusBox')?.classList.add('hidden');
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

    function bindDigitOnlyInput(input, maxLength, onComplete = null) {
        input.addEventListener('beforeinput', (event) => {
            if (event.data && !SUPPORTED_DIGIT_PATTERN.test(event.data)) event.preventDefault();
        });

        input.addEventListener('input', (event) => {
            const normalized = normalizeDigits(event.target.value).slice(0, maxLength);
            event.target.value = toPersianDigits(normalized);

            if (normalized.length === maxLength && typeof onComplete === 'function') {
                onComplete(normalized);
            }
        });
    }

    function formatSeconds(seconds) {
        const safeSeconds = Math.max(0, Number(seconds) || 0);
        const minutes = Math.floor(safeSeconds / 60);
        const remainingSeconds = safeSeconds % 60;

        return toPersianDigits(`${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`);
    }

    function stopOtpTimer() {
        if (STATE.otpTimer) clearInterval(STATE.otpTimer);
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

    function setMobileMode(mode) {
        const phonePanel = document.getElementById('phoneEntryPanel');
        const otpPanel = document.getElementById('otpEntryPanel');
        const otpActions = document.getElementById('otpActionPanel');
        const otpTimer = document.getElementById('otpTimerText')?.closest('.rounded-2xl');
        const lockedPhone = document.getElementById('lockedPhone');

        const isOtp = mode === 'otp';
        phonePanel?.classList.toggle('hidden', isOtp);
        otpPanel?.classList.toggle('hidden', !isOtp);
        otpActions?.classList.toggle('hidden', !isOtp);
        otpActions?.classList.toggle('flex', isOtp);
        otpTimer?.classList.toggle('hidden', !isOtp);

        if (lockedPhone) {
            lockedPhone.textContent = STATE.phone ? toPersianDigits(STATE.phone) : '';
        }
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

    function newIdempotencyKey() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
            const random = Math.random() * 16 | 0;
            const value = char === 'x' ? random : (random & 0x3 | 0x8);

            return value.toString(16);
        });
    }

    function categoriesFromPayload(payload) {
        const collection = payload?.categories?.data ?? payload?.data ?? payload?.categories ?? [];

        return Array.isArray(collection)
            ? collection
                .map((category) => ({ ...category, outputs: Array.isArray(category.outputs) ? category.outputs : [] }))
                .filter((category) => category.id && category.outputs.length > 0)
            : [];
    }

    function applyCategories(categories) {
        STATE.categories = categories;

        if (!STATE.categories.some((category) => category.id === STATE.selectedCategoryId)) {
            STATE.selectedCategoryId = STATE.categories[0]?.id || null;
        }

        ensureSelectedOutput();
        renderCategoryOptions();
        renderOutputOptions();
        renderStagePanels(STATE.generations);
        primeGallery();
    }

    function ensureSelectedOutput() {
        const outputs = categoryOutputs();

        if (outputs.length === 1) {
            STATE.selectedOutputId = outputs[0].id;
            return;
        }

        if (outputs.length === 0 || !outputs.some((output) => output.id === STATE.selectedOutputId)) {
            STATE.selectedOutputId = null;
        }
    }

    function renderCategoryOptions() {
        const container = document.getElementById('categoryOptions');
        const emptyPanel = document.getElementById('noApplicationsPanel');
        const continueButton = document.getElementById('continueToImage');
        if (!container || !emptyPanel || !continueButton) return;

        container.innerHTML = '';
        emptyPanel.classList.toggle('hidden', STATE.categories.length > 0);
        continueButton.disabled = STATE.categories.length === 0;

        STATE.categories.forEach((category) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.categoryOption = category.id;
            button.className = `hv-treatment-card rounded-2xl p-3 text-right ${category.id === STATE.selectedCategoryId ? 'is-selected' : ''}`;
            const visual = serviceVisual(category);
            const media = visual
                ? `<span class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-50 ring-1 ring-indigo-100"><img src="${escapeHtml(visual)}" alt="" class="hv-service-visual h-9 w-9" loading="lazy" /></span>`
                : '';
            button.innerHTML = `
                <span class="flex items-start gap-3">
                    ${media || `<span class="hv-treatment-icon grid h-12 w-12 shrink-0 place-items-center rounded-2xl text-2xl text-white shadow-lg shadow-indigo-100">
                    <span class="${iconClass(category.icon)}" aria-hidden="true"></span>
                </span>`}
                    <span class="min-w-0">
                        <span class="block text-base font-bold text-zinc-950">${escapeHtml(category.name)}</span>
                        <span class="mt-1 line-clamp-2 block text-xs leading-6 text-zinc-600">${escapeHtml(category.description || 'پیش‌نمایش فعال این کلینیک')}</span>
                    </span>
                </span>
                <span class="mt-3 inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">${escapeHtml(outputCountLabel(category.outputs.length))}</span>
            `;
            button.addEventListener('click', () => {
                STATE.selectedCategoryId = category.id;
                STATE.awaitingOutputSelection = false;
                ensureSelectedOutput();
                resetGenerationProgress();
                UI.clearError('treatmentError');
                renderCategoryOptions();
                renderOutputOptions();
                renderStagePanels([]);
                UI.showStep(2);
            });
            container.appendChild(button);
        });
    }

    function renderOutputOptions() {
        const panel = document.getElementById('outputChoicePanel');
        const container = document.getElementById('outputOptions');
        const count = document.getElementById('outputChoiceCount');
        const confirmButton = document.getElementById('confirmOutputRequest');
        if (!panel || !container || !count) return;

        const outputs = categoryOutputs();
        panel.classList.toggle('hidden', outputs.length === 0);
        count.textContent = outputCountLabel(outputs.length);
        container.innerHTML = '';

        if (confirmButton) {
            const selectedGeneration = selectedOutput() ? generationForOutput(selectedOutput()) : null;
            const selectedPending = selectedOutput() ? STATE.pendingOutputIds.has(selectedOutput().id) : false;
            confirmButton.classList.add('hidden');
            confirmButton.disabled = !selectedOutput() || selectedPending || isNonTerminalGeneration(selectedGeneration) || selectedGeneration?.status === 'completed';
            confirmButton.textContent = selectedGeneration?.status === 'completed'
                ? 'این خروجی آماده است'
                : selectedPending || isNonTerminalGeneration(selectedGeneration)
                    ? 'این خروجی در حال پردازش است'
                    : 'تایید و ساخت خروجی انتخاب‌شده';
        }

        outputs.forEach((output) => {
            const generation = generationForOutput(output);
            const pending = STATE.pendingOutputIds.has(output.id);
            const requested = pending || Boolean(generation?.id);
            const nonTerminal = pending || isNonTerminalGeneration(generation);
            const completed = generation?.status === 'completed';
            const selected = output.id === STATE.selectedOutputId;
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.outputOption = output.id;
            button.className = selected
                ? 'cursor-pointer rounded-2xl bg-zinc-950 p-3 text-right text-white shadow-lg shadow-zinc-200 transition hover:-translate-y-0.5'
                : 'cursor-pointer rounded-2xl bg-white p-3 text-right text-zinc-800 ring-1 ring-zinc-200 transition hover:-translate-y-0.5 hover:ring-indigo-200';
            const badge = completed
                ? statusLabel('completed')
                : pending ? statusLabel('queued') : nonTerminal ? statusLabel(generation.status) : requested ? statusLabel(generation.status) : 'قابل درخواست';
            button.innerHTML = `
                <span class="block text-sm font-bold">${escapeHtml(output.name)}</span>
                <span class="mt-1 line-clamp-2 block text-xs leading-6 ${selected ? 'text-white/75' : 'text-zinc-500'}">${escapeHtml(output.description || '')}</span>
                <span class="mt-3 inline-flex rounded-full ${selected ? 'bg-white/15 text-white' : completed ? 'bg-emerald-50 text-emerald-700' : nonTerminal ? 'bg-indigo-50 text-indigo-700' : 'bg-zinc-100 text-zinc-600'} px-2 py-1 text-xs font-bold">${escapeHtml(badge)}</span>
            `;
            button.addEventListener('click', async () => {
                STATE.selectedOutputId = output.id;
                UI.clearError('treatmentError');
                UI.clearError('generationError');
                renderOutputOptions();
                renderProductionTable();

                if (!STATE.sourceImageId) {
                    primeGallery();
                    return;
                }

                await requestSelectedOutput();
            });
            container.appendChild(button);
        });
    }

    function clearActiveGeneration() {
        sessionStorage.removeItem(STORAGE_KEYS.activeGenerationIds);
    }

    function saveActiveGenerations() {
        const ids = STATE.generations.map((generation) => generation.id).filter(Boolean);
        if (!ids.length) return clearActiveGeneration();
        sessionStorage.setItem(STORAGE_KEYS.activeGenerationIds, JSON.stringify(ids));
    }

    function handleApiFailure(payload, errorId = 'uploadError') {
        const message = payload?.requiresVerification
            ? 'نشست تایید شما معتبر نیست. لطفاً دوباره کد تایید دریافت کنید.'
            : (payload?.message || friendlyApiMessage(payload?.status));

        stopStageMessages();
        UI.clearStatus();
        UI.showError(errorId, message);

        const actionButton = document.getElementById('verifyOtp');
        if (actionButton) {
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع درخواست';
        }

        if (payload?.requiresVerification) {
            STATE.verified = false;
            stopOtpTimer();
            sessionStorage.removeItem(STORAGE_KEYS.verified);
            document.getElementById('otp').value = '';
            UI.clearError('otpError');
            UI.clearDebugOtp();
            UI.showStep(3);

            if (STATE.phone) {
                setMobileMode('otp');
                markOtpExpired();
                document.getElementById('displayPhone').textContent = toPersianDigits(STATE.phone);
                UI.showError('otpError', message);
                document.getElementById('otp').focus();
            } else {
                setMobileMode('phone');
                UI.showError('phoneError', message);
                document.getElementById('requestOtp').focus();
            }
        }
    }

    function latestGenerationForOutput(output, jobs = STATE.generations) {
        if (!output) return null;

        return [...(jobs || [])].reverse().find((item) => item.output?.id === output.id || item.output?.code === output.code) || null;
    }

    function generationForOutput(output) {
        return latestGenerationForOutput(output);
    }

    function isNonTerminalGeneration(generation) {
        return ['queued', 'dispatched', 'processing'].includes(generation?.status);
    }

    function mergeGenerations(current, incoming) {
        const map = new Map();
        [...(current || []), ...(incoming || [])].filter(Boolean).forEach((generation) => {
            map.set(generation.id, generation);
        });

        return Array.from(map.values());
    }

    async function refreshVerifiedCategories() {
        const result = await API.profileCategories();
        if (result.success === false) return;
        applyCategories(categoriesFromPayload(result));
    }

    async function refreshActiveGenerations() {
        const refreshed = [];

        for (const generation of STATE.generations) {
            if (!generation.id) continue;

            const result = await API.getGeneration(generation.id);
            if (result.success === false) return result;
            refreshed.push(result.data);
        }

        STATE.generations = mergeGenerations(STATE.generations, refreshed);

        return { success: true, data: STATE.generations };
    }

    function startStatusMessages(force = false) {
        const messages = [
            'درخواست پیش‌نمایش ثبت شد و وضعیت هر خروجی جداگانه به‌روزرسانی می‌شود.',
            'پردازش تصویر ممکن است کمی زمان ببرد. همین صفحه وضعیت نتیجه‌ها را نگه می‌دارد.',
            'هر خروجی فعال کلینیک مستقل ساخته می‌شود و آماده شدن یکی وابسته به دیگری نیست.',
        ];

        if (!force && STATE.messageTimer) return;

        stopStageMessages();
        STATE.messageIndex = 0;
        UI.showStatus(messages[0]);
        STATE.messageTimer = window.setInterval(() => {
            STATE.messageIndex = (STATE.messageIndex + 1) % messages.length;
            UI.showStatus(messages[STATE.messageIndex]);
        }, Number(GENERATION.messageIntervalMs || 10000));
    }

    function stopStageMessages() {
        if (STATE.messageTimer) window.clearInterval(STATE.messageTimer);
        STATE.messageTimer = null;
    }

    function showCompletionMessage() {
        stopStageMessages();
        STATE.generationComplete = true;
        UI.showStatus(completionMessage());
        UI.hideModalToast();
    }

    function applyGenerationStatus(generations) {
        const items = Array.isArray(generations?.data) ? generations.data : (Array.isArray(generations) ? generations : STATE.generations);
        STATE.generations = items.filter(Boolean);
        STATE.hasRequestedAnyOutput = STATE.generations.length > 0 || STATE.pendingOutputIds.size > 0;
        renderOutputOptions();
        renderProductionTable();
        UI.showStep(4);

        const failed = STATE.generations.some((generation) => ['failed', 'cancelled'].includes(generation.status));
        const completed = STATE.generations.length > 0 && STATE.generations.every((generation) => generation.status === 'completed');

        if (failed) {
            clearActiveGeneration();
            stopStageMessages();
            STATE.generationComplete = false;
            UI.showStatus(failedMessage());
            UI.showError('generationError', failedMessage());
            const actionButton = document.getElementById('verifyOtp');
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع درخواست';

            return 'failed';
        }

        if (completed) {
            clearActiveGeneration();
            showCompletionMessage();
            const actionButton = document.getElementById('verifyOtp');
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع درخواست';

            return 'completed';
        }

        startStatusMessages();

        return 'processing';
    }

    async function pollUntilComplete() {
        const startedAt = Date.now();
        const intervalMs = Number(GENERATION.pollIntervalMs || 5000);
        const softTimeoutMs = Number(GENERATION.softTimeoutMs || 120000);
        const legacyTimeoutMs = Number(GENERATION.pollTimeoutMs || 900000);
        const maxWaitMs = Number(GENERATION.maxWaitMs ?? legacyTimeoutMs ?? 900000);
        let softMessageShown = false;
        let transientFailures = 0;
        const waitIndefinitely = GENERATION.allowBackgroundCompletion === false || maxWaitMs <= 0;

        while (waitIndefinitely || Date.now() - startedAt < maxWaitMs) {
            await wait(document.hidden ? Math.max(intervalMs, 15000) : intervalMs);
            let statusResult;

            try {
                statusResult = await refreshActiveGenerations();
                transientFailures = 0;
            } catch (error) {
                transientFailures += 1;
                UI.showStatus('ارتباط موقتاً قطع شده است؛ پردازش در سرور ادامه دارد و وضعیت دوباره بررسی می‌شود.');
                await wait(Math.min(30000, intervalMs * transientFailures));
                continue;
            }

            if (statusResult.success === false) {
                handleApiFailure(statusResult);
                return 'failed';
            }

            const state = applyGenerationStatus(statusResult);

            if (state === 'completed' || state === 'failed') return state;

            if (Date.now() - startedAt >= softTimeoutMs && !softMessageShown) {
                softMessageShown = true;
                UI.showStatus('ساخت پیش‌نمایش با مدل واقعی کمی زمان‌بر است. پردازش همچنان در سرور ادامه دارد.');
            }
        }

        stopStageMessages();
        UI.showStatus(backgroundContinueMessage());
        UI.showError('generationError', backgroundContinueMessage());

        return 'background';
    }

    function renderStagePanels(jobs) {
        renderStageList('processingStageList', jobs);
        renderStageList('resultsStageList', jobs);
    }

    function renderProductionTable() {
        const statusBox = document.getElementById('resultsStatusBox');
        const results = document.getElementById('resultsContainer');

        STATE.hasRequestedAnyOutput = STATE.generations.length > 0 || STATE.pendingOutputIds.size > 0;

        if (!STATE.hasRequestedAnyOutput) {
            statusBox?.classList.add('hidden');
            results?.classList.add('hidden');
            return;
        }

        renderStagePanels(STATE.generations);
        displayGenerationResults(STATE.generations);
        statusBox?.classList.remove('hidden');
    }

    function renderStageList(containerId, jobs = STATE.generations) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = categoryOutputs().map((output, index) => {
            const pending = STATE.pendingOutputIds.has(output.id);
            const generation = latestGenerationForOutput(output, jobs) || {};
            const requested = pending || Boolean(generation.id);
            const status = pending ? 'queued' : requested ? generation.status : 'empty';
            const failed = status === 'failed' || status === 'cancelled';
            const completed = status === 'completed';
            const processing = ['queued', 'dispatched', 'processing'].includes(status);
            const statusClass = failed ? 'is-failed' : completed ? 'is-completed' : processing ? 'is-processing' : '';
            const markerClass = completed
                ? 'bg-emerald-700 text-white'
                : failed ? 'bg-rose-600 text-white' : processing ? 'bg-indigo-700 text-white' : 'bg-white text-zinc-500';
            const error = failed && generation.error
                ? `<p class="mt-2 text-xs leading-6 text-rose-700">${escapeHtml(generation.error)}</p>`
                : '';

            return `
                <article class="hv-generation-stage ${statusClass} rounded-2xl p-3">
                    <div class="flex items-start gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-xs font-bold ${markerClass}">${toPersianDigits(index + 1)}</span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-zinc-900">${escapeHtml(output.name)}</span>
                            <span class="mt-1 block text-xs leading-6 text-zinc-500">${escapeHtml(output.description || '')}</span>
                            <span class="mt-2 inline-flex rounded-full bg-white/75 px-2 py-1 text-xs font-bold text-zinc-600 ring-1 ring-zinc-200">${escapeHtml(requested ? statusLabel(status) : 'هنوز درخواست نشده')}</span>
                            ${error}
                        </span>
                    </div>
                </article>
            `;
        }).join('');
    }

    function originalGalleryItem() {
        if (!STATE.originalPreviewUrl) return null;

        return {
            period: 'original',
            label: 'تصویر اصلی',
            caption: 'تصویر بارگذاری‌شده شما',
            status: 'completed',
            url: STATE.originalPreviewUrl,
            isOriginal: true,
        };
    }

    function generationDisplayItemsFromJobs(jobs = STATE.generations) {
        const items = categoryOutputs()
            .map((output) => {
                const pending = STATE.pendingOutputIds.has(output.id);
                const generation = latestGenerationForOutput(output, jobs) || {};
                const status = pending ? 'queued' : generation.status || null;
                const requested = pending || Boolean(generation.id);

                if (!requested) return null;

                return {
                    period: output.code,
                    label: output.name,
                    caption: output.description,
                    status: status || 'queued',
                    url: generation.asset_url || null,
                    isOriginal: false,
                };
            })
            .filter(Boolean);

        const original = items.length > 0 ? originalGalleryItem() : null;

        return original ? [original, ...items] : items;
    }

    function galleryItemsFromJobs(jobs = STATE.generations) {
        return generationDisplayItemsFromJobs(jobs).filter((item) => item.url);
    }

    function primeGallery() {
        STATE.galleryItems = galleryItemsFromJobs();
    }

    function downloadFileName(item) {
        const period = String(item?.period || 'result').replace(/[^a-z0-9_-]/gi, '-').toLowerCase();

        return `hairvision-${period}.jpg`;
    }

    function downloadLabel(item) {
        return `دانلود ${item?.label || 'تصویر'}`;
    }

    function displayGenerationResults(jobs) {
        const container = document.getElementById('resultsContainer');
        container.innerHTML = '';
        const items = generationDisplayItemsFromJobs(jobs);
        STATE.galleryItems = items.filter((item) => item.url);
        container.classList.toggle('hidden', items.length === 0);
        container.classList.toggle('grid', items.length > 0);

        items.forEach((item, index) => {
            const card = document.createElement('article');
            card.className = 'hv-result-card hv-enter overflow-hidden rounded-3xl bg-white shadow-lg shadow-indigo-100/70';
            card.style.animationDelay = `${index * 70}ms`;
            const safeUrl = item.url ? escapeHtml(item.url) : '';
            const galleryIndex = item.url
                ? STATE.galleryItems.findIndex((galleryItem) => galleryItem.period === item.period && galleryItem.url === item.url)
                : -1;
            const media = item.url
                ? `<img src="${safeUrl}" alt="${escapeHtml(item.label)}" class="hv-result-image aspect-square w-full object-cover" loading="lazy" />`
                : `<span class="hv-skeleton block aspect-square w-full"></span><span class="absolute inset-0 grid place-items-center px-4 text-center"><span><span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-white/90 text-sm font-bold text-indigo-700 shadow-sm"><span class="fi fi-rr-magic-wand h-5 w-5" aria-hidden="true"></span></span><span class="mt-3 block text-sm font-bold text-zinc-700">در حال ساخت</span><span class="mt-1 block text-xs leading-6 text-zinc-500">${escapeHtml(statusLabel(item.status))}</span></span></span>`;
            const status = item.isOriginal
                ? `<span class="mt-3 inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">${escapeHtml(statusLabel(item.status))}</span>`
                : `<span class="mt-3 inline-flex rounded-full bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-600">${escapeHtml(statusLabel(item.status))}</span>`;
            const downloadAction = item.url
                ? `<a class="inline-flex items-center gap-1.5 rounded-full bg-zinc-950 px-3 py-1.5 text-xs font-bold text-white transition hover:-translate-y-0.5 hover:bg-zinc-800" href="${safeUrl}" download="${escapeHtml(downloadFileName(item))}" aria-label="${escapeHtml(downloadLabel(item))}" data-gallery-download><span class="fi fi-rr-arrow-down h-3.5 w-3.5" aria-hidden="true"></span><span>دانلود</span></a>`
                : '';
            const mediaButton = item.url
                ? `<button type="button" class="block w-full cursor-zoom-in text-right" data-gallery-index="${galleryIndex}"><span class="relative block overflow-hidden bg-zinc-100">${media}</span></button>`
                : `<div class="block w-full text-right"><span class="relative block overflow-hidden bg-zinc-100">${media}</span></div>`;
            const textBlock = item.url
                ? `<button type="button" class="block w-full cursor-pointer text-right" data-gallery-index="${galleryIndex}">
                        <span class="block text-base font-bold text-zinc-950">${escapeHtml(item.label)}</span>
                        <span class="mt-1 block text-sm leading-6 text-zinc-500">${escapeHtml(item.caption || '')}</span>
                    </button>`
                : `<div class="block w-full text-right">
                        <span class="block text-base font-bold text-zinc-950">${escapeHtml(item.label)}</span>
                        <span class="mt-1 block text-sm leading-6 text-zinc-500">${escapeHtml(item.caption || '')}</span>
                    </div>`;

            card.innerHTML = `
                ${mediaButton}
                <div class="p-3">
                    ${textBlock}
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
        if (!STATE.galleryItems.length) primeGallery();
        if (!STATE.galleryItems.length) return;

        STATE.activeGalleryIndex = Math.min(Math.max(Number.isFinite(index) ? index : 0, 0), STATE.galleryItems.length - 1);
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
            caption.textContent = `${item.label} - ${item.caption || ''}`;
            downloadLink.href = item.url;
            downloadLink.download = downloadFileName(item);
            downloadLink.setAttribute('aria-label', downloadLabel(item));
            downloadLink.classList.remove('hidden');
            downloadLink.classList.add('inline-flex');
            image.classList.remove('is-changing');
        };

        if (!animate) return applyImage();
        if (item.url) image.classList.add('is-changing');
        window.setTimeout(applyImage, 160);
    }

    function resetGenerationProgress(clearStatus = true) {
        stopStageMessages();
        STATE.sourceImageId = null;
        STATE.generations = [];
        STATE.galleryItems = [];
        STATE.pendingOutputIds.clear();
        STATE.currentMessage = '';
        STATE.messageIndex = 0;
        STATE.generationComplete = false;
        STATE.awaitingOutputSelection = false;
        clearActiveGeneration();
        renderStagePanels([]);
        renderOutputOptions();
        document.getElementById('resultsContainer').innerHTML = '';

        const actionButton = document.getElementById('verifyOtp');
        if (actionButton) {
            actionButton.disabled = false;
            actionButton.textContent = 'تایید و شروع درخواست';
        }

        if (clearStatus) UI.clearStatus();
    }

    async function requestOtpForPhone(phone, mode = 'initial') {
        const initialButton = document.getElementById('requestOtp');
        const resendButton = document.getElementById('resendOtp');
        const activeButton = mode === 'resend' ? resendButton : initialButton;
        UI.clearError(mode === 'resend' ? 'otpError' : 'phoneError');

        if (phone.length !== 11) return UI.showError('phoneError', 'شماره موبایل باید ۱۱ رقم باشد.');
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
            result.debug_sms ? UI.showDebugOtp(result.debug_sms) : UI.clearDebugOtp();
            startOtpTimer(result.expires_in || 120);
            setMobileMode('otp');
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

    async function beginGenerationProcess(button = null) {
        const fileInput = document.getElementById('image');
        const consent = document.getElementById('consent').checked;
        const btn = button || document.getElementById('verifyOtp');

        UI.clearError('generationError');
        UI.clearError('mobileError');

        if (!STATE.verified) {
            sessionStorage.removeItem(STORAGE_KEYS.verified);
            UI.showStep(3);
            setMobileMode(STATE.phone ? 'otp' : 'phone');
            UI.showError('phoneError', 'برای ادامه، لطفاً ابتدا شماره موبایل خود را تایید کنید.');
            return;
        }

        if (STATE.generations.length && !STATE.generationComplete) {
            btn.disabled = true;
            btn.textContent = 'در حال بررسی نتیجه...';
            const statusResult = await refreshActiveGenerations();
            if (statusResult.success === false) {
                btn.disabled = false;
                btn.textContent = 'تایید و شروع درخواست';
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

        const category = selectedCategory();
        const availableOutputs = categoryOutputs();
        if (!category || !availableOutputs.length) {
            UI.showStep(1);
            return UI.showError('treatmentError', 'برای این کلینیک کاربرد فعالی قابل درخواست نیست.');
        }

        UI.clearDebugOtp();
        btn.disabled = true;

        try {
            if (!STATE.sourceImageId) {
                btn.disabled = true;
                btn.textContent = 'در حال ذخیره تصویر...';

                const result = await API.uploadSourceImage(fileInput.files[0], consent);
                if (!result.data?.id) {
                    btn.disabled = false;
                    btn.textContent = 'تایید و شروع درخواست';
                    return handleApiFailure(result, 'mobileError');
                }

                STATE.sourceImageId = result.data.id;
                STATE.sourceImageFromProfile = false;
            }

            ensureSelectedOutput();
            STATE.awaitingOutputSelection = true;
            renderOutputOptions();
            renderProductionTable();
            btn.disabled = false;
            btn.textContent = 'ادامه در مرحله انتخاب خروجی';
            UI.showStep(4);

            if (availableOutputs.length === 1 && !generationForOutput(availableOutputs[0])) {
                await requestSelectedOutput();
            }
        } catch (error) {
            stopStageMessages();
            btn.disabled = false;
            btn.textContent = 'تایید و شروع درخواست';
            UI.clearStatus();
            UI.showError('mobileError', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    async function requestSelectedOutput() {
        const category = selectedCategory();
        const output = selectedOutput();
        const button = document.getElementById('confirmOutputRequest');

        UI.clearError('generationError');

        if (!STATE.sourceImageId) {
            UI.showStep(3);
            UI.showError('generationError', 'ابتدا شماره را تایید کنید تا تصویر ذخیره شود.');
            return;
        }

        if (!category || !output) {
            UI.showError('generationError', 'خروجی موردنظر را انتخاب کنید.');
            return;
        }

        const existing = generationForOutput(output);
        if (existing?.status === 'completed') {
            UI.showError('generationError', 'این خروجی قبلاً آماده شده است.');
            return;
        }

        if (STATE.pendingOutputIds.has(output.id) || isNonTerminalGeneration(existing)) {
            UI.showError('generationError', 'این خروجی هم‌اکنون در حال پردازش است.');
            return;
        }

        if (button) {
            button.disabled = true;
            button.textContent = 'در حال ثبت درخواست...';
        }
        STATE.pendingOutputIds.add(output.id);
        STATE.hasRequestedAnyOutput = true;
        renderOutputOptions();
        renderProductionTable();
        startStatusMessages(true);

        try {
            const genResult = await API.createGeneration(STATE.sourceImageId, category.id, output.id);
            if (genResult.success === false) {
                STATE.pendingOutputIds.delete(output.id);
                renderOutputOptions();
                renderProductionTable();
                if (button) {
                    button.disabled = false;
                    button.textContent = 'تایید و ساخت خروجی انتخاب‌شده';
                }
                return handleApiFailure(genResult, 'generationError');
            }

            STATE.pendingOutputIds.delete(output.id);
            STATE.generationComplete = false;
            STATE.generations = mergeGenerations(STATE.generations, [genResult.data]);
            saveActiveGenerations();
            applyGenerationStatus(STATE.generations);

            const pollState = await pollUntilComplete();
            if (pollState === 'background') {
                if (button) button.disabled = false;
                renderOutputOptions();
            }
        } catch (error) {
            stopStageMessages();
            STATE.pendingOutputIds.delete(output.id);
            renderOutputOptions();
            renderProductionTable();
            if (button) {
                button.disabled = false;
                button.textContent = 'تایید و ساخت خروجی انتخاب‌شده';
            }
            UI.showError('generationError', 'خطایی رخ داد. لطفاً دوباره تلاش کنید.');
        }
    }

    function loadSessionState() {
        const storedPhone = sessionStorage.getItem(STORAGE_KEYS.phone);
        sessionStorage.removeItem(STORAGE_KEYS.verified);
        STATE.verified = false;

        if (storedPhone) {
            STATE.phone = normalizeDigits(storedPhone).slice(0, 11);
            document.getElementById('phone').value = toPersianDigits(STATE.phone);
            document.getElementById('displayPhone').textContent = toPersianDigits(STATE.phone);
            markOtpExpired();
        }

        setMobileMode('phone');
        UI.showStep(1);
    }

    async function hydrateVerifiedProfile() {
        const [profileResult, categoryResult, generationResult] = await Promise.all([
            API.profile(),
            API.profileCategories(),
            API.profileGenerations(),
        ]);

        if (profileResult.success === false || categoryResult.success === false || generationResult.success === false) {
            return false;
        }

        STATE.verified = true;
        sessionStorage.setItem(STORAGE_KEYS.verified, '1');
        applyCategories(categoriesFromPayload(categoryResult));
        STATE.sourceImageId = profileResult.data?.source_image?.id || null;
        STATE.sourceImageFromProfile = Boolean(STATE.sourceImageId);

        const currentGenerations = (generationResult.data || [])
            .filter((generation) => !STATE.sourceImageId || generation.source_image_id === STATE.sourceImageId);
        const latestByOutput = new Map();

        currentGenerations.forEach((generation) => {
            const outputId = generation.output?.id;
            if (outputId && !latestByOutput.has(outputId)) latestByOutput.set(outputId, generation);
        });

        STATE.generations = Array.from(latestByOutput.values());

        const latestGeneration = STATE.generations[0];
        if (latestGeneration?.category?.id && STATE.categories.some((category) => category.id === latestGeneration.category.id)) {
            STATE.selectedCategoryId = latestGeneration.category.id;
            ensureSelectedOutput();
        }

        saveActiveGenerations();

        if (STATE.generations.length) {
            const state = applyGenerationStatus(STATE.generations);

            if (state === 'processing') pollUntilComplete();
        }

        return true;
    }

    async function resumeActiveGenerationIfPresent() {
        const rawIds = sessionStorage.getItem(STORAGE_KEYS.activeGenerationIds);
        if (!rawIds) return;

        let ids = [];
        try {
            ids = JSON.parse(rawIds);
        } catch (error) {
            ids = [];
        }
        if (!Array.isArray(ids) || !ids.length) return;

        STATE.generations = ids.map((id) => ({ id, status: 'queued' }));
        UI.showStep(4);
        UI.showStatus('در حال بررسی وضعیت پردازش قبلی...');

        try {
            const statusResult = await refreshActiveGenerations();
            if (statusResult.success === false) {
                clearActiveGeneration();
                handleApiFailure(statusResult);
                return;
            }

            const state = applyGenerationStatus(statusResult);
            if (state === 'processing') {
                document.getElementById('verifyOtp').textContent = 'بررسی وضعیت نتیجه';
                await pollUntilComplete();
            }
        } catch (error) {
            UI.showStatus('ارتباط موقتاً قطع شد، اما اگر پردازش در سرور شروع شده باشد، ساخت تصاویر در پس‌زمینه ادامه دارد.');
        }
    }

    async function loadBootstrap() {
        const result = await API.bootstrap();
        if (result.success === false) {
            document.getElementById('continueToImage').disabled = true;
            document.getElementById('noApplicationsPanel').classList.remove('hidden');
            return result;
        }

        STATE.serverSessionVerified = result.session?.verified === true;
        applyCategories(categoriesFromPayload(result));

        return result;
    }

    bindDigitOnlyInput(document.getElementById('phone'), 11);
    bindDigitOnlyInput(document.getElementById('otp'), 6, () => {
        const button = document.getElementById('verifyOtp');
        if (!button.disabled) button.click();
    });

    document.getElementById('phone').addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('requestOtp').click();
        }
    });

    document.getElementById('continueToImage').addEventListener('click', () => {
        if (!selectedCategory()) return UI.showError('treatmentError', 'لطفاً یک کاربرد فعال را انتخاب کنید.');
        renderStagePanels([]);
        UI.showStep(2);
    });

    document.getElementById('confirmOutputRequest').addEventListener('click', requestSelectedOutput);

    document.getElementById('image').addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        if (!file) return;

        STATE.sourceImageId = null;
        STATE.sourceImageFromProfile = false;
        STATE.generations = [];
        clearActiveGeneration();

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
        STATE.galleryItems = [{
            period: 'original',
            label: 'تصویر اولیه',
            caption: 'تصویر انتخاب‌شده',
            status: 'completed',
            url: STATE.originalPreviewUrl,
        }];
        openImageModal(0);
    });

    document.getElementById('requestOtp').addEventListener('click', async () => {
        await requestOtpForPhone(normalizeDigits(document.getElementById('phone').value));
    });

    document.getElementById('resendOtp').addEventListener('click', async () => {
        if (!STATE.phone) {
            UI.showStep(3);
            return UI.showError('phoneError', 'برای ارسال کد، ابتدا شماره موبایل خود را وارد کنید.');
        }
        await requestOtpForPhone(STATE.phone, 'resend');
    });

    document.getElementById('verifyOtp').addEventListener('click', async () => {
        const btn = document.getElementById('verifyOtp');
        if (btn.disabled) return;

        if (STATE.verified) {
            await beginGenerationProcess(btn);

            return;
        }

        const code = normalizeDigits(document.getElementById('otp').value);
        UI.clearError('otpError');

        if (!code || code.length !== 6) return UI.showError('otpError', 'کد ۶ رقمی را وارد کنید.');

        btn.disabled = true;
        btn.textContent = 'در حال تایید...';
        const result = await API.verifyOtp(STATE.phone, code);
        btn.disabled = false;
        btn.textContent = 'تایید و شروع درخواست';

        if (result.success) {
            STATE.verified = true;
            stopOtpTimer();
            sessionStorage.setItem(STORAGE_KEYS.phone, STATE.phone);
            sessionStorage.setItem(STORAGE_KEYS.verified, '1');
            UI.clearError('mobileError');
            await refreshVerifiedCategories();
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
        setMobileMode('phone');
        UI.clearError('otpError');
        UI.clearError('mobileError');
        UI.clearDebugOtp();
        UI.showStep(3);
        document.getElementById('phone').focus();
    });

    document.getElementById('continueToPhone').addEventListener('click', () => {
        const fileInput = document.getElementById('image');
        const consent = document.getElementById('consent').checked;
        UI.clearError('uploadError');

        if (!fileInput.files.length) return UI.showError('uploadError', 'لطفاً یک تصویر انتخاب کنید.');
        if (!consent) return UI.showError('uploadError', 'برای ادامه باید رضایت پردازش تصویر را تایید کنید.');

        setMobileMode('phone');
        UI.showStep(3);
        document.getElementById('phone').focus();
    });

    document.getElementById('backToTreatment').addEventListener('click', () => {
        UI.clearError('uploadError');
        UI.showStep(1);
    });

    document.getElementById('backToImage')?.addEventListener('click', () => {
        UI.clearError('generationError');
        UI.clearError('otpError');
        UI.clearError('mobileError');
        UI.showStep(2);
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

    renderStagePanels([]);
    loadBootstrap().finally(async () => {
        loadSessionState();
        const hydrated = STATE.serverSessionVerified ? await hydrateVerifiedProfile() : false;

        if (!hydrated) resumeActiveGenerationIfPresent();
    });
}
