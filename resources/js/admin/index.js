import { bootActionMenus, closeActionMenus } from './action-menus.js';
import { bootAdminModals, closeOpenModals } from './modals.js';
import { bootConfirmationModal, closeConfirmationModal } from './confirmation-modal.js';
import { bootAdminTheme } from './theme.js';
import { bootMobileMenu, closeMobileMenu } from './mobile-menu.js';
import { bootResponsiveControls } from './responsive-controls.js';
import { bootSearchControls } from './search-controls.js';

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
    bootMobileMenu();
    bootResponsiveControls();
    bootSearchControls();

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeActionMenus();
        closeConfirmationModal();
        closeOpenModals();
        closeUserMenus();
        closeMobileMenu();
    });
};
