const root = document.documentElement;
const drawerSelector = '[data-admin-mobile-drawer]';
const openSelector = '[data-admin-mobile-menu-open]';
const closeSelector = '[data-admin-mobile-menu-close]';
const desktopQuery = window.matchMedia('(min-width: 1024px)');

const setOpenState = (isOpen) => {
    const drawer = document.querySelector(drawerSelector);

    root.classList.toggle('admin-mobile-menu-open', isOpen);

    if (drawer) {
        drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }

    document.querySelectorAll(openSelector).forEach((button) => {
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    if (isOpen) {
        requestAnimationFrame(() => {
            document.querySelector('[data-admin-mobile-menu-autofocus]')?.focus();
        });
    }
};

export const closeMobileMenu = () => setOpenState(false);

export const bootMobileMenu = () => {
    document.addEventListener('click', (event) => {
        if (event.target.closest(openSelector)) {
            event.preventDefault();
            setOpenState(true);

            return;
        }

        if (event.target.closest(closeSelector)) {
            closeMobileMenu();
        }
    });

    desktopQuery.addEventListener('change', (event) => {
        if (event.matches) {
            closeMobileMenu();
        }
    });
};
