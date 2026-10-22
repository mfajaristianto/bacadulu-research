import { gsap } from 'gsap';
import '../css/pages/auth-register-enhancements.css';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-research-auth]');

    if (!root) {
        return;
    }

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    setupPasswordToggles(root);
    setupDomicileSelector(root);
    setupResearchInterestSelector(root);
    setupSignupProgress(root, reduceMotion);
    setupAuthSwitch(root, reduceMotion);
    setupJourneyLoops(root, reduceMotion);

    if (!reduceMotion) {
        setupAmbientMotion(root);
        setupLoginMotion(root);
        setupRegisterMotion(root);
    }
});

/* ============================================================
   PASSWORD
   ============================================================ */

function setupPasswordToggles(root) {
    root.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.closest('.research-auth__input');
            const input = wrapper?.querySelector('[data-password-input]');

            if (!input) {
                return;
            }

            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
        });
    });
}

/* ============================================================
   DOMICILE — SEARCH ALL INDONESIA REGENCIES
   ============================================================ */

function setupDomicileSelector(root) {
    const combo = root.querySelector('[data-domicile-combo]');

    if (!combo) {
        return;
    }

    const searchInput = combo.querySelector('[data-domicile-search]');
    const valueInput = combo.querySelector('[data-domicile-value]');
    const panel = combo.querySelector('[data-domicile-panel]');
    const status = combo.querySelector('[data-domicile-status]');
    const results = combo.querySelector('[data-domicile-results]');

    if (
        !searchInput ||
        !valueInput ||
        !panel ||
        !status ||
        !results
    ) {
        return;
    }

    let regions = [];
    let loaded = false;
    let loading = false;

    const DATA_URL = '/data/indonesia-regencies.json';

    const openPanel = () => {
        panel.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
    };

    const closePanel = () => {
        panel.hidden = true;
        searchInput.setAttribute('aria-expanded', 'false');
    };

    const formatRegionName = (name) => {
        return String(name || '')
            .toLowerCase()
            .replace(/\b\w/g, (letter) => letter.toUpperCase())
            .replace(/\bDki\b/g, 'DKI')
            .replace(/\bDi\b/g, 'DI');
    };

    const loadRegions = async () => {
        if (loaded || loading) {
            return;
        }

        loading = true;
        status.textContent = 'Memuat kota dan kabupaten Indonesia...';
        openPanel();

        try {
            const response = await fetch(DATA_URL, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const json = await response.json();
            const data = Array.isArray(json)
                ? json
                : (json.data ?? []);

            regions = data
                .map((region) => ({
                    id: String(region.id ?? region.code ?? ''),
                    provinceId: String(
                        region.provinceId ??
                        region.province_id ??
                        ''
                    ),
                    name: formatRegionName(region.name),
                }))
                .filter((region) => region.name)
                .sort((a, b) => a.name.localeCompare(b.name, 'id'));

            loaded = true;

            status.textContent =
                `${regions.length} kota/kabupaten tersedia.`;

            renderResults(searchInput.value);
        } catch (error) {
            console.error(
                'Failed to load Indonesia regencies:',
                error
            );

            status.textContent =
                'Data kota/kabupaten belum tersedia. Pastikan file public/data/indonesia-regencies.json sudah ada.';
        } finally {
            loading = false;
        }
    };

    const selectRegion = (region) => {
        searchInput.value = region.name;
        valueInput.value = region.name;

        valueInput.dispatchEvent(
            new Event('input', {
                bubbles: true,
            })
        );

        const wrapper = searchInput.closest('.research-auth__input');

        wrapper?.classList.add('is-filled-valid');

        closePanel();
    };

    const renderResults = (query) => {
        if (!loaded) {
            return;
        }

        const normalized = String(query || '')
            .trim()
            .toLowerCase();

        const filtered = normalized
            ? regions.filter((region) => {
                return region.name
                    .toLowerCase()
                    .includes(normalized);
            }).slice(0, 12)
            : regions.slice(0, 12);

        results.innerHTML = '';

        if (!filtered.length) {
            status.textContent =
                'Kota/kabupaten tidak ditemukan.';

            return;
        }

        status.textContent = normalized
            ? `${filtered.length} hasil teratas`
            : 'Mulai ketik untuk mencari kota/kabupaten.';

        filtered.forEach((region) => {
            const button = document.createElement('button');

            button.type = 'button';
            button.className = 'research-auth__combo-option';

            const name = document.createElement('span');
            name.textContent = region.name;

            const code = document.createElement('small');
            code.textContent = region.id;

            button.append(name, code);

            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectRegion(region);
            });

            results.appendChild(button);
        });
    };

    searchInput.addEventListener('focus', async () => {
        openPanel();
        await loadRegions();

        if (loaded) {
            renderResults(searchInput.value);
        }
    });

    searchInput.addEventListener('input', async () => {
        valueInput.value = '';

        valueInput.dispatchEvent(
            new Event('input', {
                bubbles: true,
            })
        );

        searchInput
            .closest('.research-auth__input')
            ?.classList.remove('is-filled-valid');

        openPanel();

        await loadRegions();

        if (loaded) {
            renderResults(searchInput.value);
        }
    });

    document.addEventListener('mousedown', (event) => {
        if (!combo.contains(event.target)) {
            closePanel();
        }
    });

    if (valueInput.value.trim()) {
        searchInput.value = valueInput.value.trim();

        searchInput
            .closest('.research-auth__input')
            ?.classList.add('is-filled-valid');
    }
}

