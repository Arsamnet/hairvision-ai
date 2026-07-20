const syncImageProcessingFields = (container) => {
    const toggle = container.querySelector('[data-input-image-processing-toggle]');
    const fieldContainers = container.querySelectorAll('[data-input-image-processing-field]');

    if (! (toggle instanceof HTMLInputElement)) {
        return;
    }

    fieldContainers.forEach((fieldContainer) => {
        const input = fieldContainer.querySelector('input');

        if (! (input instanceof HTMLInputElement)) {
            return;
        }

        input.required = toggle.checked;
        input.setAttribute('aria-required', toggle.checked ? 'true' : 'false');
        fieldContainer.classList.toggle('opacity-60', ! toggle.checked);
    });
};

export const bootAiModelImageProcessing = () => {
    document.querySelectorAll('[data-input-image-processing]').forEach((container) => {
        syncImageProcessingFields(container);

        container.querySelector('[data-input-image-processing-toggle]')?.addEventListener('change', () => {
            syncImageProcessingFields(container);
        });
    });
};
