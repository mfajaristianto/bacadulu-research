/*
 * Profile account security.
 * Important: stored passwords are hashes and are never revealed. The eye
 * buttons only toggle values that the user is currently typing.
 */
(() => {
    const panel = document.querySelector('[data-security-panel]');
    const openButtons = [...document.querySelectorAll('[data-security-open]')];
    const closeButton = document.querySelector('[data-security-close]');
    const error = document.querySelector('.br-security-form__error');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const openPanel = () => {
        if (!panel) return;
        panel.hidden = false;
        panel.scrollIntoView({
            behavior: reducedMotion.matches ? 'auto' : 'smooth',
            block: 'center',
        });
        window.setTimeout(() => {
            panel.querySelector('input[name="email"]')?.focus({ preventScroll: true });
        }, reducedMotion.matches ? 0 : 260);
    };

    const closePanel = () => {
        if (!panel || error) return;
        panel.hidden = true;
    };

    openButtons.forEach((button) => button.addEventListener('click', openPanel));
    closeButton?.addEventListener('click', closePanel);

    if (error) {
        openPanel();
    }

    document.querySelectorAll('[data-toggle-security-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.toggleSecurityPassword);
            if (!input) return;

            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.textContent = willShow ? 'Sembunyi' : 'Lihat';
            button.setAttribute('aria-pressed', String(willShow));
        });
    });
})();
