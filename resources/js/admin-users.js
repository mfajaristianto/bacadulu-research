/*
 * Pengguna (admin/users). Dimuat sesuai kebutuhan oleh admin.js saat halaman [data-us] terbuka.
 *
 * Peningkatan progresif: semua tautan dan form adalah GET nyata yang tetap bekerja tanpa JavaScript. Dengan JS dokumen tidak dimuat ulang: region
 * [data-us-swap] diganti dari salinan URL yang sama, bilah alamat mengikuti lewat History API (Back/Forward berfungsi), permintaan lama dibatalkan
 * (AbortController), dan kegagalan menampilkan "Coba lagi". Detail pengguna dimuat saat dibuka (JSON berisi HTML yang dirender server, semua nilai
 * di-escape Blade). Satu-satunya mutasi adalah status akun (PATCH + CSRF) dengan konfirmasi eksplisit.
 * Gerak memakai transform/opacity saja dan dimatikan oleh prefers-reduced-motion; nilai akhir selalu tampil penuh.
 */

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const NS = 'http://www.w3.org/2000/svg';
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const nf = new Intl.NumberFormat('id-ID');
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

let root = null;
let controller = null;
let current = '';
let timer = 0;
let trigger = null;
let serie = 'baru';
let detailController = null;
let attempt = null; // permintaan terakhir {target, mode, options}; "Coba lagi" mengulang tujuan yang gagal dengan filter yang sama
let filterOpen = false;
let toastTimer = 0;
let chart = null;
let tween = 0;

const here = () => location.pathname + location.search;
const plain = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
const withoutUser = (href) => {
    const url = new URL(href, location.href);

    url.searchParams.delete('pengguna');

    return url.pathname + url.search;
};
const userOf = (href) => new URL(href, location.href).searchParams.get('pengguna');

export function initAdminUsers() {
    root = $('[data-us]');

    if (!root) {
        return;
    }

    current = here();
    history.replaceState({ us: 'list' }, '', location.href);

    wire();
    afterSwap(new Map(), true);

    // Gerak masuk hanya sekali; kelas dilepas agar region yang diganti kemudian tidak mengulang animasi.
    setTimeout(() => root.classList.remove('is-entering'), 1100);

    const drawer = $('[data-us-drawer]');

    // Deep link ?pengguna=ID dirender server sebagai dialog terbuka non-modal; jadikan modal agar fokus terkunci dan Esc berfungsi.
    if (drawer?.open && $('[data-us-detail-id]')) {
        drawer.close();
        openDrawer();
        initTabs();
    }
}

/* ------------------------------------------------------------------------------------------ navigasi async */

async function go(target, mode = 'push', { keepFilters = false } = {}) {
    const url = new URL(target, location.href);

    if (url.origin !== location.origin) {
        location.assign(target);

        return;
    }

    attempt = { target, mode, options: { keepFilters } };
    controller?.abort();
    controller = new AbortController();
    const mine = controller;
    const previous = counts();
    const focusName = document.activeElement?.matches?.('select[name]') ? document.activeElement.name : null;

    root.classList.add('is-loading');
    root.setAttribute('aria-busy', 'true');
    hideError();

    try {
        const response = await fetch(url, { headers: HEADERS_HTML, credentials: 'same-origin', signal: mine.signal });

        if (new URL(response.url).pathname !== url.pathname) {
            location.assign(target); // sesi berakhir: navigasi nyata menampilkan halaman masuk

            return;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const next = new DOMParser().parseFromString(await response.text(), 'text/html');

        swap(next, keepFilters);

        const path = url.pathname + url.search;

        if (mode === 'push' && path !== here()) {
            history.pushState({ us: 'list' }, '', path);
        } else if (mode === 'replace') {
            history.replaceState({ us: 'list' }, '', path);
        }

        current = here();
        afterSwap(previous, false);

        if (focusName) {
            $(`select[name="${focusName}"]`, root)?.focus({ preventScroll: true });
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            showError(error.message.startsWith('HTTP 5') ? 'Server gagal memproses permintaan. Filter Anda tidak hilang; coba lagi.' : 'Koneksi terputus atau data tidak dapat dimuat. Periksa jaringan lalu coba lagi.');
        }
    } finally {
        if (controller === mine) {
            root.classList.remove('is-loading');
            root.removeAttribute('aria-busy');
        }
    }
}

function swap(next, keepFilters) {
    $$('[data-us-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-us-swap="${region.dataset.usSwap}"]`);

        if (!fresh) {
            return;
        }

        // Saat mengetik di kolom cari, form tidak diganti (fokus dan kursor tetap); hanya tombol "Atur ulang" yang disegarkan.
        if (keepFilters && region.dataset.usSwap === 'filters') {
            $('[data-us-reset-slot]', region)?.replaceWith(document.importNode($('[data-us-reset-slot]', fresh), true));

            return;
        }

        region.replaceWith(document.importNode(fresh, true));
    });

    const notice = $('[data-us-notice]');
    const freshNotice = next.querySelector('[data-us-notice]');

    if (notice && freshNotice) {
        notice.textContent = freshNotice.textContent.trim();
        notice.hidden = freshNotice.hidden;
    }
}

