import { bootActionMenus, closeActionMenus } from './action-menus.js';
import { bootAdminModals } from './modals.js';
import { bootConfirmationModal, closeConfirmationModal } from './confirmation-modal.js';
import { bootAdminTheme } from './theme.js';

const closeUserMenus = () => {
    document.querySelectorAll('[data-admin-user-menu][open]').forEach((menu) => {
        menu.removeAttribute('open');
    });
};

const bootUserMenus = () => {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-admin-user-menu]')) {
            return;
        }

        closeUserMenus();
    });
};

export const bootAdmin = () => {
    bootAdminTheme();
    bootUserMenus();
    bootAdminModals();
    bootActionMenus();
    bootConfirmationModal();

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeActionMenus();
        closeConfirmationModal();
        closeUserMenus();
    });
};
