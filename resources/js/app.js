import Choices from 'choices.js';
import DataTable from 'datatables.net-dt';
import 'flowbite';

const persianDigits = (value) => String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);

const latinDigits = (value) => String(value).replace(/[۰-۹٠-٩]/g, (digit) => {
    const map = {
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

    return map[digit] ?? digit;
});

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
    let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100)
        + Math.floor((gy2 + 399) / 400) - 80 + gd + gdm[gm - 1];

    jy += 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    const jm = days < 186 ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30);
    const jd = days < 186 ? 1 + (days % 31) : 1 + ((days - 186) % 30);

    return [jy, jm, jd];
};

const jalaliToGregorian = (jy, jm, jd) => {
    let gy;

    if (jy > 979) {
        gy = 1600;
        jy -= 979;
    } else {
        gy = 621;
    }

    let days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4)
        + 78 + jd + (jm < 7 ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);

    gy += 400 * Math.floor(days / 146097);
    days %= 146097;

    if (days > 36524) {
        gy += 100 * Math.floor(--days / 36524);
        days %= 36524;

        if (days >= 365) {
            days++;
        }
    }

    gy += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    let gd = days + 1;
    const leap = ((gy % 4 === 0) && (gy % 100 !== 0)) || (gy % 400 === 0);
    const months = [0, 31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 1;

    while (gm <= 12 && gd > months[gm]) {
        gd -= months[gm];
        gm++;
    }

    return [gy, gm, gd];
};