function afterSwap(previous, first) {
    animateCounters(previous, first);
    drawChart(true);
    resetDonut();

    const form = $('[data-us-filters]');

    if (form && filterOpen) {
        form.classList.add('is-open');
        $('[data-us-filter-toggle]', form)?.setAttribute('aria-expanded', 'true');
    }

    markSelected();
}

function markSelected() {
    const id = $('[data-us-detail-id]')?.dataset.usDetailId;

    $$('.bd-us__item').forEach((item) => item.classList.toggle('is-selected', Boolean(id) && item.querySelector(`[data-us-id="${id}"]`) !== null));
}

/* ----------------------------------------------------------------------------------------------- galat */

function showError(message) {
    const box = $('[data-us-error]');

    if (box) {
        $('[data-us-error-text]', box).textContent = message;
        box.hidden = false;
    }
}

function hideError() {
    const box = $('[data-us-error]');

    if (box) {
        box.hidden = true;
    }
}

function toast(message, bad = false) {
    const box = $('[data-us-toast]');

    if (!box) {
        return;
    }

    clearTimeout(toastTimer);
    box.hidden = true;
    box.classList.toggle('is-bad', bad);
    box.textContent = message;
    // Pemicu ulang animasi masuk tanpa menggeser tata letak (elemen fixed).
    requestAnimationFrame(() => {
        box.hidden = false;
    });
    toastTimer = setTimeout(() => {
        box.hidden = true;
    }, bad ? 6000 : 3800);
}

/* --------------------------------------------------------------------------------------------- peristiwa */

function formUrl(form) {
    const url = new URL(form.action, location.href);
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        if (String(value).trim() !== '') {
            params.append(key, String(value).trim());
        }
    });

    if (params.get('urut') === 'email') {
        params.delete('urut');
    }

    url.search = params.toString();

    return url.pathname + url.search;
}

