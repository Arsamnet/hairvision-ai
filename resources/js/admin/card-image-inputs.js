/**
 * @since 2.1.0 Adds safe selection cancellation and persisted card-image removal controls.
 */
const FIELD_SELECTOR = '[data-card-image-field]';
const INPUT_SELECTOR = '[data-card-image-input]';
const CLEAR_SELECTOR = '[data-card-image-clear]';
const REMOVE_SELECTOR = '[data-card-image-remove]';

const syncField = (field) => {
    const input = field.querySelector(INPUT_SELECTOR);
    const clearButton = field.querySelector(CLEAR_SELECTOR);

    if (!input || !clearButton) {
        return;
    }

    clearButton.disabled = input.files.length === 0;
};

/**
 * Connects card-image selection, cancellation, and persisted-image removal.
 *
 * @returns {void}
 */
export const bootCardImageInputs = () => {
    document.querySelectorAll(FIELD_SELECTOR).forEach((field) => {
        if (field.dataset.cardImageBound === 'true') {
            return;
        }

        field.dataset.cardImageBound = 'true';

        const input = field.querySelector(INPUT_SELECTOR);
        const clearButton = field.querySelector(CLEAR_SELECTOR);
        const removeCheckbox = field.querySelector(REMOVE_SELECTOR);

        if (!input || !clearButton) {
            return;
        }

        input.addEventListener('change', () => {
            if (input.files.length > 0 && removeCheckbox) {
                removeCheckbox.checked = false;
            }

            syncField(field);
        });

        clearButton.addEventListener('click', () => {
            input.value = '';
            syncField(field);
            input.focus();
        });

        removeCheckbox?.addEventListener('change', () => {
            if (removeCheckbox.checked) {
                input.value = '';
                syncField(field);
            }
        });

        syncField(field);
    });
};