const formatJalali = (jy, jm, jd) => persianDigits(`${jy}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`);
const formatGregorian = (gy, gm, gd) => `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;

const parseJalaliInput = (value) => {
    const normalized = latinDigits(value).trim();
    const matches = normalized.match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/);

    if (! matches) {
        return null;
    }

    const year = Number(matches[1]);
    const month = Number(matches[2]);
    const day = Number(matches[3]);

    if (year >= 1700) {
        return formatGregorian(year, month, day);
    }

    return formatGregorian(...jalaliToGregorian(year, month, day));
};

const enhanceJalaliDatepickers = () => {
    const monthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    document.querySelectorAll('[data-jalali-datepicker]').forEach((input) => {
        if (input.dataset.enhanced === 'true') {
            return;
        }

        input.dataset.enhanced = 'true';
        const hidden = document.querySelector(input.dataset.target);
        const today = new Date();
        let [year, month] = gregorianToJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());

        if (hidden?.value) {
            const parts = hidden.value.split('-').map(Number);
            [year, month] = gregorianToJalali(parts[0], parts[1], parts[2]);
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'relative';
        const panel = document.createElement('div');
        panel.className = 'absolute right-0 top-full z-40 mt-1 hidden w-72 rounded-lg border border-zinc-200 bg-white p-3 shadow-xl';

        const syncTypedValue = () => {
            const gregorian = parseJalaliInput(input.value);

            if (hidden && gregorian) {
                hidden.value = gregorian;
            }
        };

        const render = () => {
            const daysInMonth = month <= 6 ? 31 : (month <= 11 ? 30 : 29);
            panel.innerHTML = '';

            const header = document.createElement('div');
            header.className = 'mb-3 flex items-center justify-between text-sm font-bold';

            const next = document.createElement('button');
            next.type = 'button';
            next.className = 'rounded-lg px-2 py-1 hover:bg-zinc-100';
            next.textContent = 'بعد';
            next.addEventListener('click', () => {
                month = month === 12 ? 1 : month + 1;
                year = month === 1 ? year + 1 : year;
                render();
            });

            const previous = document.createElement('button');
            previous.type = 'button';
            previous.className = 'rounded-lg px-2 py-1 hover:bg-zinc-100';
            previous.textContent = 'قبل';
            previous.addEventListener('click', () => {
                month = month === 1 ? 12 : month - 1;
                year = month === 12 ? year - 1 : year;
                render();
            });

            const title = document.createElement('span');
            title.textContent = `${monthNames[month - 1]} ${persianDigits(year)}`;
            header.append(next, title, previous);

            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-7 gap-1 text-center text-xs';

            for (let day = 1; day <= daysInMonth; day++) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'h-8 rounded-lg hover:bg-blue-50 hover:text-blue-700';
                button.textContent = persianDigits(day);
                button.addEventListener('click', () => {
                    input.value = formatJalali(year, month, day);

                    if (hidden) {
                        hidden.value = formatGregorian(...jalaliToGregorian(year, month, day));
                    }

                    panel.classList.add('hidden');
                });
                grid.appendChild(button);
            }

            panel.append(header, grid);
        };

        input.parentNode.insertBefore(wrapper, input);
        wrapper.append(input, panel);

        input.addEventListener('focus', () => {
            render();
            panel.classList.remove('hidden');
        });
        input.addEventListener('blur', syncTypedValue);
        document.addEventListener('click', (event) => {
            if (! wrapper.contains(event.target)) {
                panel.classList.add('hidden');
            }
        });
    });
};

const enhanceSelects = () => {
    document.querySelectorAll('select.admin-select').forEach((select) => {
        if (select.dataset.choicesEnhanced === 'true') {
            return;
        }

        select.dataset.choicesEnhanced = 'true';
        const options = Array.from(select.options);
        const leadingOptions = options.filter((option) => option.value === '' || option.value === 'all');
        const sortableOptions = options
            .filter((option) => option.value !== '' && option.value !== 'all')
            .sort((a, b) => a.textContent.trim().localeCompare(b.textContent.trim(), 'fa'));

        select.replaceChildren(...leadingOptions, ...sortableOptions);

        new Choices(select, {
            allowHTML: false,
            itemSelectText: '',
            loadingText: 'در حال بارگذاری',
            noChoicesText: 'گزینه‌ای وجود ندارد',
            noResultsText: 'گزینه‌ای پیدا نشد',
            placeholder: false,
            position: 'bottom',
            searchEnabled: false,
            shouldSort: false,
            shouldSortItems: false,
        });
    });
};

const shouldNormalizeField = (field) => {
    if (! field.name || field.disabled || field.dataset.keepLocalizedDigits === 'true') {
        return false;
    }

    if (field.dataset.normalizeDigits === 'true') {
        return true;
    }

    const name = field.name.replace(/\[\]$/, '');
    const type = (field.getAttribute('type') || '').toLowerCase();
    const numericNames = ['from', 'to', 'priority', 'active', 'is_active', 'is_default', 'fallback_to_system'];

    return field.inputMode === 'numeric'
        || ['number', 'tel'].includes(type)
        || numericNames.includes(name)
        || name.endsWith('_id')
        || name.includes('phone')
        || name.includes('mobile')
        || name.includes('code');
};

const normalizeDashboardFormDigits = () => {
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('input, select').forEach((field) => {
                if (shouldNormalizeField(field)) {
                    field.value = latinDigits(field.value);
                }
            });
        });
    });
};

const persianizeVisibleNumbers = () => {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            if (! /\d/.test(node.nodeValue || '')) {
                return NodeFilter.FILTER_REJECT;
            }

            if (node.parentElement?.closest('script, style, input, textarea, select, code, pre')) {
                return NodeFilter.FILTER_REJECT;
            }

            return NodeFilter.FILTER_ACCEPT;
        },
    });
    const nodes = [];

    while (walker.nextNode()) {
        nodes.push(walker.currentNode);
    }

    nodes.forEach((node) => {
        node.nodeValue = persianDigits(node.nodeValue);
    });
};

const enhanceSmsProviderForms = () => {
    document.querySelectorAll('[data-sms-provider-form]').forEach((form) => {
        const select = form.querySelector('[data-sms-provider-type]');
        const fields = form.querySelectorAll('[data-provider-keys]');

        if (! select) {
            return;
        }

        const update = () => {
            const selected = select.selectedOptions[0]?.dataset.providerKey || '';

            fields.forEach((field) => {
                const keys = field.dataset.providerKeys.split(' ');
                field.classList.toggle('hidden', ! keys.includes(selected) && ! keys.includes('all'));
            });
        };

        select.addEventListener('change', update);
        update();
    });
};

const enhanceDataTables = () => {
    document.querySelectorAll('table[data-admin-datatable]').forEach((table) => {
        if (table.dataset.datatableEnhanced === 'true') {
            return;
        }

        table.dataset.datatableEnhanced = 'true';
        const isCompact = table.dataset.tableMode === 'compact';

        new DataTable(table, {
            autoWidth: false,
            drawCallback: () => persianizeVisibleNumbers(),
            info: ! isCompact,
            layout: isCompact
                ? {
                    topStart: null,
                    topEnd: null,
                    bottomStart: null,
                    bottomEnd: null,
                }
                : {
                    topStart: 'search',
                    topEnd: 'pageLength',
                    bottomStart: 'info',
                    bottomEnd: 'paging',
                },
            order: [],
            pageLength: Number(table.dataset.pageLength || 10),
            paging: ! isCompact,
            searching: ! isCompact,
            lengthChange: ! isCompact,
            lengthMenu: [10, 25, 50, 100],
            columnDefs: [
                {
                    targets: 'no-sort',
                    orderable: false,
                    searchable: false,
                },
            ],
            language: {
                search: 'جستجو:',
                lengthMenu: 'نمایش _MENU_ ردیف',
                info: 'نمایش _START_ تا _END_ از _TOTAL_ ردیف',
                infoEmpty: 'ردیفی برای نمایش وجود ندارد',
                infoFiltered: '(فیلتر شده از _MAX_ ردیف)',
                zeroRecords: 'نتیجه‌ای پیدا نشد',
                emptyTable: 'داده‌ای برای نمایش وجود ندارد',
                paginate: {
                    first: 'اول',
                    previous: 'قبلی',
                    next: 'بعدی',
                    last: 'آخر',
                },
            },
        });
    });
};

document.addEventListener('DOMContentLoaded', () => {
    enhanceSelects();
    enhanceJalaliDatepickers();
    enhanceDataTables();
    enhanceSmsProviderForms();
    normalizeDashboardFormDigits();
    persianizeVisibleNumbers();
});
