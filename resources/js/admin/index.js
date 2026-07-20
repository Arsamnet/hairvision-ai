import { bootActionMenus, closeActionMenus } from './action-menus.js';
import { bootAdminModals, closeOpenModals } from './modals.js';
import { bootConfirmationModal, closeConfirmationModal } from './confirmation-modal.js';
import { bootAdminTheme } from './theme.js';
import { bootMobileMenu, closeMobileMenu } from './mobile-menu.js';
import { bootResponsiveControls } from './responsive-controls.js';
import { bootSearchControls } from './search-controls.js';
import { bootJalaliDatepickers } from './jalali-datepickers.js';
import { bootNotifications } from './notifications.js';
import { bootPlanModels } from './plan-models.js';
import { bootNumericInputs } from './numeric-inputs.js';
import { bootAiModelImageProcessing } from './ai-model-image-processing.js';

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
    bootJalaliDatepickers();
    bootNotifications();
    bootPlanModels();
    bootNumericInputs();
    bootAiModelImageProcessing();

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
