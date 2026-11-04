/*
|--------------------------------------------------------------------------
| Admin console behaviour
|--------------------------------------------------------------------------
| Loaded by layouts/admin.blade.php and layouts/admin-auth.blade.php.
| These used to be inline <script> blocks, which the Content Security
| Policy (script-src without 'unsafe-inline') blocked from running.
*/

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
};

// Tables for initAdminGuardian(); declared here because ready() below may run it immediately.
const GUARDIAN_PLAN = {
    desktop: { x0: -120, y0: 18, s0: 0.25, amp: 1, run: 1400, settle: 220, hold: 280, wave: 600, bubble: 360 },
    mobile: { x0: -38, y0: 8, s0: 0.55, amp: 0.7, run: 800, settle: 150, hold: 150, wave: 400, bubble: 280 },
};

// Travel + growth, keyed to the foot contacts: [time share, x progress, scale progress].
// Each stride covers more ground than the last (acceleration); the final one eases out (deceleration).
const GUARDIAN_TRAVEL = [[0, 0, 0], [0.236, 0.118, 0.227], [0.486, 0.355, 0.493], [0.729, 0.671, 0.76], [1, 1, 1]];
const GUARDIAN_TRAVEL_EASE = ['ease-in', 'linear', 'linear', 'cubic-bezier(.25,.45,.45,1)'];

// Cadence: foot contact (c) -> apex (a) -> contact ... -> stop (e).
// [time share, scale progress, bob px at full size, rotate deg, kind]
const GUARDIAN_STEPS = [
    [0, 0, 0, -2, 'c'], [0.118, 0.113, -5, 0, 'a'],
    [0.236, 0.227, 2, 1.5, 'c'], [0.361, 0.36, -6, 0.5, 'a'],
    [0.486, 0.493, 1, -1.2, 'c'], [0.607, 0.627, -4, -0.3, 'a'],
    [0.729, 0.76, 0.5, 0.3, 'c'], [0.857, 0.88, -2.2, -0.4, 'a'],
    [1, 1, 0, 0, 'e'],
];
// kind -> [squash x, squash y, shadow width, shadow opacity]: the shadow widens on contact, shrinks in the air.
const GUARDIAN_POSE = { c: [1.015, 0.985, 1.1, 1], a: [0.99, 1.01, 0.8, 0.55], e: [1, 1, 1, 1] };
const GUARDIAN_UP = 'cubic-bezier(.2,.65,.4,1)';
const GUARDIAN_DOWN = 'cubic-bezier(.6,0,.8,.4)';

ready(() => {
    initAdminSidebar();
    initFileInputLabels();
    initAdminPasswordToggles();
    initAdminGuardian();
    initSourcePdfPreview();
});

/* Off-canvas sidebar below 900px (layouts/admin.blade.php). */
function initAdminSidebar() {
    const sidebar = document.querySelector('[data-admin-sidebar]');

    if (!sidebar) {
        return;
    }

    const overlay = document.querySelector('[data-admin-overlay]');
    const openButton = document.querySelector('[data-admin-sidebar-open]');
    const closeButton = document.querySelector('[data-admin-sidebar-close]');
    const mobileQuery = window.matchMedia('(max-width: 900px)');

    const setSidebar = (open) => {
        const nextOpen = Boolean(open && mobileQuery.matches);

        sidebar.classList.toggle('is-open', nextOpen);
        overlay?.classList.toggle('is-visible', nextOpen);
        document.body.classList.toggle('admin-menu-open', nextOpen);
        openButton?.setAttribute('aria-expanded', String(nextOpen));
    };

    openButton?.addEventListener('click', () => setSidebar(true));
    closeButton?.addEventListener('click', () => setSidebar(false));
    overlay?.addEventListener('click', () => setSidebar(false));

    document.querySelectorAll('.bd-admin__nav a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileQuery.matches) {
                setSidebar(false);
            }
        });
    });

    window.addEventListener('resize', () => {
        if (!mobileQuery.matches) {
            setSidebar(false);
        }
    }, { passive: true });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebar(false);
        }
    });

    setSidebar(false);
}

/* Shows the chosen file name next to [data-file-input] fields. */
function initFileInputLabels() {
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const target = document.querySelector(input.dataset.fileTarget);
            const file = input.files?.[0];

            if (!target) {
                return;
            }

            if (!file) {
                target.textContent = 'Belum ada PDF dipilih';

                return;
            }

            const sizeMb = (file.size / 1024 / 1024).toFixed(1);

            target.textContent = `${file.name} · ${sizeMb} MB`;
        });
    });
}

