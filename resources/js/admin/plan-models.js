const syncPlanModelPicker = (form) => {
    const picker = form.querySelector('[data-plan-model-picker]');
    const planType = form.querySelector('[data-plan-type]');

    if (! picker || ! planType) {
        return;
    }

    const isCreditTopUp = planType.value === 'credit_top_up';
    const selectedServices = new Set(
        Array.from(form.querySelectorAll('[data-plan-service]:checked')).map((input) => input.value),
    );

    picker.hidden = isCreditTopUp;

    picker.querySelectorAll('[data-plan-model-group]').forEach((group) => {
        const isAvailable = ! isCreditTopUp && selectedServices.has(group.dataset.planModelGroup);

        group.hidden = ! isAvailable;
        group.querySelectorAll('[data-plan-model]').forEach((input) => {
            input.disabled = ! isAvailable;
            input.required = isAvailable;
        });
    });
};

export const bootPlanModels = () => {
    document.querySelectorAll('[data-plan-form]').forEach((form) => {
        syncPlanModelPicker(form);

        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-plan-service], [data-plan-type]')) {
                syncPlanModelPicker(form);
            }
        });
    });
};
