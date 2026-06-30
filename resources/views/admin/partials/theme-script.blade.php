<script data-admin-theme-script>
    (() => {
        const key = 'hairvision.admin.theme';
        const root = document.documentElement;
        let stored = null;

        try {
            stored = localStorage.getItem(key);
        } catch (error) {
            stored = null;
        }

        const theme = ['dark', 'light'].includes(stored)
            ? stored
            : 'light';

        root.dataset.adminTheme = theme;
        root.classList.toggle('dark', theme === 'dark');
        root.style.colorScheme = theme;
    })();
</script>