/* "Lihat / Sembunyikan" on the admin login (layouts/admin-auth.blade.php). */
function initAdminPasswordToggles() {
    document.querySelectorAll('[data-admin-password-toggle]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const input = document.getElementById(button.getAttribute('aria-controls'));

            if (!input) {
                return;
            }

            const show = input.type === 'password';

            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Sembunyikan' : 'Lihat';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
        });
    });
}

/*
 * Bubu Admin Guardian on the admin login (admin/auth/login.blade.php), played once per load:
 * hidden -> running -> arrived -> waving -> greeting -> idle (data-guardian-state; admin-auth.css
 * styles each state). Only transform / opacity are animated, plus a short blur at the very start.
 */
function initAdminGuardian() {
    const root = document.querySelector('[data-guardian]');

    if (!root) {
        return;
    }

    if (matchMedia('(prefers-reduced-motion: reduce)').matches || !root.animate) {
        root.dataset.guardianState = 'idle';

        return;
    }

    playGuardian(root);
}

async function playGuardian(root) {
    const p = GUARDIAN_PLAN[matchMedia('(max-width: 760px)').matches ? 'mobile' : 'desktop'];
    const part = (name) => root.querySelector(`[data-guardian-${name}]`);
    const [run, bob, shadow, bubble] = ['run', 'bob', 'shadow', 'bubble'].map(part);
    const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
    const done = (animation) => animation.finished.catch(() => {});
    const setState = (state) => { root.dataset.guardianState = state; };
    const scaleAt = (progress) => p.s0 + (1 - p.s0) * progress;

    // Start only once both images are decoded (no pop-in) and the panel has been empty for a beat.
    await Promise.all([sleep(250), ...[...root.querySelectorAll('img')].map((img) => img.decode().catch(() => {}))]);

    if (document.hidden) {
        await new Promise((resolve) => document.addEventListener('visibilitychange', resolve, { once: true }));
    }

    const travel = GUARDIAN_TRAVEL.map(([offset, x, s], i) => ({
        offset,
        easing: GUARDIAN_TRAVEL_EASE[i] ?? 'linear',
        transform: `translate(${p.x0 * (1 - x)}px, ${p.y0 * (1 - x)}px) scale(${scaleAt(s)})`,
    }));
    // The bob sits inside the scaled layer, so its px are divided by the scale to read the same on screen.
    const cadence = GUARDIAN_STEPS.map(([offset, sp, y, rotate, kind]) => {
        const s = scaleAt(sp);
        const [sx, sy, shadowWidth, shadowOpacity] = GUARDIAN_POSE[kind];
        const easing = kind === 'a' ? GUARDIAN_DOWN : GUARDIAN_UP;

        return {
            body: { offset, easing, transform: `translateY(${(y * p.amp * (0.4 + 0.6 * s)) / s}px) rotate(${rotate}deg) scale(${sx}, ${sy})` },
            shadow: { offset, easing, transform: `scaleX(${shadowWidth})`, opacity: shadowOpacity },
        };
    });
    const appear = [
        { opacity: 0, filter: 'blur(2px)' },
        { opacity: 1, filter: 'blur(1.5px)', offset: 0.09 },
        { opacity: 1, filter: 'blur(0px)', offset: 0.19 },
        { opacity: 1, filter: 'blur(0px)' },
    ];
    const timing = { duration: p.run, fill: 'backwards' };

    // Created before the state flips, so the first painted frame is already the tiny, invisible start.
    const arrival = run.animate(travel, timing);
    run.animate(appear, timing);
    bob.animate(cadence.map((c) => c.body), timing);
    shadow.animate(cadence.map((c) => c.shadow), timing);
    setState('running');
    await done(arrival);

    setState('arrived');
    await done(bob.animate([
        { transform: 'translateY(0) rotate(0deg)' },
        { transform: 'translateY(-2px) rotate(.7deg)', offset: 0.4 },
        { transform: 'translateY(0) rotate(0deg)' },
    ], { duration: p.settle, easing: 'ease-out' }));
    await sleep(p.hold);

    // The wave pose has its own arm raised; the body only gets a small sway so the PNG is not deformed.
    setState('waving');
    const sway = (y, rotate, scale, offset) => ({ transform: `translateY(${y}px) rotate(${rotate}deg) scale(${scale})`, easing: 'ease-in-out', offset });
    await done(bob.animate([
        sway(0, 0, 1, 0), sway(-1.5, -1, 1.01, 0.25), sway(-1, 1, 1, 0.5), sway(-1.5, -0.8, 1.01, 0.75), sway(0, 0, 1, 1),
    ], { duration: p.wave }));

    const reveal = 'circle(80% at 53% 38%)';
    const greeting = bubble.animate([
        { opacity: 0, clipPath: 'circle(0% at 53% 38%)', transform: 'translateY(6px) scale(.9)' },
        { opacity: 1, clipPath: reveal, transform: 'translateY(0) scale(1.025)', offset: 0.7 },
        { opacity: 1, clipPath: reveal, transform: 'translateY(0) scale(1)' },
    ], { duration: p.bubble, easing: 'ease-out', fill: 'backwards' });
    setState('greeting');
    await done(greeting);
    setState('idle');

    // A failed login reloads this page, so the error gets one small shake once Bubu has greeted.
    if (document.getElementById('admin-login-error')) {
        root.animate([
            { transform: 'none' }, { transform: 'translateX(-4px)', offset: 0.2 }, { transform: 'translateX(4px)', offset: 0.4 },
            { transform: 'translateX(-4px)', offset: 0.6 }, { transform: 'translateX(4px)', offset: 0.8 }, { transform: 'none' },
        ], { duration: 360, easing: 'ease-out' });
    }
}

