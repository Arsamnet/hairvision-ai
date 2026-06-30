const searchInputSelector = '[data-admin-search-input]';
const searchClearSelector = '[data-admin-search-clear]';

const searchClearControl = (input) => {
    const key = input.dataset.adminSearchInput;

    return key ? document.querySelector(`${searchClearSelector}[data-admin-search-clear="${key}"]`) : null;
};

const syncSearchClearControl = (input) => {
    searchClearControl(input)?.classList.toggle('hidden', ! input.value);
};

export const bootSearchControls = () => {
    document.querySelectorAll(searchInputSelector).forEach(syncSearchClearControl);

    document.addEventListener('input', (event) => {
        if (! event.target.matches(searchInputSelector)) {
            return;
        }

        syncSearchClearControl(event.target);
    });

    document.addEventListener('click', (event) => {
        const clearControl = event.target.closest(searchClearSelector);

        if (! clearControl || clearControl.dataset.adminSearchHasQuery === 'true') {
            return;
        }

        const input = document.querySelector(`${searchInputSelector}[data-admin-search-input="${clearControl.dataset.adminSearchClear}"]`);

        if (! input) {
            return;
        }

        event.preventDefault();
        input.value = '';
        input.focus();
        syncSearchClearControl(input);
    });
};