/* ============================================================
   RESEARCH INTEREST — MAX 3
   ============================================================ */

function setupResearchInterestSelector(root) {
    const wrapper = root.querySelector('[data-interest-select]');

    if (!wrapper) {
        return;
    }

    const trigger = wrapper.querySelector('[data-interest-trigger]');
    const panel = wrapper.querySelector('[data-interest-panel]');
    const label = wrapper.querySelector('[data-interest-label]');
    const count = wrapper.querySelector('[data-interest-count]');
    const hidden = wrapper.querySelector('[data-interest-value]');
    const options = [
        ...wrapper.querySelectorAll('[data-interest-option]'),
    ];

    if (
        !trigger ||
        !panel ||
        !label ||
        !count ||
        !hidden
    ) {
        return;
    }

    const MAX = 3;

    const getSelected = () => {
        return options
            .filter((option) => option.checked)
            .map((option) => option.value);
    };

    const render = () => {
        const selected = getSelected();

        hidden.value = selected.join(', ');

        hidden.dispatchEvent(
            new Event('input', {
                bubbles: true,
            })
        );

        count.textContent = `${selected.length}/${MAX}`;

        if (!selected.length) {
            label.textContent = 'Pilih maksimal 3';
            trigger.classList.remove('has-value');
        } else if (selected.length === 1) {
            label.textContent = selected[0];
            trigger.classList.add('has-value');
        } else {
            label.textContent =
                `${selected[0]} +${selected.length - 1}`;

            trigger.classList.add('has-value');
        }

        options.forEach((option) => {
            option.disabled =
                !option.checked &&
                selected.length >= MAX;
        });
    };

    const openPanel = () => {
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    };

    const closePanel = () => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };

    trigger.addEventListener('click', () => {
        if (panel.hidden) {
            openPanel();
        } else {
            closePanel();
        }
    });

    options.forEach((option) => {
        option.addEventListener('change', render);
    });

    document.addEventListener('mousedown', (event) => {
        if (!wrapper.contains(event.target)) {
            closePanel();
        }
    });

    const oldValues = hidden.value
        .split(',')
        .map((value) => value.trim())
        .filter(Boolean);

    options.forEach((option) => {
        option.checked = oldValues.includes(option.value);
    });

    render();
}