function wire() {
    root.addEventListener('click', (event) => {
        // Pada <a> milik SVG, `target` adalah objek; baca atributnya.
        const link = event.target.closest('a[data-us-nav]');

        if (link && plain(event) && !link.getAttribute('target')) {
            event.preventDefault();
            go(link.getAttribute('href'));

            return;
        }

        const row = event.target.closest('a[data-us-detail]');

        if (row && plain(event)) {
            event.preventDefault();
            trigger = row;
            loadDetail(row.dataset.usDetail, row.getAttribute('href'));

            return;
        }

        if (event.target.closest('[data-us-close]')) {
            closeDrawer();

            return;
        }

        if (event.target.closest('[data-us-retry]')) {
            go(attempt?.target ?? here(), attempt?.mode ?? 'none', attempt?.options);

            return;
        }

        const toggle = event.target.closest('[data-us-custom-toggle]');

        if (toggle) {
            const form = $('#us-custom');
            const open = form.hidden;

            form.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));

            if (open) {
                $('input[name="dari"]', form)?.focus();
            }

            return;
        }

        const filterToggle = event.target.closest('[data-us-filter-toggle]');

        if (filterToggle) {
            filterOpen = !filterOpen;
            filterToggle.closest('form').classList.toggle('is-open', filterOpen);
            filterToggle.setAttribute('aria-expanded', String(filterOpen));

            return;
        }

        const serieButton = event.target.closest('[data-us-serie]');

        if (serieButton) {
            selectSerie(serieButton.dataset.usSerie);

            return;
        }

        const tab = event.target.closest('[data-us-tab]');

        if (tab) {
            activateTab(tab.dataset.usTab);

            return;
        }

        const copy = event.target.closest('[data-us-copy]');

        if (copy) {
            copyId(copy);
        }

        // Klik pada latar dialog (di luar panel) menutup detail.
        if (event.target.matches('dialog[data-us-drawer]')) {
            closeDrawer();
        }
    });

    root.addEventListener('submit', (event) => {
        const status = event.target.closest('form[data-us-status-form]');

        if (status) {
            event.preventDefault();
            submitStatus(status);

            return;
        }

        const form = event.target.closest('form[data-us-form]');

        if (form) {
            event.preventDefault();
            clearTimeout(timer);
            go(formUrl(form));
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('[data-us-search]')) {
            clearTimeout(timer);
            timer = setTimeout(() => go(formUrl(event.target.form), 'replace', { keepFilters: true }), 350);
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-us-auto]')) {
            go(formUrl(event.target.form));
        }
    });

    root.addEventListener('keydown', onKey);
    root.addEventListener('pointerover', onDonutOver);
    root.addEventListener('pointerout', onDonutOut);
    root.addEventListener('focusin', onDonutOver);
    root.addEventListener('focusout', onDonutOut);

    const drawer = $('[data-us-drawer]');

    drawer?.addEventListener('close', () => {
        $('[data-us-drawer-body]').replaceChildren();
        markSelected();

        if (history.state?.us === 'detail') {
            history.back();
        } else if (userOf(here())) {
            history.replaceState({ us: 'list' }, '', withoutUser(here()));
            current = here();
        }

        const back = trigger && document.contains(trigger) ? trigger : null;

        (back ?? $(`[data-us-id="${trigger?.dataset.usId}"]`))?.focus?.();
    });

    window.addEventListener('popstate', () => {
        const target = here();

        if (withoutUser(target) === withoutUser(current)) {
            // Hanya detail yang berubah: buka/tutup panel tanpa memuat ulang daftar.
            current = target;

            if (userOf(target)) {
                const row = $(`a[data-us-id="${userOf(target)}"]`);
                loadDetail(row?.dataset.usDetail ?? `${root.dataset.usUrl}/${userOf(target)}`, target, false);
            } else if (drawer?.open) {
                $('[data-us-drawer-body]').replaceChildren();
                drawer.close();
            }

            return;
        }

        const had = Boolean(userOf(target));

        go(withoutUser(target), 'none').then(() => {
            if (had) {
                loadDetail(`${root.dataset.usUrl}/${userOf(target)}`, target, false);
            }
        });
        current = target;
    });

    window.addEventListener('resize', () => {
        clearTimeout(window.__usResize);
        window.__usResize = setTimeout(() => root && drawChart(false), 150);
    });
}

/* ------------------------------------------------------------------------------------------ counter KPI */

function counts() {
    return new Map($$('[data-us-count]').map((node) => [node.dataset.usKey, Number(node.dataset.usCount)]));
}

/*
 * Pertama kali: dari 0 ke nilai saat elemen terlihat. Setelah filter: dari nilai lama ke nilai baru. Teks akhir selalu diisi persis dari server;
 * hanya tampilan antara yang dianimasikan.
 */
function animateCounters(previous, first) {
    const nodes = $$('[data-us-count]');

    nodes.forEach((node) => {
        const to = Number(node.dataset.usCount);
        const from = first ? 0 : previous.get(node.dataset.usKey);

        if (reduced() || from === undefined || from === to || !Number.isFinite(to)) {
            return;
        }

        const run = () => {
            const start = performance.now();
            const span = first ? 750 : 500;
            const step = (now) => {
                const t = Math.min(1, Math.max(0, (now - start) / span)); // timestamp rAF bisa lebih awal dari `start`
                const eased = 1 - (1 - t) ** 3;

                node.textContent = nf.format(Math.round(from + (to - from) * eased));

                if (t < 1 && node.isConnected) {
                    requestAnimationFrame(step);
                } else {
                    node.textContent = nf.format(to);
                }
            };

            requestAnimationFrame(step);
        };

        node.textContent = nf.format(from);

        if (first && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    observer.disconnect();
                    run();
                }
            });

            observer.observe(node);
        } else {
            run();
        }
    });
}

