export const bootLandingTemplateForm = () => {
    const picker = document.querySelector('[data-landing-template-color]');
    const input = document.querySelector('input[name="primary_color"]');

    if (!picker || !input) return;

    picker.addEventListener('input', () => {
        input.value = picker.value;
    });

    input.addEventListener('input', () => {
        if (/^#[0-9a-f]{6}$/i.test(input.value)) picker.value = input.value;
    });
};
