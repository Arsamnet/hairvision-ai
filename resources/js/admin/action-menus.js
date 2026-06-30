const actionMenuSelector = '[data-table-action-menu]';
const actionMenuItemSelector = '[data-table-action-item], a, button';

export const closeActionMenus = (except = null) => {
    document.querySelectorAll(`${actionMenuSelector}[open]`).forEach((menu) => {
        if (menu !== except) {
            menu.removeAttribute('open');
        }
    });
};

export const bootActionMenus = () => {
    document.addEventListener('click', (event) => {
        const summary = event.target.closest('summary');
        const summaryMenu = summary?.parentElement?.matches(actionMenuSelector) ? summary.parentElement : null;

        if (summaryMenu) {
            closeActionMenus(summaryMenu);

            return;
        }

        const menu = event.target.closest(actionMenuSelector);

        if (! menu) {
            closeActionMenus();

            return;
        }

        if (event.target.closest(actionMenuItemSelector)) {
            menu.removeAttribute('open');
        }
    });
};