/* ------------------------------------------------------------------------------------------------ detail */

async function loadDetail(endpoint, href, push = true) {
    detailController?.abort();
    detailController = new AbortController();
    const mine = detailController;
    const body = $('[data-us-drawer-body]');

    body.replaceChildren(skeleton());
    openDrawer();

    try {
        const response = await fetch(endpoint, { headers: HEADERS_JSON, credentials: 'same-origin', signal: mine.signal });

        if (!response.ok) {
            throw new Error(response.status === 404 ? 'Pengguna tidak ditemukan.' : 'Detail tidak dapat dimuat.');
        }

        const json = await response.json();

        fill(body, json.html);
        body.focus();
        markSelected();

        if (push && withoutUser(href) === withoutUser(here())) {
            history.pushState({ us: 'detail' }, '', href);
            current = here();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            const p = document.createElement('p');

            p.className = 'bd-us__loading is-error';
            p.setAttribute('role', 'alert');
            p.textContent = error.message || 'Detail tidak dapat dimuat.';
            body.replaceChildren(p);
        }
    }
}

function skeleton() {
    const box = document.createElement('div');

    box.className = 'bd-us__skeleton';
    box.setAttribute('role', 'status');
    box.setAttribute('aria-label', 'Memuat detail');
    box.append(document.createElement('i'), document.createElement('i'), document.createElement('i'), document.createElement('i'));

    return box;
}

// Isi dibuat server (Blade, semua nilai di-escape); diurai sebagai dokumen terpisah lalu dipindahkan.
function fill(body, html, tab = 'ringkasan') {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    body.replaceChildren(...doc.body.childNodes);
    initTabs(tab);
}

function openDrawer() {
    const drawer = $('[data-us-drawer]');

    if (drawer && !drawer.open) {
        drawer.showModal();
    }
}

function closeDrawer() {
    const drawer = $('[data-us-drawer]');

    if (drawer?.open) {
        drawer.close();
    }
}

async function copyId(button) {
    const state = $('[data-us-copy-state]', button);
    const text = String(button.dataset.usCopy);

    try {
        await navigator.clipboard.writeText(text);
        state.textContent = '· tersalin';
    } catch {
        state.textContent = '· salin manual';
    }

    setTimeout(() => {
        state.textContent = '';
    }, 2200);
}

/* Tab: semua panel dirender server; tanpa JS semuanya tampil. Dengan JS hanya satu yang aktif. */
function initTabs(tab = 'ringkasan') {
    if ($('[data-us-tabs]')) {
        activateTab(tab, false);
    }
}

function activateTab(key, focus = false) {
    $$('[data-us-tab]').forEach((button) => {
        const on = button.dataset.usTab === key;

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-selected', String(on));
        button.tabIndex = on ? 0 : -1;

        if (on && focus) {
            button.focus();
        }
    });
    $$('[data-us-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.usPanel !== key;
    });
}

async function submitStatus(form) {
    const button = $('[data-us-submit]', form);
    const tab = $('[data-us-tab].is-on')?.dataset.usTab ?? 'akun';
    const body = $('[data-us-drawer-body]');

    button.disabled = true;
    button.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { ...HEADERS_JSON, 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: new URLSearchParams(new FormData(form)),
        });
        const json = await response.json().catch(() => null);

        if (!json) {
            throw new Error(response.status === 419 ? 'Sesi berakhir. Muat ulang halaman lalu coba lagi.' : 'Perubahan tidak dapat disimpan.');
        }

        if (json.html) {
            fill(body, json.html, tab);
        }

        toast(json.message ?? (json.ok ? 'Tersimpan.' : 'Perubahan ditolak.'), !json.ok);

        if (json.ok) {
            go(here(), 'none'); // segarkan KPI, grafik, dan daftar; panel detail tidak diganti
        }
    } catch (error) {
        toast(error.message || 'Koneksi terputus. Perubahan belum disimpan.', true);
        button.disabled = false;
        button.removeAttribute('aria-busy');
    }
}

