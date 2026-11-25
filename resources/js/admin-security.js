/*
 * Keamanan (admin/security). Dimuat sesuai kebutuhan oleh admin.js saat halaman [data-sc] terbuka.
 *
 * Peningkatan progresif: semua tautan dan form adalah GET nyata yang tetap bekerja tanpa JavaScript. Dengan JS dokumen tidak dimuat ulang: region
 * [data-sc-swap] diganti dari salinan URL yang sama, bilah alamat mengikuti lewat History API (Back/Forward berfungsi), permintaan lama dibatalkan
 * (AbortController), dan kegagalan menampilkan "Coba lagi". Detail event dimuat saat dibuka (JSON berisi HTML yang dirender server; metadata sudah
 * diredaksi di server). Halaman ini hanya membaca: tidak ada mutasi. Tidak ada polling; "Muat ulang data" adalah tindakan manual.
 * Gerak memakai transform/opacity dan dimatikan oleh prefers-reduced-motion; nilai akhir selalu tampil penuh.
 */

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const NS = 'http://www.w3.org/2000/svg';
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const nf = new Intl.NumberFormat('id-ID');
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const COLORS = { berhasil: 'var(--sc-berhasil)', gagal: 'var(--sc-gagal)', keluar: 'var(--sc-keluar)', akun: 'var(--sc-akun)' };

let root = null;
let controller = null;
let current = '';
let timer = 0;
let trigger = null;
let detailController = null;
let attempt = null; // permintaan terakhir {target, mode, options}; "Coba lagi" mengulang tujuan yang gagal dengan filter yang sama
let filterOpen = false;
let chart = null;
let tween = 0;
const hidden = new Set(); // seri yang disembunyikan lewat legenda

const here = () => location.pathname + location.search;
const plain = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
const withoutLog = (href) => {
    const url = new URL(href, location.href);

    url.searchParams.delete('log');

    return url.pathname + url.search;
};
const logOf = (href) => new URL(href, location.href).searchParams.get('log');