/* ============================================================
   SIGNUP PROGRESS
   ============================================================ */

function setupSignupProgress(root, reduceMotion) {
    const form = root.querySelector('[data-register-form]');
    const box = root.querySelector('[data-signup-progress]');
    const valueElement = root.querySelector(
        '[data-signup-progress-value]'
    );
    const textElement = root.querySelector(
        '[data-signup-progress-text]'
    );
    const submit = root.querySelector('[data-register-submit]');
    const submitLabel = root.querySelector(
        '[data-register-submit-label]'
    );

    if (
        !form ||
        !box ||
        !valueElement ||
        !textElement ||
        !submit ||
        !submitLabel
    ) {
        return;
    }

    const nameInput = form.querySelector(
        '[data-progress-field="name"]'
    );
    const emailInput = form.querySelector(
        '[data-progress-field="email"]'
    );
    const institutionInput = form.querySelector(
        '[data-progress-field="institution"]'
    );
    const roleInput = form.querySelector(
        '[data-progress-field="role"]'
    );
    const domicileInput = form.querySelector(
        '[data-progress-field="domicile"]'
    );
    const interestInput = form.querySelector(
        '[data-progress-field="interest"]'
    );
    const passwordInput = form.querySelector(
        '[data-register-password]'
    );
    const confirmationInput = form.querySelector(
        '[data-register-confirmation]'
    );

    const progressState = {
        value: 0,
    };

    let progressTween = null;
    let lastTarget = 0;
    let currentMessage = '';

    const validEmail = (value) => {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(
            String(value || '').trim()
        );
    };

    const setInputValid = (input, valid) => {
        if (!input) {
            return;
        }

        const wrapper =
            input.closest('.research-auth__input') ||
            input.closest('.research-auth__select');

        wrapper?.classList.toggle(
            'is-filled-valid',
            valid
        );
    };

    const calculateIdentityProgress = () => {
        let total = 0;

        const nameValid =
            nameInput?.value.trim().length >= 2;

        const emailValid =
            validEmail(emailInput?.value);

        const institutionValid =
            institutionInput?.value.trim().length >= 2;

        const roleValid =
            Boolean(roleInput?.value);

        const domicileValid =
            Boolean(domicileInput?.value.trim());

        const interestValid =
            Boolean(interestInput?.value.trim());

        if (nameValid) {
            total += 20;
        }

        if (emailValid) {
            total += 20;
        }

        if (institutionValid) {
            total += 20;
        }

        if (roleValid) {
            total += 20;
        }

        if (domicileValid) {
            total += 10;
        }

        if (interestValid) {
            total += 10;
        }

        setInputValid(nameInput, nameValid);
        setInputValid(emailInput, emailValid);
        setInputValid(institutionInput, institutionValid);
        setInputValid(roleInput, roleValid);

        return total;
    };

    const passwordValid = () => {
        return Boolean(
            passwordInput &&
            passwordInput.value.length >= 8
        );
    };

    const confirmationValid = () => {
        return Boolean(
            passwordInput &&
            confirmationInput &&
            confirmationInput.value.length > 0 &&
            confirmationInput.value === passwordInput.value
        );
    };

    const getMessage = (progress) => {
        if (progress === 100) {
            return 'Data identitas pendaftaran sudah lengkap 100%.';
        }

        if (progress >= 90) {
            return 'Hampir lengkap. Satu data opsional lagi untuk mencapai 100%.';
        }

        if (progress >= 80) {
            if (!passwordValid()) {
                return 'Minimum 80% terpenuhi. Buat kata sandi minimal 8 karakter.';
            }

            if (!confirmationValid()) {
                return 'Minimum 80% terpenuhi. Pastikan konfirmasi kata sandi sama.';
            }

            return 'Minimum data sudah terpenuhi. Akun siap dibuat.';
        }

        if (progress >= 60) {
            return 'Profil baru 60%. Lengkapi Peran Utama untuk mencapai minimum 80%.';
        }

        if (progress >= 40) {
            return 'Profil belum lengkap. Minimum pendaftaran adalah 80%.';
        }

        if (progress >= 20) {
            return 'Lanjutkan melengkapi data identitas Anda.';
        }

        return 'Minimum 80% untuk membuat akun.';
    };

    const renderProgress = (value) => {
        const safe = Math.max(
            0,
            Math.min(
                100,
                Number(value) || 0
            )
        );

        box.style.setProperty(
            '--signup-progress',
            safe.toFixed(2)
        );

        valueElement.textContent =
            `${Math.round(safe)}%`;
    };

    const animateMessage = (message) => {
        if (currentMessage === message) {
            return;
        }

        currentMessage = message;

        if (reduceMotion) {
            textElement.textContent = message;
            return;
        }

        gsap.killTweensOf(textElement);

        gsap.to(textElement, {
            y: -3,
            opacity: 0,
            duration: 0.13,
            ease: 'power1.out',
            onComplete: () => {
                textElement.textContent = message;

                gsap.fromTo(
                    textElement,
                    {
                        y: 3,
                        opacity: 0,
                    },
                    {
                        y: 0,
                        opacity: 1,
                        duration: 0.25,
                        ease: 'power2.out',
                    }
                );
            },
        });
    };

    const updateSubmit = (progress) => {
        const identityReady = progress >= 80;
        const passwordReady = passwordValid();
        const confirmationReady = confirmationValid();

        const ready =
            identityReady &&
            passwordReady &&
            confirmationReady;

        submit.disabled = !ready;

        if (!identityReady) {
            submitLabel.textContent =
                `Lengkapi minimal 80% (${progress}%)`;

            return;
        }

        if (!passwordReady) {
            submitLabel.textContent =
                'Buat kata sandi minimal 8 karakter';

            return;
        }

        if (!confirmationReady) {
            submitLabel.textContent =
                'Pastikan kata sandi sudah sama';

            return;
        }

        submitLabel.textContent =
            'Buat akun';
    };

    const animateProgress = (target) => {
        const message = getMessage(target);

        box.classList.toggle(
            'is-minimum',
            target >= 80
        );

        box.classList.toggle(
            'is-complete',
            target === 100
        );

        animateMessage(message);
        updateSubmit(target);

        if (reduceMotion) {
            progressState.value = target;
            renderProgress(target);
            lastTarget = target;
            return;
        }

        if (progressTween) {
            progressTween.kill();
        }

        const difference =
            Math.abs(
                target - progressState.value
            );

        const duration = Math.min(
            .78,
            Math.max(
                .38,
                difference / 78
            )
        );

        progressTween = gsap.to(
            progressState,
            {
                value: target,
                duration,
                ease: 'power3.out',
                overwrite: true,

                onUpdate: () => {
                    renderProgress(
                        progressState.value
                    );
                },

                onComplete: () => {
                    progressState.value = target;
                    renderProgress(target);

                    if (
                        target > lastTarget
                    ) {
                        gsap.fromTo(
                            box,
                            {
                                scale: .992,
                            },
                            {
                                scale: 1,
                                duration: .32,
                                ease: 'back.out(1.8)',
                                clearProps: 'transform',
                            }
                        );
                    }

                    if (target === 100) {
                        gsap.fromTo(
                            valueElement,
                            {
                                scale: .82,
                            },
                            {
                                scale: 1,
                                duration: .42,
                                ease: 'back.out(2.4)',
                                clearProps: 'transform',
                            }
                        );
                    }

                    lastTarget = target;
                },
            }
        );
    };

    const calculate = () => {
        const progress =
            calculateIdentityProgress();

        animateProgress(progress);
    };

    const watched = [
        nameInput,
        emailInput,
        institutionInput,
        roleInput,
        domicileInput,
        interestInput,
        passwordInput,
        confirmationInput,
    ].filter(Boolean);

    watched.forEach((field) => {
        field.addEventListener('input', calculate);
        field.addEventListener('change', calculate);
    });

    form.addEventListener('submit', (event) => {
        const progress =
            calculateIdentityProgress();

        if (
            progress < 80 ||
            !passwordValid() ||
            !confirmationValid()
        ) {
            event.preventDefault();

            calculate();

            submit.animate(
                [
                    {
                        transform: 'translateX(0)',
                    },
                    {
                        transform: 'translateX(-4px)',
                    },
                    {
                        transform: 'translateX(4px)',
                    },
                    {
                        transform: 'translateX(0)',
                    },
                ],
                {
                    duration: 260,
                }
            );
        }
    });

    const initial =
        calculateIdentityProgress();

    progressState.value = initial;
    lastTarget = initial;

    renderProgress(initial);
    animateMessage(
        getMessage(initial)
    );
    updateSubmit(initial);

    box.classList.toggle(
        'is-minimum',
        initial >= 80
    );

    box.classList.toggle(
        'is-complete',
        initial === 100
    );
}

