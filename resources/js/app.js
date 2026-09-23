const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
        return;
    }

    callback();
};

ready(() => {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    initMobileNavigation();
    initAdminNavigation();
    initPasswordToggles();
    initPromptPicker();
    initChatSidebar();
    initChatComposer();
    initAvatarPreview();
    initDateOfBirthFields();
    initProfileProgress();
    initNetworkState();
    initAvatarFallbacks();
    initToasts();

    if (!reduceMotion) {
        animateReveals();
        initMotionDetails();
    }
});

function initMobileNavigation() {
    const toggle = document.querySelector('[data-mobile-menu]');
    const panel = document.querySelector('[data-mobile-panel]');

    if (!toggle || !panel) return;

    const close = () => {
        panel.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        const open = panel.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
    });

    panel.querySelectorAll('a, button').forEach((element) => {
        element.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
}

function initAdminNavigation() {
    const toggle = document.querySelector('[data-admin-menu]');
    const nav = document.querySelector('[data-admin-nav]');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
        const open = nav.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
    });
}

function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.closest('.password-field');
            const input = wrapper?.querySelector('[data-password-input]');

            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });
}

function initPromptPicker() {
    const button = document.querySelector('[data-prompt-open]');
    const menu = document.querySelector('[data-prompt-menu]');
    const hiddenInput = document.querySelector('[data-prompt-input]');
    const label = document.querySelector('[data-prompt-label]');
    const closeButton = document.querySelector('[data-prompt-close]');

    if (!button || !menu) return;

    const options = [...menu.querySelectorAll('[data-prompt-id]')];

    const setOpen = (open) => {
        menu.classList.toggle('is-open', open);
        menu.hidden = !open;
        button.setAttribute('aria-expanded', String(open));

        if (open) {
            window.requestAnimationFrame(() => options[0]?.focus());
        }
    };

    const selectOption = (option) => {
        if (!option) return;

        options.forEach((item) => item.setAttribute('aria-selected', String(item === option)));
        if (hiddenInput) hiddenInput.value = option.dataset.promptId || '';
        if (label) label.textContent = option.dataset.promptName || 'Research prompt selected';
        setOpen(false);
        button.focus();
    };

    setOpen(false);

    button.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(menu.hidden);
    });

    closeButton?.addEventListener('click', () => {
        setOpen(false);
        button.focus();
    });

    options.forEach((option) => {
        option.addEventListener('click', () => selectOption(option));
        option.addEventListener('keydown', (event) => {
            const currentIndex = options.indexOf(option);

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                options[(currentIndex + 1) % options.length]?.focus();
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                options[(currentIndex - 1 + options.length) % options.length]?.focus();
            }

            if (event.key === 'Home') {
                event.preventDefault();
                options[0]?.focus();
            }

            if (event.key === 'End') {
                event.preventDefault();
                options.at(-1)?.focus();
            }

            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectOption(option);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target) && !button.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !menu.hidden) {
            setOpen(false);
            button.focus();
        }
    });
}

function initChatSidebar() {
    const sidebar = document.querySelector('[data-chat-sidebar]');
    const backdrop = document.querySelector('[data-chat-sidebar-backdrop]');
    const openButton = document.querySelector('[data-chat-sidebar-open]');
    const closeButton = document.querySelector('[data-chat-sidebar-close]');

    if (!sidebar || !backdrop || !openButton) return;

    const setOpen = (open) => {
        sidebar.classList.toggle('is-open', open);
        backdrop.classList.toggle('is-open', open);
        document.body.classList.toggle('is-locked', open);
        openButton.setAttribute('aria-expanded', String(open));
    };

    openButton.addEventListener('click', () => setOpen(true));
    closeButton?.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setOpen(false);
    });
}

function initChatComposer() {
    const input = document.querySelector('[data-chat-input]');
    const scroller = document.querySelector('[data-chat-scroll]');
    const form = document.querySelector('[data-chat-form]');

    const resize = () => {
        if (!input) return;
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
    };

    input?.addEventListener('input', resize);
    resize();

    input?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
        event.preventDefault();
        form?.requestSubmit();
    });

    form?.addEventListener('submit', () => {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    });

    if (scroller) {
        window.requestAnimationFrame(() => {
            scroller.scrollTop = scroller.scrollHeight;
        });
    }
}

function initAvatarPreview() {
    const input = document.querySelector('[data-avatar-input]');
    const preview = document.querySelector('[data-avatar-preview]');

    if (!input || !preview) return;

    let temporaryUrl = null;

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;

        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        temporaryUrl = URL.createObjectURL(file);

        let image = preview.querySelector('[data-avatar-image]');
        if (!image) {
            image = document.createElement('img');
            image.className = 'profile-photo-preview-img';
            image.alt = 'New profile photo preview';
            image.dataset.avatarImage = '';
            preview.appendChild(image);
        }

        image.src = temporaryUrl;
        image.hidden = false;
    });

    window.addEventListener('beforeunload', () => {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
    });
}