export function initAdminSecurity() {
    root = $('[data-sc]');

    if (!root) {
        return;
    }

    current = here();
    history.replaceState({ sc: 'list' }, '', location.href);

    wire();
    afterSwap(new Map(), true);
    setTimeout(() => root.classList.remove('is-entering'), 1200);

    const drawer = $('[data-sc-drawer]');

    // Deep link ?log=ID dirender server sebagai dialog terbuka non-modal; jadikan modal agar fokus terkunci dan Esc berfungsi.
    if (drawer?.open && $('[data-sc-detail-id]')) {
        drawer.close();
        openDrawer();
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

        swap(new DOMParser().parseFromString(await response.text(), 'text/html'), keepFilters);

        const path = url.pathname + url.search;

        if (mode === 'push' && path !== here()) {
            history.pushState({ sc: 'list' }, '', path);
        } else if (mode === 'replace') {
            history.replaceState({ sc: 'list' }, '', path);
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
    $$('[data-sc-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-sc-swap="${region.dataset.scSwap}"]`);

        if (!fresh) {
            return;
        }

        // Saat mengetik di kolom cari, form tidak diganti (fokus dan kursor tetap); hanya tombol "Atur ulang" yang disegarkan.
        if (keepFilters && region.dataset.scSwap === 'filters') {
            $('[data-sc-reset-slot]', region)?.replaceWith(document.importNode($('[data-sc-reset-slot]', fresh), true));

            return;
        }

        region.replaceWith(document.importNode(fresh, true));
    });

    const notice = $('[data-sc-notice]');
    const freshNotice = next.querySelector('[data-sc-notice]');

    if (notice && freshNotice) {
        notice.textContent = freshNotice.textContent.trim();
        notice.hidden = freshNotice.hidden;
    }
}

function afterSwap(previous, first) {
    animateCounters(previous, first);
    drawChart(true);

    const form = $('[data-sc-filters]');

    if (form && filterOpen) {
        form.classList.add('is-open');
        $('[data-sc-filter-toggle]', form)?.setAttribute('aria-expanded', 'true');
    }

    markSelected();
}

function markSelected() {
    const id = $('[data-sc-detail-id]')?.dataset.scDetailId;

    $$('.bd-sc__event').forEach((item) => item.classList.toggle('is-selected', Boolean(id) && item.querySelector(`[data-sc-id="${id}"]`) !== null));
}

/* ----------------------------------------------------------------------------------------------- galat */

function showError(message) {
    const box = $('[data-sc-error]');

    if (box) {
        $('[data-sc-error-text]', box).textContent = message;
        box.hidden = false;
    }
}

function hideError() {
    const box = $('[data-sc-error]');

    if (box) {
        box.hidden = true;
    }
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

    if (params.get('urut') === 'terbaru') {
        params.delete('urut');
    }

    url.search = params.toString();

    return url.pathname + url.search;
}

function wire() {
    root.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-sc-nav]');

        if (link && plain(event) && !link.getAttribute('target')) {
            event.preventDefault();
            go(link.getAttribute('href'));

            return;
        }

        const row = event.target.closest('a[data-sc-detail]');

        if (row && plain(event)) {
            event.preventDefault();
            trigger = row;
            loadDetail(row.dataset.scDetail, row.getAttribute('href'));

            return;
        }

        if (event.target.closest('[data-sc-close]')) {
            closeDrawer();

            return;
        }

        if (event.target.closest('[data-sc-retry]')) {
            go(attempt?.target ?? here(), attempt?.mode ?? 'none', attempt?.options);

            return;
        }

        if (event.target.closest('[data-sc-refresh]')) {
            go(here(), 'none');

            return;
        }

        const toggle = event.target.closest('[data-sc-custom-toggle]');

        if (toggle) {
            const form = $('#sc-custom');
            const open = form.hidden;

            form.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));

            if (open) {
                $('input[name="dari"]', form)?.focus();
            }

            return;
        }

        const filterToggle = event.target.closest('[data-sc-filter-toggle]');

        if (filterToggle) {
            filterOpen = !filterOpen;
            filterToggle.closest('form').classList.toggle('is-open', filterOpen);
            filterToggle.setAttribute('aria-expanded', String(filterOpen));

            return;
        }

        const serie = event.target.closest('[data-sc-serie]');

        if (serie) {
            toggleSerie(serie.dataset.scSerie);

            return;
        }

        const copy = event.target.closest('[data-sc-copy]');

        if (copy) {
            copyId(copy);
        }

        // Klik pada latar dialog (di luar panel) menutup detail.
        if (event.target.matches('dialog[data-sc-drawer]')) {
            closeDrawer();
        }
    });

    root.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-sc-form]');

        if (form) {
            event.preventDefault();
            clearTimeout(timer);
            go(formUrl(form));
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('[data-sc-search]')) {
            clearTimeout(timer);
            timer = setTimeout(() => go(formUrl(event.target.form), 'replace', { keepFilters: true }), 350);
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-sc-auto]')) {
            go(formUrl(event.target.form));
        }
    });

    root.addEventListener('keydown', onKey);
    root.addEventListener('pointerover', onRankOver);
    root.addEventListener('pointerout', onRankOut);
    root.addEventListener('focusin', onRankOver);
    root.addEventListener('focusout', onRankOut);

    const drawer = $('[data-sc-drawer]');

    drawer?.addEventListener('close', () => {
        $('[data-sc-drawer-body]').replaceChildren();
        markSelected();

        if (history.state?.sc === 'detail') {
            history.back();
        } else if (logOf(here())) {
            history.replaceState({ sc: 'list' }, '', withoutLog(here()));
            current = here();
        }

        const back = trigger && document.contains(trigger) ? trigger : $(`[data-sc-id="${trigger?.dataset.scId}"]`);

        back?.focus?.();
    });

    window.addEventListener('popstate', () => {
        const target = here();

        if (withoutLog(target) === withoutLog(current)) {
            // Hanya detail yang berubah: buka/tutup panel tanpa memuat ulang daftar.
            current = target;

            if (logOf(target)) {
                const row = $(`a[data-sc-id="${logOf(target)}"]`);
                loadDetail(row?.dataset.scDetail ?? `${root.dataset.scUrl}/events/${logOf(target)}`, target, false);
            } else if (drawer?.open) {
                $('[data-sc-drawer-body]').replaceChildren();
                drawer.close();
            }

            return;
        }

        const had = Boolean(logOf(target));

        go(withoutLog(target), 'none').then(() => {
            if (had) {
                loadDetail(`${root.dataset.scUrl}/events/${logOf(target)}`, target, false);
            }
        });
        current = target;
    });

    window.addEventListener('resize', () => {
        clearTimeout(window.__scResize);
        window.__scResize = setTimeout(() => root && drawChart(false), 150);
    });
}