/* ============================================================
   LOGIN ↔ REGISTER SLIDE
   ============================================================ */

function setupAuthSwitch(root, reduceMotion) {
    const shell =
        root.querySelector('.research-auth__shell');

    if (!shell) {
        return;
    }

    let wave =
        shell.querySelector(
            '.research-auth__transition-wave'
        );

    if (!wave) {
        wave =
            document.createElement('div');

        wave.className =
            'research-auth__transition-wave';

        wave.setAttribute(
            'aria-hidden',
            'true'
        );

        shell.appendChild(wave);
    }

    let switching = false;

    root.querySelectorAll(
        '[data-auth-switch]'
    ).forEach((link) => {
        link.addEventListener(
            'click',
            (event) => {
                event.preventDefault();

                changeView(
                    link.dataset.authSwitch,
                    true
                );
            }
        );
    });

    window.addEventListener(
        'popstate',
        () => {
            const target =
                location.pathname.includes('/register')
                    ? 'signup'
                    : 'signin';

            changeView(target, false);
        }
    );

    function changeView(
        target,
        updateHistory = true
    ) {
        if (
            !['signin', 'signup'].includes(target)
        ) {
            return;
        }

        const current =
            root.dataset.view;

        if (
            current === target ||
            switching
        ) {
            return;
        }

        const oldHero =
            root.querySelector(
                `[data-auth-hero="${current}"]`
            );

        const newHero =
            root.querySelector(
                `[data-auth-hero="${target}"]`
            );

        const oldPane =
            root.querySelector(
                `[data-auth-pane="${current}"]`
            );

        const newPane =
            root.querySelector(
                `[data-auth-pane="${target}"]`
            );

        if (
            !oldHero ||
            !newHero ||
            !oldPane ||
            !newPane
        ) {
            return;
        }

        const direction =
            target === 'signup'
                ? 1
                : -1;

        const url =
            target === 'signup'
                ? root.dataset.registerUrl
                : root.dataset.loginUrl;

        if (reduceMotion) {
            root.dataset.view = target;

            newPane.scrollTop = 0;

            if (
                updateHistory &&
                url
            ) {
                history.pushState(
                    {},
                    '',
                    url
                );
            }

            return;
        }

        switching = true;

        gsap.set(
            [newHero, newPane],
            {
                visibility: 'visible',
                pointerEvents: 'none',
            }
        );

        gsap.set(
            newHero,
            {
                x: 86 * direction,
                opacity: 0,
                filter: 'blur(4px)',
            }
        );

        gsap.set(
            newPane,
            {
                x: 65 * direction,
                opacity: 0,
                filter: 'blur(3px)',
            }
        );

        gsap.set(
            wave,
            {
                opacity: 0,
                xPercent:
                    direction === 1
                        ? -145
                        : 245,
            }
        );

        const timeline =
            gsap.timeline({
                defaults: {
                    ease: 'power3.inOut',
                },

                onComplete: () => {
                    root.dataset.view = target;

                    gsap.set(
                        [
                            oldHero,
                            oldPane,
                            newHero,
                            newPane,
                        ],
                        {
                            clearProps:
                                'transform,opacity,visibility,pointerEvents,filter',
                        }
                    );

                    gsap.set(
                        wave,
                        {
                            opacity: 0,
                        }
                    );

                    newPane.scrollTop = 0;

                    if (
                        updateHistory &&
                        url
                    ) {
                        history.pushState(
                            {
                                authView: target,
                            },
                            '',
                            url
                        );
                    }

                    switching = false;
                },
            });

        timeline
            .to(
                wave,
                {
                    opacity: .94,
                    duration: .1,
                },
                0
            )

            .to(
                wave,
                {
                    xPercent:
                        direction === 1
                            ? 245
                            : -145,
                    duration: .72,
                    ease: 'power2.inOut',
                },
                0
            )

            .to(
                wave,
                {
                    opacity: 0,
                    duration: .17,
                },
                .56
            )

            .to(
                oldHero,
                {
                    x: -90 * direction,
                    opacity: 0,
                    filter: 'blur(4px)',
                    duration: .33,
                },
                .03
            )

            .to(
                oldPane,
                {
                    x: -68 * direction,
                    opacity: 0,
                    filter: 'blur(3px)',
                    duration: .30,
                },
                .04
            )

            .to(
                newHero,
                {
                    x: 0,
                    opacity: 1,
                    filter: 'blur(0px)',
                    duration: .42,
                    ease: 'power3.out',
                },
                .22
            )

            .to(
                newPane,
                {
                    x: 0,
                    opacity: 1,
                    filter: 'blur(0px)',
                    duration: .39,
                    ease: 'power3.out',
                },
                .25
            );
    }
}

