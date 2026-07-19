const modalToggleSelector = '[data-admin-modal-toggle]';
const modalTriggerSelector = '[data-admin-modal-trigger]';
const modalCloseSelector = '[data-admin-modal-close]';
const modalBackdropSelector = '[data-admin-modal-backdrop]';
const modalLabelControlSelector = `${modalTriggerSelector}, [data-admin-modal-label-close]`;

const modalToggle = (modalId) => document.querySelector(`${modalToggleSelector}[data-admin-modal-toggle="${modalId}"]`);

const syncModalScrollLock = () => {
    document.documentElement.classList.toggle(
        'admin-modal-open',
        Boolean(document.querySelector(`${modalToggleSelector}:checked`)),
    );
};

const preserveScrollPosition = (callback) => {
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;

    callback();
    window.scrollTo(scrollX, scrollY);
    requestAnimationFrame(() => window.scrollTo(scrollX, scrollY));
};

export const openModal = (modalId) => {
    const toggle = modalToggle(modalId);

    if (toggle) {
        preserveScrollPosition(() => {
            toggle.checked = true;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
            syncModalScrollLock();
        });
    }
};

export const closeModal = (modalId) => {
    const toggle = modalToggle(modalId);

    if (! toggle) {
        return;
    }

    preserveScrollPosition(() => {
        toggle.checked = false;
        toggle.dispatchEvent(new Event('change', { bubbles: true }));
        syncModalScrollLock();
    });
};

export const closeOpenModals = () => {
    document.querySelectorAll(`${modalToggleSelector}:checked`).forEach((toggle) => {
        closeModal(toggle.dataset.adminModalToggle);
    });
};

export const bootAdminModals = () => {
    document.addEventListener('click', (event) => {
        const closeControl = event.target.closest(`${modalCloseSelector}, ${modalBackdropSelector}`);

        if (! closeControl) {
            return;
        }

        event.preventDefault();
        closeModal(closeControl.dataset.adminModalClose || closeControl.dataset.adminModalBackdrop);
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest(modalTriggerSelector);

        if (! trigger) {
            return;
        }

        event.preventDefault();
        openModal(trigger.dataset.adminModalTrigger);
    });

    document.addEventListener('keydown', (event) => {
        if (! ['Enter', ' '].includes(event.key)) {
            return;
        }

        const labelControl = event.target.closest(modalLabelControlSelector);

        if (! labelControl) {
            return;
        }

        event.preventDefault();
        labelControl.click();
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches(modalToggleSelector)) {
            syncModalScrollLock();
        }
    });

    syncModalScrollLock();
};
