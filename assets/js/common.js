// Loading forms
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!form.matches('[data-loading-form]')) {
        return;
    }

    const button = form.querySelector('[data-loading-button]');

    if (!button || button.disabled) {
        return;
    }

    button.querySelector('[data-button-label]')?.classList.add('d-none');
    button.querySelector('[data-loading-label]')?.classList.remove('d-none');

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
});