/* ============================================================
   JOURNEY
   ============================================================ */

function setupJourneyLoops(
    root,
    reduceMotion
) {
    root.querySelectorAll(
        '[data-auth-journey]'
    ).forEach((journey) => {
        const steps = [
            ...journey.querySelectorAll(
                '[data-auth-journey-step]'
            ),
        ];

        const progress =
            journey.querySelector(
                '[data-auth-journey-progress]'
            );

        if (!steps.length) {
            return;
        }

        let index = 0;

        const render = () => {
            const percentage =
                steps.length > 1
                    ? (
                        index /
                        (steps.length - 1)
                    ) * 100
                    : 0;

            if (progress) {
                progress.style.width =
                    `${percentage}%`;
            }

            steps.forEach((step, stepIndex) => {
                step.classList.toggle(
                    'is-past',
                    stepIndex < index
                );

                step.classList.toggle(
                    'is-current',
                    stepIndex === index
                );

                step.classList.toggle(
                    'is-future',
                    stepIndex > index
                );
            });

            if (
                !reduceMotion &&
                steps[index]
            ) {
                const dot =
                    steps[index].querySelector('i');

                if (dot) {
                    gsap.fromTo(
                        dot,
                        {
                            scale: .76,
                        },
                        {
                            scale: 1.2,
                            duration: .34,
                            ease: 'back.out(2)',
                            clearProps: 'transform',
                        }
                    );
                }
            }
        };

        render();

        if (!reduceMotion) {
            window.setInterval(
                () => {
                    index =
                        (index + 1) %
                        steps.length;

                    render();
                },
                1450
            );
        }
    });
}

