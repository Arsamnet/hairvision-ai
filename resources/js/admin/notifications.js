const tenantSummary = (selected, total, labels) => {
    if (selected === total && total > 0) {
        return labels.all;
    }

    if (selected === 0) {
        return labels.none;
    }

    return labels.selected.replace(':count', String(selected));
};

const syncSchedule = (form) => {
    const immediate = form.querySelector('[data-notification-immediate]');
    const fields = form.querySelector('[data-notification-scheduled-fields]');

    if (!immediate || !fields) {
        return;
    }

    fields.hidden = immediate.checked;
};

const checkedTenants = (checkboxes) => checkboxes.filter((checkbox) => checkbox.checked);

const csvValues = (value) => (value || '').split(',').filter(Boolean);

const currentFilters = (form) => ({
    planState: form.querySelector('select[name="filter_plan_state"]')?.value || 'any',
    categoryId: form.querySelector('select[name="filter_ai_model_category_id"]')?.value || '',
    modelAccessId: form.querySelector('select[name="filter_ai_model_access_id"]')?.value || '',
});

const tenantMatchesFilters = (checkbox, filters) => {
    if (filters.planState === 'active' && checkbox.dataset.hasActivePlan !== '1') {
        return false;
    }

    if (filters.planState === 'inactive' && checkbox.dataset.hasActivePlan === '1') {
        return false;
    }

    if (filters.categoryId && !csvValues(checkbox.dataset.categoryIds).includes(filters.categoryId)) {
        return false;
    }

    if (filters.modelAccessId && !csvValues(checkbox.dataset.modelAccessIds).includes(filters.modelAccessId)) {
        return false;
    }

    return true;
};

const syncMatchCount = (form, checkboxes) => {
    const countNode = form.querySelector('[data-notification-match-count]');

    if (!countNode) {
        return;
    }

    const filters = currentFilters(form);
    const count = checkedTenants(checkboxes)
        .filter((checkbox) => tenantMatchesFilters(checkbox, filters))
        .length;

    countNode.textContent = count === 0
        ? countNode.dataset.zeroLabel || ''
        : (countNode.dataset.countLabel || '').replace(':count', String(count));
};

const syncTenants = (form, changedCheckbox = null, source = 'checkbox') => {
    const scope = form.querySelector('select[name="target_scope"]');
    const picker = form.querySelector('[data-notification-tenant-picker]');
    const checkboxes = Array.from(form.querySelectorAll('[data-notification-tenant-checkbox]'));
    const summary = form.querySelector('[data-notification-tenant-summary]');

    if (!scope || !picker || checkboxes.length === 0) {
        return;
    }

    const total = checkboxes.length;
    const selectedBeforeSync = checkedTenants(checkboxes).length;

    picker.hidden = false;

    if (scope.value === 'all' && !changedCheckbox) {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = true;
        });
    }

    if (source === 'scope' && ['tenant', 'selected'].includes(scope.value) && selectedBeforeSync === total) {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });
    }

    if (scope.value === 'tenant') {
        if (changedCheckbox?.checked) {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = checkbox === changedCheckbox;
            });
        } else {
            const checked = checkboxes.filter((checkbox) => checkbox.checked);
            checked.slice(1).forEach((checkbox) => {
                checkbox.checked = false;
            });
        }
    }

    const selected = checkedTenants(checkboxes).length;

    if (source === 'checkbox' && selected === total) {
        scope.value = 'all';
    } else if (scope.value === 'all' && selected < total) {
        scope.value = changedCheckbox ? 'selected' : (selected <= 1 ? 'tenant' : 'selected');
    } else if (scope.value === 'tenant' && selected > 1) {
        scope.value = 'selected';
    }

    if (summary) {
        summary.textContent = tenantSummary(selected, total, {
            all: summary.dataset.allLabel || '',
            none: summary.dataset.noneLabel || '',
            selected: summary.dataset.selectedLabel || '',
        });
    }

    syncMatchCount(form, checkboxes);
};

const syncRichTextInput = (composer) => {
    const editor = composer.querySelector('[data-rich-text-editor]');
    const input = composer.querySelector('[data-rich-text-input]');

    if (editor && input) {
        input.value = editor.innerHTML.trim();
    }
};

const bootRichText = (form) => {
    form.querySelectorAll('[data-notification-composer]').forEach((composer) => {
        const editor = composer.querySelector('[data-rich-text-editor]');

        if (!editor) {
            return;
        }

        editor.addEventListener('input', () => syncRichTextInput(composer));

        composer.querySelectorAll('[data-rich-text-command]').forEach((button) => {
            button.addEventListener('click', () => {
                editor.focus();
                document.execCommand(button.dataset.richTextCommand, false);
                syncRichTextInput(composer);
            });
        });

        const linkButton = composer.querySelector('[data-rich-text-link]');
        linkButton?.addEventListener('click', () => {
            editor.focus();
            const url = window.prompt(linkButton.dataset.linkPrompt || '');

            if (!url) {
                return;
            }

            document.execCommand('createLink', false, url);
            syncRichTextInput(composer);
        });
    });
};

export const bootNotifications = () => {
    document.querySelectorAll('[data-notification-form]').forEach((form) => {
        const scope = form.querySelector('select[name="target_scope"]');
        const immediate = form.querySelector('[data-notification-immediate]');

        form.querySelector('[data-notification-select-all]')?.addEventListener('click', () => {
            form.querySelectorAll('[data-notification-tenant-checkbox]').forEach((checkbox) => {
                checkbox.checked = true;
            });

            if (scope) {
                scope.value = 'all';
            }

            syncTenants(form);
        });

        form.querySelector('[data-notification-clear-tenants]')?.addEventListener('click', () => {
            form.querySelectorAll('[data-notification-tenant-checkbox]').forEach((checkbox) => {
                checkbox.checked = false;
            });

            if (scope) {
                scope.value = 'selected';
            }

            syncTenants(form);
        });

        form.querySelectorAll('[data-notification-tenant-checkbox]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => syncTenants(form, checkbox));
        });

        scope?.addEventListener('change', () => syncTenants(form, null, 'scope'));
        immediate?.addEventListener('change', () => syncSchedule(form));
        form.querySelectorAll('[data-notification-filter]').forEach((filter) => {
            filter.addEventListener('change', () => syncTenants(form, null, 'filters'));
        });
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-notification-composer]').forEach(syncRichTextInput);
        });

        bootRichText(form);
        syncSchedule(form);
        syncTenants(form);
    });
};
