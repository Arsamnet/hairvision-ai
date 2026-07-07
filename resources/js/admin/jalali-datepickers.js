import '@majidh1/jalalidatepicker';
import '@majidh1/jalalidatepicker/dist/jalalidatepicker.min.css';

let focusSyncRegistered = false;

const digitMap = {
    '۰': '0',
    '۱': '1',
    '۲': '2',
    '۳': '3',
    '۴': '4',
    '۵': '5',
    '۶': '6',
    '۷': '7',
    '۸': '8',
    '۹': '9',
    '٠': '0',
    '١': '1',
    '٢': '2',
    '٣': '3',
    '٤': '4',
    '٥': '5',
    '٦': '6',
    '٧': '7',
    '٨': '8',
    '٩': '9',
};

const normalizeDigits = (value) => String(value || '').replace(/[۰-۹٠-٩]/g, (digit) => digitMap[digit] || digit);

const pad = (value) => String(value).padStart(2, '0');

const gregorianToJalali = (gy, gm, gd) => {
    const gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jy;

    if (gy > 1600) {
        jy = 979;
        gy -= 1600;
    } else {
        jy = 0;
        gy -= 621;
    }

    const gy2 = gm > 2 ? gy + 1 : gy;
    let days = 365 * gy + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + gdm[gm - 1];

    jy += 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);

    return [jy, jm, jd];
};

const todayJalaliDate = () => {
    const today = new Date();
    const [year, month, day] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());

    return `${year}/${pad(month)}/${pad(day)}`;
};

const jalaliDatePart = (value) => {
    const normalized = normalizeDigits(value).trim().replace(/[-.]/g, '/');
    const match = normalized.match(/(\d{4})\/(\d{1,2})\/(\d{1,2})/);

    if (!match) {
        return '';
    }

    return `${match[1]}/${pad(match[2])}/${pad(match[3])}`;
};

const syncInitDate = (input) => {
    input.setAttribute('data-jdp-init-date', jalaliDatePart(input.value) || todayJalaliDate());
};

const markDatepickers = () => {
    document.querySelectorAll('[data-jalali-date], [data-jalali-datetime]').forEach((input) => {
        input.setAttribute('data-jdp', '');
        syncInitDate(input);

        if (input.matches('[data-jalali-date]')) {
            input.setAttribute('data-jdp-only-date', '');
        }
    });
};

const syncFocusedDatepicker = (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('input[data-jdp]')) {
        syncInitDate(event.target);
    }
};

export const bootJalaliDatepickers = () => {
    markDatepickers();

    if (!focusSyncRegistered) {
        document.body.addEventListener('focusin', syncFocusedDatepicker);
        focusSyncRegistered = true;
    }

    if (!window.jalaliDatepicker) {
        return;
    }

    window.jalaliDatepicker.startWatch({
        selector: 'input[data-jdp]',
        date: true,
        time: true,
        initDate: 'attr',
        hasSecond: true,
        persianDigits: false,
        autoShow: true,
        autoHide: true,
        hideAfterChange: false,
        showTodayBtn: true,
        showEmptyBtn: true,
        plusHtml: '+',
        minusHtml: '-',
        separatorChars: {
            date: '/',
            between: ' ',
            time: ':',
        },
    });
};