/* ============================================================
   AMBIENT MOTION
   ============================================================ */

function setupAmbientMotion(root) {
    animateBlob(
        root,
        '.research-auth__blob--coral',
        20,
        16,
        7
    );

    animateBlob(
        root,
        '.research-auth__blob--teal',
        -20,
        15,
        6.3
    );

    animateBlob(
        root,
        '.research-auth__blob--blue',
        -23,
        19,
        8
    );

    animateBlob(
        root,
        '.research-auth__blob--peach',
        -18,
        -16,
        9
    );

    gsap.to(
        root.querySelectorAll(
            '.research-auth__particle'
        ),
        {
            y: -10,
            x: 4,
            rotation: 8,
            duration: 3.1,
            stagger: .32,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        }
    );

    const grid =
        root.querySelector(
            '[data-auth-grid]'
        );

    if (grid) {
        gsap.to(
            grid,
            {
                backgroundPosition:
                    '39px 39px',
                duration: 15,
                repeat: -1,
                ease: 'none',
            }
        );
    }
}

function animateBlob(
    root,
    selector,
    x,
    y,
    duration
) {
    const element =
        root.querySelector(selector);

    if (!element) {
        return;
    }

    gsap.to(
        element,
        {
            x,
            y,
            duration,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        }
    );
}