/* ----------------------------------------------------------------------------------------------- donut */

function resetDonut() {
    const center = $('[data-us-donut]');

    if (center) {
        center.dataset.n0 = $('[data-us-donut-n]', center).textContent;
        center.dataset.l0 = $('[data-us-donut-l]', center).textContent;
    }
}

function donutKey(target) {
    const link = target.closest?.('a');

    return link ? (link.querySelector('[data-us-seg]')?.dataset.usSeg ?? link.dataset.usLeg ?? null) : null;
}

function onDonutOver(event) {
    const key = donutKey(event.target);
    const donut = $('[data-us-donut]');

    if (!key || !donut) {
        return;
    }

    $$('[data-us-seg]').forEach((seg) => seg.classList.toggle('is-hot', seg.dataset.usSeg === key));
    $$('[data-us-leg]').forEach((leg) => leg.classList.toggle('is-hot', leg.dataset.usLeg === key));
    donut.classList.add('is-dim');

    const leg = $(`[data-us-leg="${key}"]`);

    $('[data-us-donut-n]', donut).textContent = leg.dataset.usN;
    $('[data-us-donut-l]', donut).textContent = leg.dataset.usL;
}

function onDonutOut(event) {
    const donut = $('[data-us-donut]');

    if (!donutKey(event.target) || !donut) {
        return;
    }

    $$('[data-us-seg], [data-us-leg]').forEach((node) => node.classList.remove('is-hot'));
    donut.classList.remove('is-dim');
    $('[data-us-donut-n]', donut).textContent = donut.dataset.n0;
    $('[data-us-donut-l]', donut).textContent = donut.dataset.l0;
}

/* ------------------------------------------------------------------------------------------------ grafik */

function el(name, attributes) {
    const node = document.createElementNS(NS, name);

    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));

    return node;
}

function niceMax(value) {
    if (value <= 4) {
        return 4;
    }

    const pow = 10 ** Math.floor(Math.log10(value));
    const n = value / pow;

    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 4 ? 4 : n <= 5 ? 5 : 10) * pow;
}

/* Interpolasi kubik monoton (Fritsch–Carlson): halus tetapi tidak pernah melampaui nilai data. */
function curve(points) {
    const n = points.length;

    if (n === 1) {
        return `M${points[0].x} ${points[0].y}`;
    }

    const dx = [];
    const m = [];

    for (let i = 0; i < n - 1; i++) {
        dx[i] = points[i + 1].x - points[i].x;
        m[i] = (points[i + 1].y - points[i].y) / dx[i];
    }

    const t = [m[0]];

    for (let i = 1; i < n - 1; i++) {
        t[i] = m[i - 1] * m[i] <= 0 ? 0 : (m[i - 1] + m[i]) / 2;
    }

    t[n - 1] = m[n - 2];

    for (let i = 0; i < n - 1; i++) {
        if (m[i] === 0) {
            t[i] = 0;
            t[i + 1] = 0;
        } else {
            const a = t[i] / m[i];
            const b = t[i + 1] / m[i];
            const s = a * a + b * b;

            if (s > 9) {
                const tau = 3 / Math.sqrt(s);

                t[i] = tau * a * m[i];
                t[i + 1] = tau * b * m[i];
            }
        }
    }

    let d = `M${points[0].x} ${points[0].y}`;

    for (let i = 0; i < n - 1; i++) {
        const h = dx[i] / 3;

        d += ` C${points[i].x + h} ${points[i].y + t[i] * h} ${points[i + 1].x - h} ${points[i + 1].y - t[i + 1] * h} ${points[i + 1].x} ${points[i + 1].y}`;
    }

    return d;
}

