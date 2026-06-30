const storageKey = 'hairvision.admin.theme';
const themes = ['dark', 'light'];

const readStoredTheme = () => {
    try {
        return localStorage.getItem(storageKey);
    } catch (error) {
        return null;
    }
};

const writeStoredTheme = (theme) => {
    try {
        localStorage.setItem(storageKey, theme);
    } catch (error) {
        // Browser storage can be unavailable in restricted modes.
    }
};

const preferredTheme = () => (
    window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
);

const applyTheme = (theme, persist = false) => {
    const safeTheme = themes.includes(theme) ? theme : preferredTheme();
    const root = document.documentElement;

    root.dataset.adminTheme = safeTheme;
    root.classList.toggle('dark', safeTheme === 'dark');
    root.style.colorScheme = safeTheme;

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const nextLabel = safeTheme === 'dark'
            ? button.dataset.lightLabel
            : button.dataset.darkLabel;

        button.dataset.currentTheme = safeTheme;
        button.setAttribute('aria-pressed', safeTheme === 'dark' ? 'true' : 'false');

        if (nextLabel) {
            button.setAttribute('aria-label', nextLabel);
            button.setAttribute('title', nextLabel);
        }
    });

    if (persist) {
        writeStoredTheme(safeTheme);
    }
};

export const bootAdminTheme = () => {
    const storedTheme = readStoredTheme();
    const initialTheme = themes.includes(storedTheme) ? storedTheme : preferredTheme();

    applyTheme(initialTheme);

    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        if (themes.includes(readStoredTheme())) {
            return;
        }

        applyTheme(event.matches ? 'dark' : 'light');
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            applyTheme(document.documentElement.dataset.adminTheme === 'dark' ? 'light' : 'dark', true);
        });
    });
};
