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
   LOGIN ↔ REGISTER — PAPER TEAR (vertikal di semua device)
   Satu sumber state: root.dataset.view. Tab, link bawah form, dock mobile,
   keyboard, dan tombol back browser semuanya memanggil requestView().
   Saat pindah, form lama di-clone jadi dua lembar (kiri/kanan) yang dipotong
   zig-zag di tengah. View langsung diganti sehingga form baru sudah ada di
   lapisan bawah; seam tergambar dari atas ke bawah, kedua lembar terbuka,
   lalu memudar dan form baru settle. Tidak ada loop/timer di luar saat
   berpindah.

   Siklus hidup: finishSwitch() idempoten (dipanggil oleh onComplete,
   onInterrupt, watchdog, dan saat tab tersembunyi) dan SELALU menghapus
   clone, mereset lock, lalu menjalankan target terakhir dari klik cepat.
   ============================================================ */

// Offset zig-zag seam (px) sepanjang sobekan; sama untuk kedua sisi.
const TEAR_SEAM = [0, 5, -4, 6, -3, 5, -6, 4, -5, 6, -3, 4, 0];

// Total ≈ 1 dtk: seam 0,2 → buka 0,5 (mulai 0,13) → memudar + settle 0,34–0,4.
const TEAR = {
    seam: .2,
    open: .5,
    fade: .34,
    settle: .4,
};

const AUTH_LABEL = {
    signin: 'Masuk',
    signup: 'Daftar',
};

function tearPolygons() {
    const last = TEAR_SEAM.length - 1;

    const seam = TEAR_SEAM
        .map((offset, index) => `calc(50% + ${offset}px) ${(index / last) * 100}%`)
        .join(', ');

    return [
        `polygon(0 0, ${seam}, 0 100%)`,
        `polygon(100% 0, ${seam}, 100% 100%)`,
    ];
}

function seamLine(width, height) {
    const last = TEAR_SEAM.length - 1;
    const ns = 'http://www.w3.org/2000/svg';

    const points = TEAR_SEAM
        .map((offset, index) => `${width / 2 + offset},${(index / last) * height}`)
        .join(' ');

    const svg = document.createElementNS(ns, 'svg');
    const line = document.createElementNS(ns, 'polyline');

    svg.setAttribute('class', 'research-auth__tear-seam');
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('aria-hidden', 'true');

    line.setAttribute('points', points);
    line.setAttribute('fill', 'none');
    line.setAttribute('stroke', 'currentColor');
    line.setAttribute('stroke-width', '1.5');
    line.setAttribute('vector-effect', 'non-scaling-stroke');

    svg.appendChild(line);

    return svg;
}

// cloneNode tidak menyalin nilai yang sedang diketik; salin ke atribut.
function copyLiveValues(source, clone) {
    const from = source.querySelectorAll('input, select');
    const to = clone.querySelectorAll('input, select');

    from.forEach((field, index) => {
        const twin = to[index];

        if (!twin) {
            return;
        }

        if (field.type === 'checkbox' || field.type === 'radio') {
            twin.toggleAttribute('checked', field.checked);

            return;
        }

        if (field.tagName === 'SELECT') {
            twin.value = field.value;

            return;
        }

        twin.setAttribute('value', field.value);
    });
}

function buildTear(forms, pane) {
    const rect = forms.getBoundingClientRect();

    // Form Daftar di mobile bisa >1000px; lembar cukup setinggi layar agar
    // layer komposit tetap kecil (yang di luar layar memang tidak terlihat).
    const width = rect.width;
    const height = Math.min(rect.height, window.innerHeight);

    const tear = document.createElement('div');

    tear.className = 'research-auth__tear';
    tear.setAttribute('aria-hidden', 'true');
    tear.inert = true;
    tear.style.width = `${width}px`;
    tear.style.height = `${height}px`;

    const halves = tearPolygons().map((path) => {
        const half = document.createElement('div');
        const sheet = document.createElement('div');
        const clone = pane.cloneNode(true);

        half.className = 'research-auth__tear-half';
        sheet.className = 'research-auth__tear-sheet';
        sheet.style.clipPath = path;

        clone.removeAttribute('data-auth-pane');
        clone.classList.add('research-auth__tear-pane');

        clone.querySelectorAll('[id]').forEach((node) => node.removeAttribute('id'));
        clone.querySelectorAll('[name]').forEach((node) => node.removeAttribute('name'));

        copyLiveValues(pane, clone);

        sheet.appendChild(clone);
        half.appendChild(sheet);
        tear.appendChild(half);

        return half;
    });

    tear.appendChild(seamLine(width, height));

    forms.appendChild(tear);

    return {
        tear,
        halves,
        seam: tear.querySelector('.research-auth__tear-seam'),
    };
}