function drawChart(animate) {
    cancelAnimationFrame(tween);

    const box = $('[data-us-chart]');
    const plot = box ? $('[data-us-plot]', box) : null;

    chart = null;

    if (!box || !plot) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(box.dataset.usPayload);
    } catch {
        return;
    }

    if (!payload.series[serie]) {
        serie = 'baru';
    }

    $$('[data-us-serie]', box).forEach((button) => {
        const on = button.dataset.usSerie === serie;

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-selected', String(on));
        button.tabIndex = on ? 0 : -1;
    });

    const labels = payload.labels;
    const n = labels.length;
    const width = Math.max(260, plot.clientWidth);
    const height = width < 480 ? 200 : 260;
    const pad = { l: 42, r: 14, t: 16, b: 28 };
    const inner = { w: width - pad.l - pad.r, h: height - pad.t - pad.b };
    const xs = labels.map((_, i) => (n === 1 ? pad.l + inner.w / 2 : pad.l + (inner.w * i) / (n - 1)));
    const svg = el('svg', { viewBox: `0 0 ${width} ${height}`, width: '100%', height, role: 'img', 'aria-hidden': 'true', class: 'us-svg' });
    const ticks = [];

    for (let i = 0; i <= 4; i++) {
        const y = pad.t + inner.h - (inner.h * i) / 4;

        svg.append(el('line', { x1: pad.l, x2: width - pad.r, y1: y, y2: y, class: i === 0 ? 'us-axis' : 'us-grid' }));

        const tick = el('text', { x: pad.l - 7, y: y + 4, class: 'us-ytick', 'text-anchor': 'end' });

        ticks.push(tick);
        svg.append(tick);
    }

    const every = Math.ceil(n / Math.max(2, Math.floor(inner.w / 70)));

    labels.forEach((label, i) => {
        if (i % every === 0) {
            const tick = el('text', { x: xs[i], y: height - 8, class: 'us-xtick', 'text-anchor': i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle' });

            tick.textContent = label;
            svg.append(tick);
        }
    });

    const area = el('path', { class: 'us-area' });
    const line = el('path', { class: 'us-line', pathLength: 1 });
    const cursor = el('line', { class: 'us-cursor', y1: pad.t, y2: pad.t + inner.h });
    const dots = n <= 45 ? xs.map((_, i) => el('circle', { class: 'us-dot', r: n > 20 ? 3 : 4, 'data-us-dot': i })) : [];

    svg.append(area, line, cursor, ...dots);

    const tip = document.createElement('div');

    tip.className = 'bd-us__tooltip';
    tip.setAttribute('aria-hidden', 'true');
    plot.replaceChildren(svg, tip);
    plot.tabIndex = 0;
    plot.dataset.count = String(n);
    plot.style.setProperty('--c', `var(--us-${serie})`);
    plot.classList.remove('is-draw');

    chart = { box, plot, payload, n, xs, pad, inner, width, height, ticks, area, line, dots, cursor, tip, values: [], max: 1, active: -1 };
    place(payload.series[serie].points, niceMax(payload.series[serie].max));

    if (animate && !reduced()) {
        plot.classList.add('is-draw');
        setTimeout(() => plot.classList.remove('is-draw'), 1100);
    }
}

function place(values, max) {
    const { n, xs, pad, inner, area, line, dots, ticks } = chart;
    const base = pad.t + inner.h;
    const points = values.map((v, i) => ({ x: xs[i], y: base - (max ? (inner.h * v) / max : 0) }));
    const d = curve(points);

    line.setAttribute('d', d);
    area.setAttribute('d', n > 1 ? `${d} L${xs[n - 1]} ${base} L${xs[0]} ${base} Z` : '');
    dots.forEach((dot, i) => {
        dot.setAttribute('cx', points[i].x);
        dot.setAttribute('cy', points[i].y);
    });
    ticks.forEach((tick, i) => {
        tick.textContent = nf.format(Math.round((max / 4) * i));
    });
    chart.values = values;
    chart.max = max;
    chart.points = points;
}

function selectSerie(key) {
    if (!chart || key === serie || !chart.payload.series[key]) {
        return;
    }

    const from = chart.values.slice();
    const fromMax = chart.max;
    const next = chart.payload.series[key];
    const toMax = niceMax(next.max);

    serie = key;
    $$('[data-us-serie]', chart.box).forEach((button) => {
        const on = button.dataset.usSerie === key;

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-selected', String(on));
        button.tabIndex = on ? 0 : -1;
    });
    chart.plot.style.setProperty('--c', `var(--us-${key})`);
    chart.plot.classList.remove('is-draw');
    clearActive();

    if (reduced()) {
        place(next.points, toMax);

        return;
    }

    const start = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - start) / 380);
        const e = 1 - (1 - t) ** 3;

        place(next.points.map((v, i) => from[i] + (v - from[i]) * e), fromMax + (toMax - fromMax) * e);

        if (t < 1) {
            tween = requestAnimationFrame(step);
        }
    };

    cancelAnimationFrame(tween);
    tween = requestAnimationFrame(step);
}