/* ============================================================
   LOGIN VISUAL MOTION
   ============================================================ */

function setupLoginMotion(root) {
    const workspace =
        root.querySelector(
            '[data-login-workspace]'
        );

    const search =
        root.querySelector(
            '[data-search-orb]'
        );

    const evidence =
        root.querySelector(
            '[data-evidence-card]'
        );

    const navigation =
        root.querySelector(
            '[data-evidence-nav]'
        );

    if (workspace) {
        gsap.to(
            workspace,
            {
                y: -7,
                duration: 3.2,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            }
        );
    }

    if (search) {
        gsap.to(
            search,
            {
                x: 3,
                y: -8,
                rotation: 8,
                scale: 1.04,
                duration: 2.5,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            }
        );
    }

    if (navigation) {
        gsap.to(
            navigation,
            {
                x: 2,
                duration: 1.7,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            }
        );
    }

    if (evidence) {
        gsap.timeline({
            repeat: -1,
            repeatDelay: .45,
        })
            .to(
                evidence,
                {
                    x: 3,
                    backgroundColor:
                        'rgba(32,170,166,.15)',
                    duration: .55,
                }
            )
            .to(
                evidence,
                {
                    x: 0,
                    backgroundColor:
                        'rgba(32,170,166,.09)',
                    duration: .60,
                }
            );
    }

    root.querySelectorAll(
        '[data-source-lines] span'
    ).forEach((line, index) => {
        gsap.to(
            line,
            {
                scaleX:
                    index === 0
                        ? .84
                        : .90,

                opacity: .70,

                duration:
                    1.55 +
                    (index * .20),

                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            }
        );
    });
}

/* ============================================================
   REGISTER VISUAL MOTION
   ============================================================ */

function setupRegisterMotion(root) {
    root.querySelectorAll(
        '[data-flow-path]'
    ).forEach((path, index) => {
        gsap.set(
            path,
            {
                strokeDashoffset:
                    40 +
                    (index * 4),
            }
        );

        gsap.to(
            path,
            {
                strokeDashoffset: 0,
                duration:
                    1.8 +
                    (index * .12),
                repeat: -1,
                ease: 'none',
            }
        );
    });

    root.querySelectorAll(
        '[data-flow-card]'
    ).forEach((card, index) => {
        gsap.to(
            card,
            {
                y:
                    index % 2 === 0
                        ? -5
                        : 5,

                x:
                    index % 2 === 0
                        ? 2
                        : -2,

                rotation:
                    index % 2 === 0
                        ? -.6
                        : .6,

                duration:
                    2.8 +
                    (index * .25),

                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            }
        );
    });

    const note =
        root.querySelector(
            '[data-research-note]'
        );

    if (note) {
        gsap.to(
            note,
            {
                y: -6,
                duration: 3.25,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            }
        );
    }
}