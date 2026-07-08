const actionMenuSelector = '[data-table-action-menu]';
const actionMenuItemSelector = '[data-table-action-item], a, button';
const tableRowSelector = '[data-admin-table-container] tbody tr';
const clickableRowSelector = `${tableRowSelector}[data-clickable-table-row="true"]`;
const rowViewActionSelector = '[data-table-row-view-action]';
const rowInteractiveSelector = [
    'a',
    'button',
    'input',
    'select',
    'textarea',
    'label',
    'summary',
    '[role="button"]',
    '[data-table-action-menu]',
    '[data-admin-modal-trigger]',
].join(',');

export const closeActionMenus = (except = null) => {
    document.querySelectorAll(`${actionMenuSelector}[open]`).forEach((menu) => {
        if (menu !== except) {
            menu.removeAttribute('open');
        }
    });
};

export const bootActionMenus = () => {
    document.querySelectorAll(tableRowSelector).forEach((row) => {
        const viewAction = row.querySelector(rowViewActionSelector);

        if (viewAction?.getAttribute('href') || viewAction?.dataset.adminModalTrigger) {
            row.dataset.clickableTableRow = 'true';
        }
    });

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

    document.addEventListener('click', (event) => {
        const row = event.target.closest(clickableRowSelector);

        if (! row || event.target.closest(rowInteractiveSelector)) {
            return;
        }

        const viewAction = row.querySelector(rowViewActionSelector);
        const href = viewAction?.getAttribute('href');

        if (href) {
            window.location.assign(href);

            return;
        }

        if (viewAction?.dataset.adminModalTrigger) {
            viewAction.click();
        }
    });
};