/* PDF dropzone + live paper preview (admin/library/form.blade.php). */
function initSourcePdfPreview() {
    const wrapper = document.querySelector('[data-source-upload]');
    const input = document.querySelector('[data-source-pdf-input]');
    const titleInput = document.querySelector('[data-source-title-input]');
    const dropzone = document.querySelector('[data-source-dropzone]');
    const previewTitle = document.querySelector('[data-pdf-preview-title]');
    const previewFile = document.querySelector('[data-pdf-preview-file]');
    const previewMeta = document.querySelector('[data-pdf-preview-meta]');
    const fileText = document.querySelector('#source-file-name');

    if (!wrapper || !input || !titleInput || !previewTitle) {
        return;
    }

    let currentFileName = previewFile?.textContent?.trim() || '';

    const isPdf = (file) => file.type === 'application/pdf' || /\.pdf$/i.test(file.name);

    const cleanFileName = (name) => String(name || '')
        .replace(/\.pdf$/i, '')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const titleForPreview = () => {
        const typedTitle = titleInput.value.trim();

        if (typedTitle) {
            return typedTitle;
        }

        const fromFile = cleanFileName(currentFileName);

        if (fromFile && fromFile !== 'Belum ada PDF') {
            return fromFile;
        }

        return 'Judul Source';
    };

    const syncTitle = () => {
        previewTitle.textContent = titleForPreview();
    };

    const animateReceived = () => {
        wrapper.classList.remove('is-receiving');
        void wrapper.offsetWidth;
        wrapper.classList.add('is-receiving');

        window.setTimeout(() => wrapper.classList.remove('is-receiving'), 1000);
    };

    const applyFile = (file, updateInput = false) => {
        if (!file) {
            return;
        }

        if (!isPdf(file)) {
            window.alert('File harus berupa PDF.');

            return;
        }

        if (updateInput) {
            try {
                const transfer = new DataTransfer();

                transfer.items.add(file);
                input.files = transfer.files;
            } catch (error) {
                /* Some browsers restrict assigning a FileList. */
            }
        }

        currentFileName = file.name;

        const cleanTitle = cleanFileName(file.name);

        if (!titleInput.value.trim() && cleanTitle) {
            titleInput.value = cleanTitle;
        }

        const sizeMb = (file.size / 1024 / 1024).toFixed(2);

        if (previewFile) {
            previewFile.textContent = file.name;
        }

        if (previewMeta) {
            previewMeta.textContent = `${sizeMb} MB · PDF dipilih`;
        }

        if (fileText) {
            fileText.textContent = `${file.name} · ${sizeMb} MB`;
        }

        wrapper.classList.add('has-file');

        syncTitle();
        animateReceived();
    };

    titleInput.addEventListener('input', syncTitle);

    input.addEventListener('change', () => {
        const file = input.files?.[0];

        if (!file) {
            syncTitle();

            return;
        }

        applyFile(file);
    });

    if (dropzone) {
        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, () => dropzone.classList.remove('is-dragging'));
        });

        dropzone.addEventListener('drop', (event) => {
            event.preventDefault();

            const pdf = Array.from(event.dataTransfer?.files || []).find(isPdf);

            if (!pdf) {
                window.alert('Drag file PDF ke area upload.');

                return;
            }

            applyFile(pdf, true);
        });
    }

    syncTitle();
}