function setupAuthSwitch(root, reduceMotion) {
    const forms = root.querySelector('.research-auth__forms');
    const nextButton = root.querySelector('[data-auth-next]');
    const status = root.querySelector('[data-auth-status]');
    const mobile = window.matchMedia('(max-width: 900px)');

    let switching = false;
    let pending = null;
    let active = null;

    const urlFor = (view) =>
        view === 'signup'
            ? root.dataset.registerUrl
            : root.dataset.loginUrl;

    // Menyelaraskan tab, dock, dan pengumuman screen reader dengan data-view.
    const syncView = () => {
        const view = root.dataset.view;
        const next = view === 'signin' ? 'signup' : 'signin';

        root.querySelectorAll('.research-auth__tab, .research-auth__dock-mode').forEach((item) => {
            if (item.dataset.authSwitch === view) {
                item.setAttribute('aria-current', 'page');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        if (nextButton) {
            nextButton.href = urlFor(next);
            nextButton.setAttribute('aria-label', `Pindah ke ${AUTH_LABEL[next]}`);
        }

        if (status) {
            status.textContent = `Mode ${AUTH_LABEL[view]}`;
        }
    };

    // Idempoten: aman dipanggil berkali-kali dari jalur mana pun.
    function finishSwitch() {
        if (!active) {
            return;
        }

        const { timeline, guard, targets } = active;

        active = null;

        timeline.kill();
        guard.kill();

        root.querySelectorAll('.research-auth__tear').forEach((node) => node.remove());

        gsap.set(targets, { clearProps: 'transform,opacity' });

        switching = false;

        if (pending) {
            const next = pending;

            pending = null;

            requestView(next.target, next.updateHistory);
        }
    }

    function requestView(target, updateHistory = true) {
        if (!['signin', 'signup'].includes(target)) {
            return;
        }

        if (switching) {
            pending = { target, updateHistory };

            return;
        }

        if (root.dataset.view === target) {
            return;
        }

        const current = root.dataset.view;
        const oldPane = root.querySelector(`[data-auth-pane="${current}"]`);
        const newPane = root.querySelector(`[data-auth-pane="${target}"]`);
        const newIntro = root.querySelector(`[data-auth-hero="${target}"] .research-auth__intro`);

        if (!forms || !oldPane || !newPane) {
            return;
        }

        const commit = () => {
            root.dataset.view = target;

            syncView();

            const url = urlFor(target);

            if (updateHistory && url) {
                history.pushState({ authView: target }, '', url);
            }
        };

        // Sisa clone dari kondisi tak terduga tidak boleh menumpuk.
        root.querySelectorAll('.research-auth__tear').forEach((node) => node.remove());

        if (reduceMotion) {
            commit();

            gsap.fromTo(
                [newPane, newIntro],
                { opacity: 0 },
                { opacity: 1, duration: .18, clearProps: 'opacity' }
            );

            return;
        }

        switching = true;

        // Dari dock di bawah halaman: bawa form ke atas agar sobekan terlihat.
        const top = forms.getBoundingClientRect().top;

        if (top < 0) {
            window.scrollTo({ top: window.scrollY + top - 72, behavior: 'instant' });
        }

        const { halves, seam } = buildTear(forms, oldPane);
        const [left, right] = halves;
        const gap = mobile.matches ? 12 : 18;
        const tilt = mobile.matches ? .5 : .8;

        commit();

        const timeline = gsap.timeline({
            defaults: { ease: 'power2.inOut' },
            onComplete: finishSwitch,
            onInterrupt: finishSwitch,
        });

        const open = TEAR.seam * .65;
        const fadeAt = open + TEAR.open * .9;

        timeline
            // 1. seam tergambar dari atas ke bawah
            .fromTo(
                seam,
                { opacity: 1, clipPath: 'inset(0% 0% 100% 0%)' },
                { clipPath: 'inset(0% 0% 0% 0%)', duration: TEAR.seam, ease: 'power1.out' },
                0
            )
            // 2. lembar terbuka ke kiri dan kanan
            .to(left, { x: -gap, rotation: -tilt, duration: TEAR.open, ease: 'power3.inOut' }, open)
            .to(right, { x: gap, rotation: tilt, duration: TEAR.open, ease: 'power3.inOut' }, open)
            .to(seam, { opacity: 0, duration: .24, ease: 'power1.in' }, open + TEAR.open * .45)
            // 3. potongan lama memudar, form baru settle
            .to(halves, { opacity: 0, duration: TEAR.fade, ease: 'power1.in' }, fadeAt)
            .fromTo(newPane, { y: 10 }, { y: 0, duration: TEAR.settle, ease: 'power3.out' }, fadeAt)
            .fromTo(newIntro, { opacity: 0, y: 6 }, { opacity: 1, y: 0, duration: TEAR.settle, ease: 'power3.out' }, fadeAt - .1);

        // Watchdog: kalau timeline tak pernah selesai (mis. tab di background),
        // lock tetap dilepas dan clone dihapus.
        active = {
            timeline,
            guard: gsap.delayedCall(timeline.duration() + .8, finishSwitch),
            targets: [newPane, newIntro],
        };
    }

    root.querySelectorAll('[data-auth-switch]').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();

            requestView(link.dataset.authSwitch, true);
        });
    });

    nextButton?.addEventListener('click', (event) => {
        event.preventDefault();

        requestView(root.dataset.view === 'signin' ? 'signup' : 'signin', true);
    });

    window.addEventListener('popstate', () => {
        requestView(
            location.pathname.includes('/register') ? 'signup' : 'signin',
            false
        );
    });

    // Tab di background menghentikan rAF; selesaikan seketika agar tidak menggantung.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && active) {
            active.timeline.progress(1);
        }
    });

    // Keyboard mobile: sembunyikan dock saat mengetik agar tidak menutupi input.
    const TYPING = 'input:not([type="checkbox"]), select, textarea';

    root.addEventListener('focusin', (event) => {
        if (event.target.matches(TYPING)) {
            root.classList.add('is-typing');
        }
    });

    root.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (!document.activeElement?.matches?.(TYPING)) {
                root.classList.remove('is-typing');
            }
        }, 80);
    });

    syncView();
}
