const modalToggleSelector = '[data-admin-modal-toggle]';
const modalCloseSelector = '[data-admin-modal-close]';
const modalBackdropSelector = '[data-admin-modal-backdrop]';

const modalToggle = (modalId) => document.querySelector(`${modalToggleSelector}[data-admin-modal-toggle="${modalId}"]`);

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
        toggle.checked = true;
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
};
