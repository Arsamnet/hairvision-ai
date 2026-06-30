import { closeActionMenus } from './action-menus.js';
import { closeModal, openModal } from './modals.js';

const confirmModalId = 'admin-confirm-modal';

let pendingConfirmForm = null;
let confirmMessage = null;
let confirmCancel = null;
let confirmSubmit = null;
let defaultConfirmMessage = '';

const submitForm = (form) => {
    HTMLFormElement.prototype.submit.call(form);
};

const openConfirmationModal = (form) => {
    if (! document.querySelector(`[data-admin-modal-toggle="${confirmModalId}"]`)) {
        submitForm(form);

        return;
    }

    pendingConfirmForm = form;

    if (confirmMessage) {
        confirmMessage.textContent = form.dataset.confirmMessage || defaultConfirmMessage;
    }

    openModal(confirmModalId);
};

export const closeConfirmationModal = () => {
    closeModal(confirmModalId);
    pendingConfirmForm = null;
};

export const bootConfirmationModal = () => {
    confirmMessage = document.querySelector('[data-admin-confirm-message]');
    confirmCancel = document.querySelector('[data-admin-confirm-cancel]');
    confirmSubmit = document.querySelector('[data-admin-confirm-submit]');
    defaultConfirmMessage = confirmMessage?.textContent?.trim() ?? '';

    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (! form.matches('[data-confirm-form]')) {
            return;
        }

        event.preventDefault();
        closeActionMenus();
        openConfirmationModal(form);
    });

    document.addEventListener('change', (event) => {
        if (event.target.matches(`[data-admin-modal-toggle="${confirmModalId}"]`) && ! event.target.checked) {
            pendingConfirmForm = null;
        }
    });

    confirmCancel?.addEventListener('click', closeConfirmationModal);

    confirmSubmit?.addEventListener('click', () => {
        if (! pendingConfirmForm) {
            closeConfirmationModal();

            return;
        }

        const form = pendingConfirmForm;

        closeConfirmationModal();
        submitForm(form);
    });
};
