const desktopControlsSelector = '[data-responsive-desktop-controls]';
const mobileControlsSelector = '[data-responsive-mobile-controls]';
const formControlSelector = 'input, select, textarea, button';
const desktopQuery = window.matchMedia('(min-width: 1024px)');

const setControlsDisabled = (container, disabled) => {
    container.querySelectorAll(formControlSelector).forEach((control) => {
        control.disabled = disabled;
    });
};

const syncResponsiveControls = () => {
    const isDesktop = desktopQuery.matches;

    document.querySelectorAll(desktopControlsSelector).forEach((container) => {
        setControlsDisabled(container, ! isDesktop);
    });

    document.querySelectorAll(mobileControlsSelector).forEach((container) => {
        setControlsDisabled(container, isDesktop);
    });
};

export const bootResponsiveControls = () => {
    syncResponsiveControls();
    desktopQuery.addEventListener('change', syncResponsiveControls);
};
