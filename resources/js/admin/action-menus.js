const actionMenuSelector = '[data-table-action-menu]';
const actionMenuPanelSelector = '[data-table-action-menu-panel]';
const actionMenuItemSelector = '[data-table-action-item], a, button';
const tableRowSelector = '[data-admin-table-container] tbody tr';
const clickableRowSelector = `${tableRowSelector}[data-clickable-table-row="true"]`;
const rowViewActionSelector = '[data-table-row-view-action]';
const menuViewportGap = 12;
const menuSummaryGap = 8;
let actionMenuPositionFrame = null;
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

const resetActionMenuPosition = (menu) => {
    const panel = menu.querySelector(actionMenuPanelSelector);

    if (! panel) {
        return;
    }

    panel.style.removeProperty('position');
    panel.style.removeProperty('inset');
    panel.style.removeProperty('top');
    panel.style.removeProperty('right');
    panel.style.removeProperty('bottom');
    panel.style.removeProperty('left');
    panel.style.removeProperty('margin-top');
};

const positionActionMenu = (menu) => {
    const summary = menu.querySelector('summary');
    const panel = menu.querySelector(actionMenuPanelSelector);

    if (! menu.open || ! summary || ! panel) {
        resetActionMenuPosition(menu);

        return;
    }

    panel.style.position = 'fixed';
    panel.style.inset = 'auto';
    panel.style.marginTop = '0';

    const summaryRect = summary.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
    const documentDirection = document.documentElement.dir || document.body.dir || 'rtl';
    const alignPanelToRight = documentDirection === 'rtl' || window.matchMedia('(min-width: 1024px)').matches;
    const naturalLeft = alignPanelToRight ? summaryRect.right - panelRect.width : summaryRect.left;
    const maxLeft = viewportWidth - panelRect.width - menuViewportGap;
    const left = Math.min(Math.max(naturalLeft, menuViewportGap), Math.max(menuViewportGap, maxLeft));
    const belowTop = summaryRect.bottom + menuSummaryGap;
    const aboveTop = summaryRect.top - panelRect.height - menuSummaryGap;
    const top = belowTop + panelRect.height + menuViewportGap > viewportHeight && aboveTop >= menuViewportGap
        ? aboveTop
        : Math.min(Math.max(belowTop, menuViewportGap), Math.max(menuViewportGap, viewportHeight - panelRect.height - menuViewportGap));

    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
};

const positionOpenActionMenus = () => {
    document.querySelectorAll(`${actionMenuSelector}[open]`).forEach(positionActionMenu);
};

const scheduleOpenActionMenuPosition = () => {
    if (actionMenuPositionFrame) {
        return;
    }

    actionMenuPositionFrame = window.requestAnimationFrame(() => {
        actionMenuPositionFrame = null;
        positionOpenActionMenus();
    });
};

export const closeActionMenus = (except = null) => {
    document.querySelectorAll(`${actionMenuSelector}[open]`).forEach((menu) => {
        if (menu !== except) {
            resetActionMenuPosition(menu);
            menu.removeAttribute('open');
        }
    });
};

export const bootActionMenus = () => {
    document.querySelectorAll(actionMenuSelector).forEach((menu) => {
        if (menu.dataset.actionMenuBound === 'true') {
            return;
        }

        menu.dataset.actionMenuBound = 'true';
        menu.addEventListener('toggle', () => {
            if (menu.open) {
                closeActionMenus(menu);
                positionActionMenu(menu);

                return;
            }

            resetActionMenuPosition(menu);
        });
    });

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
            window.requestAnimationFrame(() => positionActionMenu(summaryMenu));

            return;
        }

        const menu = event.target.closest(actionMenuSelector);

        if (! menu) {
            closeActionMenus();

            return;
        }

        if (event.target.closest(actionMenuItemSelector)) {
            resetActionMenuPosition(menu);
            menu.removeAttribute('open');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeActionMenus();
        }
    });

    window.addEventListener('resize', scheduleOpenActionMenuPosition);
    window.addEventListener('scroll', scheduleOpenActionMenuPosition, true);

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
