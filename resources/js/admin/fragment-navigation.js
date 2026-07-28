const adminContentSelector = '[data-admin-scroll-container]';

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const resetRootScroll = () => {
    document.documentElement.scrollTop = 0;
    document.body.scrollTop = 0;
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
};

const targetForHash = (hash) => {
    if (!hash || hash === '#') return null;

    try {
        return document.getElementById(decodeURIComponent(hash.slice(1)));
    } catch (error) {
        return null;
    }
};

const scrollAdminContentToHash = (hash, behavior = 'smooth') => {
    const scroller = document.querySelector(adminContentSelector);
    const target = targetForHash(hash);

    if (!(scroller instanceof HTMLElement) || !(target instanceof HTMLElement)) {
        resetRootScroll();

        return false;
    }

    const targetStyles = window.getComputedStyle(target);
    const margin = Number.parseFloat(targetStyles.scrollMarginBlockStart) || 0;
    const top = target.getBoundingClientRect().top
        - scroller.getBoundingClientRect().top
        + scroller.scrollTop
        - margin;

    resetRootScroll();
    scroller.scrollTo({
        top: Math.max(0, top),
        left: scroller.scrollLeft,
        behavior: reducedMotion() ? 'auto' : behavior,
    });
    requestAnimationFrame(resetRootScroll);

    return true;
};

const sameDocumentHashLink = (anchor) => {
    if (!(anchor instanceof HTMLAnchorElement) || !anchor.hash || anchor.hasAttribute('download')) return false;

    const url = new URL(anchor.href, window.location.href);

    return url.origin === window.location.origin
        && url.pathname === window.location.pathname
        && url.search === window.location.search;
};

const restoreInitialHash = () => {
    const hash = window.__hairvisionAdminPendingHash || window.location.hash;

    if (!hash) {
        resetRootScroll();

        return;
    }

    history.replaceState(history.state, '', `${window.location.pathname}${window.location.search}${hash}`);
    requestAnimationFrame(() => {
        requestAnimationFrame(() => scrollAdminContentToHash(hash, 'auto'));
    });
    window.__hairvisionAdminPendingHash = '';
};

/**
 * Keep all dashboard fragment movement inside the main content scroller.
 *
 * @returns {void}
 */
export const bootAdminFragmentNavigation = () => {
    if (!document.documentElement.classList.contains('admin-root')) return;

    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const anchor = event.target.closest('a[href]');

        if (!sameDocumentHashLink(anchor) || !targetForHash(anchor.hash)) return;

        event.preventDefault();
        history.pushState(history.state, '', `${window.location.pathname}${window.location.search}${anchor.hash}`);
        scrollAdminContentToHash(anchor.hash);
    });

    window.addEventListener('hashchange', () => scrollAdminContentToHash(window.location.hash));
    window.addEventListener('pageshow', () => {
        resetRootScroll();

        if (window.location.hash) scrollAdminContentToHash(window.location.hash, 'auto');
    });

    restoreInitialHash();
};