/* ------------------------------------------------------------------------------------------ counter KPI */

function counts() {
    return new Map($$('[data-sc-count]').map((node) => [node.dataset.scKey, Number(node.dataset.scCount)]));
}

/*
 * Pertama kali: dari 0 ke nilai saat elemen terlihat. Setelah filter/muat ulang: dari nilai lama ke nilai baru. Teks akhir selalu diisi persis
 * dari server; hanya tampilan antara yang dianimasikan. Tidak ada angka acak dan tidak ada animasi saat hover.
 */
function animateCounters(previous, first) {
    $$('[data-sc-count]').forEach((node) => {
        const to = Number(node.dataset.scCount);
        const from = first ? 0 : previous.get(node.dataset.scKey);

        if (reduced() || from === undefined || from === to || !Number.isFinite(to)) {
            return;
        }

        const run = () => {
            const start = performance.now();
            const span = first ? 750 : 500;
            const step = (now) => {
                const t = Math.min(1, Math.max(0, (now - start) / span)); // timestamp rAF bisa lebih awal dari `start`

                node.textContent = nf.format(Math.round(from + (to - from) * (1 - (1 - t) ** 3)));

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
    const body = $('[data-sc-drawer-body]');
    const skeleton = document.createElement('div');

    skeleton.className = 'bd-sc__skeleton';
    skeleton.setAttribute('role', 'status');
    skeleton.setAttribute('aria-label', 'Memuat detail');
    skeleton.append(...[0, 1, 2, 3].map(() => document.createElement('i')));
    body.replaceChildren(skeleton);
    openDrawer();

    try {
        const response = await fetch(endpoint, { headers: HEADERS_JSON, credentials: 'same-origin', signal: mine.signal });

        if (!response.ok) {
            throw new Error(response.status === 404 ? 'Event tidak ditemukan.' : 'Detail tidak dapat dimuat.');
        }

        const json = await response.json();

        // Isi dibuat server (Blade, semua nilai di-escape dan metadata diredaksi); diurai sebagai dokumen terpisah lalu dipindahkan.
        body.replaceChildren(...new DOMParser().parseFromString(json.html, 'text/html').body.childNodes);
        body.focus();
        markSelected();

        if (push && withoutLog(href) === withoutLog(here())) {
            history.pushState({ sc: 'detail' }, '', href);
            current = here();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            const p = document.createElement('p');

            p.className = 'bd-sc__loading is-error';
            p.setAttribute('role', 'alert');
            p.textContent = error.message || 'Detail tidak dapat dimuat.';
            body.replaceChildren(p);
        }
    }
}

function openDrawer() {
    const drawer = $('[data-sc-drawer]');

    if (drawer && !drawer.open) {
        drawer.showModal();
    }
}

function closeDrawer() {
    const drawer = $('[data-sc-drawer]');

    if (drawer?.open) {
        drawer.close();
    }
}

async function copyId(button) {
    const state = $('[data-sc-copy-state]', button);

    try {
        await navigator.clipboard.writeText(String(button.dataset.scCopy));
        state.textContent = '· tersalin';
    } catch {
        state.textContent = '· salin manual';
    }

    setTimeout(() => {
        state.textContent = '';
    }, 2200);
}

/* --------------------------------------------------------------------------------------- distribusi */

function onRankOver(event) {
    const link = event.target.closest?.('[data-sc-rank]');

    if (!link) {
        return;
    }

    const seg = $('.bd-sc__seg');

    seg?.classList.add('is-dim');
    $$('[data-sc-part]').forEach((part) => part.classList.toggle('is-hot', part.dataset.scPart === link.dataset.scRank));
    link.classList.add('is-hot');
}

function onRankOut(event) {
    if (!event.target.closest?.('[data-sc-rank]')) {
        return;
    }

    $('.bd-sc__seg')?.classList.remove('is-dim');
    $$('[data-sc-part], [data-sc-rank]').forEach((node) => node.classList.remove('is-hot'));
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

const visible = (payload) => Object.keys(payload.series).filter((key) => !hidden.has(key));

function stackMax(payload) {
    const keys = visible(payload);

    return Math.max(0, ...payload.labels.map((_, i) => keys.reduce((sum, key) => sum + payload.series[key].points[i], 0)));
}

function drawChart(animate) {
    cancelAnimationFrame(tween);

    const box = $('[data-sc-chart]');
    const plot = box ? $('[data-sc-plot]', box) : null;

    chart = null;

    if (!box || !plot) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(box.dataset.scPayload);
    } catch {
        return;
    }

    // Seri yang disembunyikan hanya berlaku bila seri itu masih ada pada data baru.
    [...hidden].forEach((key) => !payload.series[key] && hidden.delete(key));
    $$('[data-sc-serie]', box).forEach((button) => {
        const on = !hidden.has(button.dataset.scSerie);

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-pressed', String(on));
    });

    const n = payload.labels.length;
    const width = Math.max(260, plot.clientWidth);
    const height = width < 480 ? 200 : 260;
    const pad = { l: 34, r: 8, t: 14, b: 28 };
    const inner = { w: width - pad.l - pad.r, h: height - pad.t - pad.b };
    const step = inner.w / n;
    const barW = Math.max(3, Math.min(30, step * 0.66));
    const svg = el('svg', { viewBox: `0 0 ${width} ${height}`, width: '100%', height, role: 'img', 'aria-hidden': 'true', class: 'sc-svg' });
    const ticks = [];

    for (let i = 0; i <= 4; i++) {
        const y = pad.t + inner.h - (inner.h * i) / 4;

        svg.append(el('line', { x1: pad.l, x2: width - pad.r, y1: y, y2: y, class: i === 0 ? 'sc-axis' : 'sc-grid' }));

        const tick = el('text', { x: pad.l - 6, y: y + 4, class: 'sc-ytick', 'text-anchor': 'end' });

        ticks.push(tick);
        svg.append(tick);
    }

    const every = Math.ceil(n / Math.max(2, Math.floor(inner.w / 64)));

    payload.labels.forEach((label, i) => {
        if (i % every === 0) {
            const tick = el('text', { x: pad.l + step * i + step / 2, y: height - 8, class: 'sc-xtick', 'text-anchor': 'middle' });

            tick.textContent = label;
            svg.append(tick);
        }
    });

    // Satu rect per (bucket, seri); tinggi dan posisi diatur place() sehingga legenda dapat menyusun ulang tumpukan dengan halus.
    const keys = Object.keys(payload.series);
    const bars = [];

    payload.labels.forEach((_, i) => {
        keys.forEach((key) => {
            const rect = el('rect', { class: 'sc-bar', x: pad.l + step * i + (step - barW) / 2, width: barW, rx: 2, style: `--c: ${COLORS[key]}; --i: ${i}` });

            bars.push({ rect, i, key });
            svg.append(rect);
        });
    });

    const hits = payload.labels.map((_, i) => el('rect', { class: 'sc-hit', x: pad.l + step * i, y: pad.t, width: step, height: inner.h, 'data-sc-hit': i }));

    svg.append(...hits);

    const tip = document.createElement('div');

    tip.className = 'bd-sc__tooltip';
    tip.setAttribute('aria-hidden', 'true');
    plot.replaceChildren(svg, tip);
    plot.dataset.count = String(n);
    plot.classList.remove('is-draw', 'has-active');

    chart = { box, plot, payload, n, step, pad, inner, width, height, ticks, bars, tip, active: -1, keys, heights: null };
    place(1);

    if (animate && !reduced()) {
        plot.classList.add('is-draw');
        setTimeout(() => plot.classList.remove('is-draw'), 1200);
    }
}

/* Susun tumpukan menurut seri yang terlihat. `t` (0..1) menginterpolasi dari susunan sebelumnya ke yang baru. */
function place(t) {
    const { payload, bars, inner, pad, ticks } = chart;
    const keys = visible(payload);
    const max = niceMax(stackMax(payload));
    const next = new Map();
    const base = pad.t + inner.h;

    payload.labels.forEach((_, i) => {
        let acc = 0;

        chart.keys.forEach((key) => {
            const n = keys.includes(key) ? payload.series[key].points[i] : 0;
            const h = max ? (inner.h * n) / max : 0;

            next.set(`${i}:${key}`, { y: base - (acc * inner.h) / max || base, h });
            acc += n;
        });
    });

    const prev = chart.heights;

    bars.forEach(({ rect, i, key }) => {
        const to = next.get(`${i}:${key}`);
        const from = prev?.get(`${i}:${key}`) ?? to;
        const y = from.y + (to.y - from.y) * t;
        const h = from.h + (to.h - from.h) * t;

        rect.setAttribute('y', Math.max(0, y - h));
        rect.setAttribute('height', Math.max(0, h));
        rect.setAttribute('visibility', h < 0.01 ? 'hidden' : 'visible');
    });

    // y = alas tumpukan seri; tinggi bar mengisi ke atas dari posisi tumpukan.
    ticks.forEach((tick, i) => {
        tick.textContent = nf.format(Math.round((max / 4) * i));
    });

    if (t >= 1) {
        chart.heights = next;
        chart.max = max;
    }
}

function toggleSerie(key) {
    if (!chart || !chart.payload.series[key]) {
        return;
    }

    // Minimal satu seri tetap terlihat agar grafik tidak kosong tanpa penjelasan.
    if (!hidden.has(key) && visible(chart.payload).length === 1) {
        return;
    }

    hidden.has(key) ? hidden.delete(key) : hidden.add(key);
    $$('[data-sc-serie]', chart.box).forEach((button) => {
        const on = !hidden.has(button.dataset.scSerie);

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-pressed', String(on));
    });
    clearActive();
    cancelAnimationFrame(tween);

    if (reduced()) {
        place(1);

        return;
    }

    const start = performance.now();
    const step = (now) => {
        const t = Math.min(1, (now - start) / 380);

        place(1 - (1 - t) ** 3);

        if (t < 1) {
            tween = requestAnimationFrame(step);
        }
    };

    tween = requestAnimationFrame(step);
}

function setActive(index, announce = true) {
    if (!chart || index < 0 || index >= chart.n) {
        return;
    }

    const { payload } = chart;
    const keys = visible(payload);
    const label = payload.labels[index];
    const total = keys.reduce((sum, key) => sum + payload.series[key].points[index], 0);

    chart.active = index;
    chart.plot.classList.add('has-active');
    chart.bars.forEach(({ rect, i }) => rect.classList.toggle('is-on', i === index));

    chart.tip.replaceChildren();

    const title = document.createElement('strong');

    title.textContent = label;
    chart.tip.append(title);
    keys.forEach((key) => {
        const row = document.createElement('div');
        const name = document.createElement('span');
        const swatch = document.createElement('i');
        const value = document.createElement('b');

        swatch.style.setProperty('--c', COLORS[key]);
        name.append(swatch, payload.series[key].label);
        value.textContent = nf.format(payload.series[key].points[index]);
        row.append(name, value);
        chart.tip.append(row);
    });

    const sum = document.createElement('div');

    sum.className = 'is-total';
    sum.append(document.createElement('span'), document.createElement('b'));
    sum.firstChild.textContent = 'Total';
    sum.lastChild.textContent = nf.format(total);
    chart.tip.append(sum);

    const half = chart.tip.offsetWidth / 2 || 80;
    const x = chart.pad.l + chart.step * index + chart.step / 2;
    const top = Math.max(chart.pad.t + 6, chart.pad.t + chart.inner.h - (chart.inner.h * total) / (chart.max || 1));

    chart.tip.style.left = `${Math.min(chart.width - half, Math.max(half, x))}px`;
    chart.tip.style.top = `${Math.max(top, chart.tip.offsetHeight + 12)}px`;
    chart.tip.classList.add('is-on');

    const message = `${label}: ${keys.map((key) => `${nf.format(payload.series[key].points[index])} ${payload.series[key].label.toLowerCase()}`).join(', ')}; total ${nf.format(total)}`;
    const line = $('[data-sc-tip]', chart.box);

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
    chart.plot.classList.remove('has-active');
    chart.bars.forEach(({ rect }) => rect.classList.remove('is-on'));
    chart.tip.classList.remove('is-on');
}

function indexAt(event) {
    const rect = chart.plot.getBoundingClientRect();

    return Math.min(chart.n - 1, Math.max(0, Math.floor((event.clientX - rect.left - chart.pad.l) / chart.step)));
}

document.addEventListener('pointermove', (event) => {
    if (chart && chart.plot.contains(event.target)) {
        setActive(indexAt(event), false);
    }
});

document.addEventListener('pointerout', (event) => {
    if (chart && chart.plot.contains(event.target) && !chart.plot.contains(event.relatedTarget)) {
        clearActive();
    }
});

function onKey(event) {
    const plot = event.target.closest?.('[data-sc-plot]');

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
    }
}
