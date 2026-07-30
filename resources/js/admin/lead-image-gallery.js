let activeGallery = null;

const focusableSelector = [
    'a[href]:not([hidden])',
    'button:not([disabled]):not([hidden])',
    '[tabindex]:not([tabindex="-1"]):not([hidden])',
].join(',');

const galleryItems = (root) => Array.from(root.querySelectorAll('[data-lead-gallery-item]')).map((item) => ({
    key: item.dataset.galleryKey,
    src: item.dataset.gallerySrc,
    title: item.dataset.galleryTitle,
    description: item.dataset.galleryDescription,
    fileName: item.dataset.galleryFileName,
})).filter((item) => item.key && item.src);

const finishLoading = (gallery) => {
    gallery.image.classList.remove('is-loading');
    gallery.image.removeAttribute('aria-busy');
    gallery.loader.hidden = true;
};

const renderGalleryItem = (gallery) => {
    const item = gallery.items[gallery.index];

    if (! item) {
        return;
    }

    gallery.image.classList.add('is-loading');
    gallery.image.setAttribute('aria-busy', 'true');
    gallery.loader.hidden = false;
    gallery.image.onload = () => finishLoading(gallery);
    gallery.image.onerror = () => finishLoading(gallery);
    gallery.image.src = item.src;
    gallery.image.alt = item.title;
    gallery.title.textContent = item.title;
    gallery.description.textContent = item.description || '';
    gallery.description.hidden = ! item.description;
    gallery.counter.textContent = `${gallery.index + 1} از ${gallery.items.length}`;
    gallery.download.href = item.src;
    gallery.download.download = item.fileName || 'hairvision-lead-image.jpg';
    gallery.previous.hidden = gallery.items.length < 2;
    gallery.next.hidden = gallery.items.length < 2;
};

const moveGallery = (gallery, offset) => {
    gallery.index = (gallery.index + offset + gallery.items.length) % gallery.items.length;
    renderGalleryItem(gallery);
};

const openGallery = (gallery, key = null) => {
    const requestedIndex = key ? gallery.items.findIndex((item) => item.key === key) : 0;

    gallery.index = requestedIndex >= 0 ? requestedIndex : 0;
    gallery.returnFocus = document.activeElement;
    gallery.modal.classList.remove('hidden');
    gallery.modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('lead-gallery-open');
    activeGallery = gallery;
    renderGalleryItem(gallery);
    gallery.close.focus({ preventScroll: true });
};

export const closeLeadImageGallery = () => {
    if (! activeGallery) {
        return;
    }

    const gallery = activeGallery;
    gallery.modal.classList.add('hidden');
    gallery.modal.setAttribute('aria-hidden', 'true');
    gallery.image.removeAttribute('src');
    finishLoading(gallery);
    document.documentElement.classList.remove('lead-gallery-open');
    activeGallery = null;
    gallery.returnFocus?.focus?.({ preventScroll: true });
};

const trapFocus = (event, gallery) => {
    const focusable = Array.from(gallery.modal.querySelectorAll(focusableSelector))
        .filter((element) => ! element.hidden && element.offsetParent !== null);

    if (! focusable.length) {
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (! event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
};

const bindGallery = (root) => {
    const items = galleryItems(root);
    const modal = root.querySelector('[data-lead-gallery-modal]');

    if (! items.length || ! modal) {
        return;
    }

    const gallery = {
        root,
        items,
        modal,
        index: 0,
        returnFocus: null,
        image: modal.querySelector('[data-lead-gallery-image]'),
        loader: modal.querySelector('[data-lead-gallery-loader]'),
        title: modal.querySelector('[data-lead-gallery-title]'),
        description: modal.querySelector('[data-lead-gallery-description]'),
        counter: modal.querySelector('[data-lead-gallery-counter]'),
        download: modal.querySelector('[data-lead-gallery-download]'),
        close: modal.querySelector('[data-lead-gallery-close]'),
        previous: modal.querySelector('[data-lead-gallery-previous]'),
        next: modal.querySelector('[data-lead-gallery-next]'),
    };

    root.querySelectorAll('[data-lead-gallery-trigger]').forEach((trigger) => {
        trigger.addEventListener('click', () => openGallery(gallery, trigger.dataset.leadGalleryTrigger));
    });

    root.querySelectorAll('[data-lead-gallery-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => openGallery(gallery));
    });

    gallery.close.addEventListener('click', closeLeadImageGallery);
    modal.querySelector('[data-lead-gallery-backdrop]').addEventListener('click', closeLeadImageGallery);
    gallery.previous.addEventListener('click', () => moveGallery(gallery, -1));
    gallery.next.addEventListener('click', () => moveGallery(gallery, 1));

    let touchStartX = null;
    modal.addEventListener('touchstart', (event) => {
        touchStartX = event.changedTouches[0]?.clientX ?? null;
    }, { passive: true });
    modal.addEventListener('touchend', (event) => {
        const touchEndX = event.changedTouches[0]?.clientX ?? null;

        if (touchStartX === null || touchEndX === null || Math.abs(touchEndX - touchStartX) < 48) {
            touchStartX = null;
            return;
        }

        moveGallery(gallery, touchEndX < touchStartX ? 1 : -1);
        touchStartX = null;
    }, { passive: true });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Tab') {
            trapFocus(event, gallery);
        }
    });
};

export const bootLeadImageGallery = () => {
    document.querySelectorAll('[data-lead-gallery]').forEach(bindGallery);

    document.addEventListener('keydown', (event) => {
        if (! activeGallery) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeLeadImageGallery();
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            moveGallery(activeGallery, -1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            moveGallery(activeGallery, 1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            activeGallery.index = 0;
            renderGalleryItem(activeGallery);
        } else if (event.key === 'End') {
            event.preventDefault();
            activeGallery.index = activeGallery.items.length - 1;
            renderGalleryItem(activeGallery);
        }
    });
};