function setActive(index, announce = true) {
    if (!chart || index < 0 || index >= chart.n) {
        return;
    }

    const data = chart.payload.series[serie];
    const value = data.points[index];
    const message = `${chart.payload.labels[index]}: ${nf.format(value)} ${data.unit}`;

    chart.active = index;
    chart.dots.forEach((dot, i) => dot.classList.toggle('is-on', i === index));
    chart.cursor.setAttribute('x1', chart.xs[index]);
    chart.cursor.setAttribute('x2', chart.xs[index]);
    chart.cursor.classList.add('is-on');

    const y = chart.points[index].y;
    const half = chart.tip.offsetWidth / 2 || 50;

    chart.tip.replaceChildren();

    const strong = document.createElement('strong');
    const span = document.createElement('span');

    strong.textContent = nf.format(value);
    span.textContent = `${data.unit} · ${chart.payload.labels[index]}`;
    chart.tip.append(strong, span);
    chart.tip.style.left = `${Math.min(chart.width - half, Math.max(half, chart.xs[index]))}px`;
    chart.tip.style.top = `${y}px`;
    chart.tip.classList.add('is-on');

    const line = $('[data-us-tip]', chart.box);

    if (line) {
        line.textContent = message;
        line.classList.add('is-live');
    }

    if (announce) {
        chart.plot.setAttribute('aria-label', message);
    }
}

function clearActive() {
    if (!chart) {
        return;
    }

    chart.active = -1;
    chart.dots.forEach((dot) => dot.classList.remove('is-on'));
    chart.cursor.classList.remove('is-on');
    chart.tip.classList.remove('is-on');
}

function indexAt(event) {
    const rect = chart.plot.getBoundingClientRect();
    const x = event.clientX - rect.left - chart.pad.l;

    return chart.n === 1 ? 0 : Math.min(chart.n - 1, Math.max(0, Math.round((x / chart.inner.w) * (chart.n - 1))));
}

document.addEventListener('pointermove', (event) => {
    if (chart && chart.plot.contains(event.target)) {
        setActive(indexAt(event), false);
    }
});

document.addEventListener('pointerout', (event) => {
    if (chart && chart.plot.contains(event.target) && !chart.plot.contains(event.relatedTarget)) {
        chart.tip.classList.remove('is-on');
        chart.cursor.classList.remove('is-on');
        chart.dots.forEach((dot) => dot.classList.remove('is-on'));
    }
});

function onKey(event) {
    const plot = event.target.closest?.('[data-us-plot]');

    if (plot && chart) {
        const keys = { ArrowRight: 1, ArrowLeft: -1 };

        if (event.key in keys) {
            event.preventDefault();
            setActive(Math.min(chart.n - 1, Math.max(0, (chart.active < 0 ? (keys[event.key] > 0 ? -1 : chart.n) : chart.active) + keys[event.key])));
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            setActive(event.key === 'Home' ? 0 : chart.n - 1);
        } else if (event.key === 'Escape' && chart.active >= 0) {
            event.stopPropagation();
            clearActive();
        }

        return;
    }

    const tab = event.target.closest?.('[data-us-serie], [data-us-tab]');

    if (tab && ['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(event.key)) {
        const attr = tab.matches('[data-us-serie]') ? 'data-us-serie' : 'data-us-tab';
        const tabs = $$(`[${attr}]`, tab.parentElement);
        const at = tabs.indexOf(tab);
        const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs.at(-1) : tabs[(at + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];

        event.preventDefault();

        if (attr === 'data-us-serie') {
            selectSerie(next.dataset.usSerie);
            next.focus();
        } else {
            activateTab(next.dataset.usTab, true);
        }
    }
}