function initDateOfBirthFields() {
    document.querySelectorAll('[data-dob-group]').forEach((group) => {
        const month = group.querySelector('[data-dob-month]');
        const day = group.querySelector('[data-dob-day]');
        const year = group.querySelector('[data-dob-year]');
        const output = group.parentElement?.querySelector('[data-dob-output]');
        const form = group.closest('form');

        if (!month || !day || !year || !output) return;

        const sync = () => {
            if (!month.value || !day.value || !year.value) {
                output.value = '';
                return;
            }

            output.value = `${year.value}-${String(month.value).padStart(2, '0')}-${String(day.value).padStart(2, '0')}`;
        };

        [month, day, year].forEach((element) => element.addEventListener('change', sync));
        form?.addEventListener('submit', sync);
    });
}

function initProfileProgress() {
    document.querySelectorAll('[data-progress-value]').forEach((bar) => {
        const value = Math.max(0, Math.min(100, Number(bar.dataset.progressValue) || 0));
        bar.style.width = `${value}%`;
    });
}

function initNetworkState() {
    const banner = document.querySelector('[data-network-banner]');
    if (!banner) return;

    const update = () => {
        banner.hidden = navigator.onLine;
    };

    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    update();
}

function initAvatarFallbacks() {
    document.querySelectorAll('[data-avatar-image]').forEach((image) => {
        const hideBrokenImage = () => {
            image.hidden = true;
            image.setAttribute('aria-hidden', 'true');
        };

        image.addEventListener('error', hideBrokenImage);

        // The resource can fail before DOMContentLoaded. Check its completed state too.
        if (image.complete && image.naturalWidth === 0) {
            hideBrokenImage();
        }
    });
}

function initToasts() {
    const toast = document.querySelector('[data-admin-toast]');
    if (!toast) return;

    window.setTimeout(() => {
        toast.animate(
            [{ opacity: 1, transform: 'translateY(0)' }, { opacity: 0, transform: 'translateY(-8px)' }],
            { duration: 240, easing: 'ease', fill: 'forwards' },
        ).finished.then(() => toast.remove());
    }, 4200);
}

function animateReveals() {
    const elements = [...document.querySelectorAll('[data-reveal], .reveal, [data-admin-reveal]')];

    if (elements.length === 0 || !Element.prototype.animate) return;

    const offsets = {
        left: 'translate3d(-26px, 0, 0)',
        right: 'translate3d(26px, 0, 0)',
        up: 'translate3d(0, -18px, 0)',
        down: 'translate3d(0, 24px, 0)',
    };

    elements.forEach((element) => {
        element.style.opacity = '0';
        element.style.transform = offsets[element.dataset.revealFrom || 'down'] || offsets.down;
    });

    const reveal = (element) => {
        if (element.dataset.revealed === 'true') return;

        element.dataset.revealed = 'true';
        const direction = element.dataset.revealFrom || 'down';
        const offset = offsets[direction] || offsets.down;
        const delay = Math.max(0, Number(element.dataset.revealDelay) || 0);

        const animation = element.animate(
            [
                { opacity: 0, transform: `${offset} scale(.985)`, filter: 'blur(3px)' },
                { opacity: 1, transform: 'translate3d(0, 0, 0) scale(1)', filter: 'blur(0)' },
            ],
            {
                duration: 620,
                delay,
                easing: 'cubic-bezier(.22,1,.36,1)',
                fill: 'forwards',
            },
        );

        animation.finished
            .then(() => {
                element.style.opacity = '1';
                element.style.transform = '';
                element.style.filter = '';
                animation.cancel();
            })
            .catch(() => {});
    };

    if (!('IntersectionObserver' in window)) {
        elements.forEach(reveal);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            const visibleEntries = entries
                .filter((entry) => entry.isIntersecting)
                .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);

            visibleEntries.forEach((entry, index) => {
                if (!entry.target.dataset.revealDelay) {
                    entry.target.dataset.revealDelay = String(Math.min(index * 70, 280));
                }

                reveal(entry.target);
                observer.unobserve(entry.target);
            });
        },
        {
            rootMargin: '0px 0px -10% 0px',
            threshold: 0.12,
        },
    );

    elements.forEach((element) => observer.observe(element));
}

function initMotionDetails() {
    const heroCard = document.querySelector('[data-motion-card]');

    if (heroCard && window.matchMedia('(pointer: fine)').matches) {
        const reset = () => {
            heroCard.style.transform = '';
        };

        heroCard.addEventListener('pointermove', (event) => {
            const rect = heroCard.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - 0.5;
            const y = (event.clientY - rect.top) / rect.height - 0.5;
            const rotateX = y * -2.5;
            const rotateY = x * 3.5;

            heroCard.style.transform = `perspective(1100px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
        });

        heroCard.addEventListener('pointerleave', reset);
        heroCard.addEventListener('blur', reset, true);
    }

    document.querySelectorAll('[data-motion-button]').forEach((button) => {
        button.addEventListener('pointermove', (event) => {
            const rect = button.getBoundingClientRect();
            button.style.setProperty('--motion-x', `${event.clientX - rect.left}px`);
            button.style.setProperty('--motion-y', `${event.clientY - rect.top}px`);
        });
    });
}
