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

const toLatinDigits = (value) => String(value ?? '').replace(/[۰-۹٠-٩]/g, (digit) => digitMap[digit] || digit);

const digitsOnly = (value) => toLatinDigits(value).replace(/[^0-9]/g, '');

const formatWithThousandsSeparators = (value) => {
    const digits = digitsOnly(value);

    return digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

const caretAfterDigit = (value, digitCount) => {
    if (digitCount <= 0) {
        return 0;
    }

    let seen = 0;

    for (let index = 0; index < value.length; index += 1) {
        if (/[0-9]/.test(value[index])) {
            seen += 1;
        }

        if (seen === digitCount) {
            return index + 1;
        }
    }

    return value.length;
};

const formatNumberInput = (input, preserveSelection = false) => {
    const selectionStart = preserveSelection ? input.selectionStart : null;
    const selectionEnd = preserveSelection ? input.selectionEnd : null;
    const startDigitCount = selectionStart === null ? null : digitsOnly(input.value.slice(0, selectionStart)).length;
    const endDigitCount = selectionEnd === null ? null : digitsOnly(input.value.slice(0, selectionEnd)).length;
    const formatted = formatWithThousandsSeparators(input.value);

    input.value = formatted;

    if (startDigitCount !== null && endDigitCount !== null) {
        input.setSelectionRange(
            caretAfterDigit(formatted, startDigitCount),
            caretAfterDigit(formatted, endDigitCount),
        );
    }
};

const normalizeDigitInput = (input, preserveSelection = false) => {
    const selectionStart = preserveSelection ? input.selectionStart : null;
    const normalized = toLatinDigits(input.value);

    input.value = normalized;

    if (selectionStart !== null) {
        input.setSelectionRange(selectionStart, selectionStart);
    }
};

const handleNumericInput = (event) => {
    if (! (event.target instanceof HTMLInputElement)) {
        return;
    }

    if (event.target.matches('[data-number-input]')) {
        formatNumberInput(event.target, true);

        return;
    }

    if (event.target.matches('[data-digit-input]')) {
        normalizeDigitInput(event.target, true);
    }
};

const normalizeSubmittedNumbers = (event) => {
    if (! (event.target instanceof HTMLFormElement)) {
        return;
    }

    event.target.querySelectorAll('[data-number-input]').forEach((input) => {
        input.value = digitsOnly(input.value);
    });

    event.target.querySelectorAll('[data-digit-input]').forEach((input) => {
        input.value = toLatinDigits(input.value);
    });
};

export const bootNumericInputs = () => {
    document.querySelectorAll('[data-number-input]').forEach((input) => formatNumberInput(input));
    document.querySelectorAll('[data-digit-input]').forEach((input) => normalizeDigitInput(input));

    document.addEventListener('input', handleNumericInput);
    document.addEventListener('submit', normalizeSubmittedNumbers, true);
};
