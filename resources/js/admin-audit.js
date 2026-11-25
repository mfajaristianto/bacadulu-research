/*
 * Log Aktivitas (admin/audit). Dimuat sesuai kebutuhan oleh admin.js saat halaman [data-au] terbuka.
 *
 * Peningkatan progresif: semua tautan dan form adalah GET nyata yang tetap bekerja tanpa JavaScript. Dengan JS dokumen tidak dimuat ulang: region
 * [data-au-swap] diganti dari salinan URL yang sama, bilah alamat mengikuti lewat History API (Back/Forward berfungsi), permintaan lama dibatalkan
 * (AbortController), dan kegagalan menampilkan "Coba lagi" yang memakai filter aktif. Detail event dimuat saat dibuka (JSON berisi HTML yang sudah
 * diredaksi server). Halaman ini hanya membaca: tidak ada aksi ubah atau hapus.
 */

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const NS = 'http://www.w3.org/2000/svg';
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const nf = new Intl.NumberFormat('id-ID');

let root = null;
let controller = null;
let current = '';
let timer = 0;
let trigger = null;
let serie = 'all';
let activeBar = -1;
let detailController = null;
let attempt = null; // permintaan terakhir {target, mode, options}; "Coba lagi" mengulang tujuan yang gagal dengan filter yang sama

const here = () => location.pathname + location.search;
const plain = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
const withoutLog = (href) => {
    const url = new URL(href, location.href);

    url.searchParams.delete('log');

    return url.pathname + url.search;
};
const logOf = (href) => new URL(href, location.href).searchParams.get('log');

export function initAdminAudit() {
    root = $('[data-au]');

    if (!root) {
        return;
    }

    current = here();
    history.replaceState({ au: 'list' }, '', location.href);

    wire();
    afterSwap();

    const drawer = $('[data-au-drawer]');

    // Deep link ?log=ID dirender server sebagai dialog terbuka non-modal; jadikan modal agar fokus terkunci dan Esc berfungsi.
    if (drawer?.open && $('[data-au-detail-id]')) {
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
            history.pushState({ au: 'list' }, '', path);
        } else if (mode === 'replace') {
            history.replaceState({ au: 'list' }, '', path);
        }

        current = here();
        afterSwap();
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
    $$('[data-au-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-au-swap="${region.dataset.auSwap}"]`);

        if (!fresh) {
            return;
        }

        // Saat mengetik di kolom cari, form tidak diganti (fokus dan kursor tetap); hanya chip dan tombol "Atur ulang" yang disegarkan.
        if (keepFilters && region.dataset.auSwap === 'filters') {
            $('.bd-au__chips', region)?.replaceWith(document.importNode($('.bd-au__chips', fresh), true));
            $('[data-au-reset-slot]', region)?.replaceWith(document.importNode($('[data-au-reset-slot]', fresh), true));

            return;
        }

        region.replaceWith(document.importNode(fresh, true));
    });

    const notice = $('[data-au-notice]');
    const freshNotice = next.querySelector('[data-au-notice]');

    if (notice && freshNotice) {
        notice.textContent = freshNotice.textContent.trim();
        notice.hidden = freshNotice.hidden;
    }
}

function afterSwap() {
    drawCharts();
}

/* ----------------------------------------------------------------------------------------------- galat */

function showError(message) {
    const box = $('[data-au-error]');

    if (box) {
        $('[data-au-error-text]', box).textContent = message;
        box.hidden = false;
    }
}

function hideError() {
    const box = $('[data-au-error]');

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
        const link = event.target.closest('a[data-au-nav]');

        if (link && plain(event) && !link.target) {
            event.preventDefault();
            go(link.getAttribute('href'));

            return;
        }

        const row = event.target.closest('a[data-au-detail]');

        if (row && plain(event)) {
            event.preventDefault();
            trigger = row;
            loadDetail(row.dataset.auDetail, row.getAttribute('href'));

            return;
        }

        if (event.target.closest('[data-au-close]')) {
            closeDrawer();

            return;
        }

        if (event.target.closest('[data-au-retry]')) {
            go(attempt?.target ?? here(), attempt?.mode ?? 'none', attempt?.options);

            return;
        }

        const toggle = event.target.closest('[data-au-custom-toggle]');

        if (toggle) {
            const form = $('#au-custom');
            const open = form.hidden;

            form.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));

            if (open) {
                $('input[name="dari"]', form)?.focus();
            }

            return;
        }

        const serieButton = event.target.closest('[data-au-serie]');

        if (serieButton) {
            selectSerie(serieButton.dataset.auSerie);

            return;
        }

        const copy = event.target.closest('[data-au-copy]');

        if (copy) {
            copyJson(copy);
        }

        // Klik pada latar dialog (di luar panel) menutup detail.
        if (event.target.matches('dialog[data-au-drawer]')) {
            closeDrawer();
        }
    });

    root.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-au-form]');

        if (form) {
            event.preventDefault();
            clearTimeout(timer);
            go(formUrl(form));
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('[data-au-search]')) {
            clearTimeout(timer);
            timer = setTimeout(() => go(formUrl(event.target.form), 'replace', { keepFilters: true }), 350);
        }
    });

    root.addEventListener('change', (event) => {
        if (event.target.matches('[data-au-auto]')) {
            go(formUrl(event.target.form));
        }
    });

    root.addEventListener('keydown', onChartKey);
    root.addEventListener('mouseover', (event) => {
        const bar = event.target.closest?.('[data-au-bar]');

        if (bar) {
            setActive(Number(bar.dataset.auBar), false);
        }
    });

    const drawer = $('[data-au-drawer]');

    drawer?.addEventListener('close', () => {
        $('[data-au-drawer-body]').replaceChildren();

        if (history.state?.au === 'detail') {
            history.back();
        } else if (logOf(here())) {
            history.replaceState({ au: 'list' }, '', withoutLog(here()));
            current = here();
        }

        trigger?.focus?.();
    });

    window.addEventListener('popstate', () => {
        const target = here();

        if (withoutLog(target) === withoutLog(current)) {
            // Hanya detail yang berubah: buka/tutup panel tanpa memuat ulang daftar.
            current = target;

            if (logOf(target)) {
                const row = $(`a[data-au-id="${logOf(target)}"]`);
                loadDetail(row?.dataset.auDetail ?? `${root.dataset.auUrl}/${logOf(target)}`, target, false);
            } else if (drawer?.open) {
                $('[data-au-drawer-body]').replaceChildren();
                drawer.close();
            }

            return;
        }

        const hadLog = Boolean(logOf(target));

        go(withoutLog(target), 'none').then(() => {
            if (hadLog) {
                loadDetail(`${root.dataset.auUrl}/${logOf(target)}`, target, false);
            }
        });
        current = target;
    });
}

/* ------------------------------------------------------------------------------------------------ detail */

async function loadDetail(endpoint, href, push = true) {
    detailController?.abort();
    detailController = new AbortController();
    const mine = detailController;
    const body = $('[data-au-drawer-body]');

    const loading = document.createElement('p');

    loading.className = 'bd-au__loading';
    loading.setAttribute('role', 'status');
    loading.textContent = 'Memuat detail…';
    body.replaceChildren(loading);
    openDrawer();

    try {
        const response = await fetch(endpoint, { headers: HEADERS_JSON, credentials: 'same-origin', signal: mine.signal });

        if (!response.ok) {
            throw new Error(response.status === 404 ? 'Event tidak ditemukan.' : 'Detail tidak dapat dimuat.');
        }

        const json = await response.json();

        // Isi dibuat server (Blade, semua nilai di-escape dan payload diredaksi); diurai sebagai dokumen terpisah lalu dipindahkan.
        const doc = new DOMParser().parseFromString(json.html, 'text/html');

        body.replaceChildren(...doc.body.childNodes);
        body.focus();

        if (push && withoutLog(href) === withoutLog(here())) {
            history.pushState({ au: 'detail' }, '', href);
            current = here();
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            const p = document.createElement('p');

            p.className = 'bd-au__loading is-error';
            p.setAttribute('role', 'alert');
            p.textContent = error.message || 'Detail tidak dapat dimuat.';
            body.replaceChildren(p);
        }
    }
}

function openDrawer() {
    const drawer = $('[data-au-drawer]');

    if (drawer && !drawer.open) {
        drawer.showModal();
    }
}

function closeDrawer() {
    const drawer = $('[data-au-drawer]');

    if (drawer?.open) {
        drawer.close();
    }
}

async function copyJson(button) {
    const pre = $('[data-au-json]', button.closest('[data-au-raw]'));
    const state = $('[data-au-copy-state]', button.closest('[data-au-raw]'));
    const text = pre?.textContent ?? '';

    try {
        await navigator.clipboard.writeText(text);
        state.textContent = 'Tersalin.';
    } catch {
        const range = document.createRange();

        range.selectNodeContents(pre);
        getSelection().removeAllRanges();
        getSelection().addRange(range);
        state.textContent = 'Teks dipilih; tekan Ctrl+C untuk menyalin.';
    }

    setTimeout(() => {
        state.textContent = '';
    }, 2500);
}

/* ------------------------------------------------------------------------------------------------ grafik */

function drawCharts() {
    const box = $('[data-au-chart]');
    const plot = $('[data-au-plot]', box ?? document);

    if (!box || !plot) {
        return;
    }

    let payload;

    try {
        payload = JSON.parse(box.dataset.auPayload);
    } catch {
        return;
    }

    if (!payload.series[serie]) {
        serie = 'all';
    }

    $$('[data-au-serie]', box).forEach((button) => {
        const on = button.dataset.auSerie === serie;

        button.classList.toggle('is-on', on);
        button.setAttribute('aria-selected', String(on));
        button.tabIndex = on ? 0 : -1;
    });

    const data = payload.series[serie];
    const width = Math.max(260, plot.clientWidth);
    const height = width < 480 ? 190 : 240;
    const pad = { l: 34, r: 8, t: 12, b: 26 };
    const inner = { w: width - pad.l - pad.r, h: height - pad.t - pad.b };
    const max = niceMax(data.max);
    const step = inner.w / data.points.length;
    const barW = Math.max(3, Math.min(28, step * 0.64));
    const svg = el('svg', { viewBox: `0 0 ${width} ${height}`, width: '100%', height, role: 'img', 'aria-hidden': 'true', class: 'au-svg' });

    for (let i = 0; i <= 4; i++) {
        const value = (max / 4) * i;
        const y = pad.t + inner.h - (inner.h * i) / 4;

        svg.append(el('line', { x1: pad.l, x2: width - pad.r, y1: y, y2: y, class: i === 0 ? 'au-axis' : 'au-grid' }));
        svg.append(text(nf.format(Math.round(value)), pad.l - 6, y + 4, 'au-ytick', 'end'));
    }

    const every = Math.ceil(data.points.length / Math.max(2, Math.floor(inner.w / 64)));

    data.points.forEach((n, i) => {
        const x = pad.l + step * i + (step - barW) / 2;
        const h = max ? (inner.h * n) / max : 0;
        const rect = el('rect', { x, y: pad.t + inner.h - h, width: barW, height: Math.max(n > 0 ? 2 : 0, h), rx: 2, class: 'au-bar', 'data-au-bar': i });
        const hit = el('rect', { x: pad.l + step * i, y: pad.t, width: step, height: inner.h, class: 'au-hit', 'data-au-bar': i });

        svg.append(rect, hit);

        if (i % every === 0) {
            svg.append(text(payload.labels[i], pad.l + step * i + step / 2, height - 8, 'au-xtick', 'middle'));
        }
    });

    plot.replaceChildren(svg);
    plot.tabIndex = 0;
    plot.dataset.count = String(data.points.length);
    activeBar = -1;
}

function niceMax(value) {
    if (value <= 4) {
        return 4;
    }

    const pow = 10 ** Math.floor(Math.log10(value));
    const n = value / pow;

    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 4 ? 4 : n <= 5 ? 5 : 10) * pow;
}

function el(name, attributes) {
    const node = document.createElementNS(NS, name);

    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));

    return node;
}

function text(content, x, y, cls, anchor) {
    const node = el('text', { x, y, class: cls, 'text-anchor': anchor });

    node.textContent = content;

    return node;
}

function selectSerie(key) {
    serie = key;
    drawCharts();
}

function setActive(index, announce = true) {
    const box = $('[data-au-chart]');
    const plot = $('[data-au-plot]', box ?? document);

    if (!box || !plot) {
        return;
    }

    const payload = JSON.parse(box.dataset.auPayload);
    const data = payload.series[serie];

    if (!data || index < 0 || index >= data.points.length) {
        return;
    }

    activeBar = index;
    $$('.au-bar', plot).forEach((bar) => bar.classList.toggle('is-on', Number(bar.dataset.auBar) === index));

    const message = `${payload.labels[index]}: ${nf.format(data.points[index])} ${data.label.toLowerCase()}`;
    const tip = $('[data-au-tip]', box);

    if (tip) {
        tip.textContent = message;
        tip.classList.add('is-live');
    }

    if (announce) {
        plot.setAttribute('aria-label', message);
    }
}

function onChartKey(event) {
    const plot = event.target.closest?.('[data-au-plot]');

    if (plot) {
        const count = Number(plot.dataset.count || 0);
        const keys = { ArrowRight: 1, ArrowLeft: -1 };

        if (event.key in keys) {
            event.preventDefault();
            setActive(Math.min(count - 1, Math.max(0, (activeBar < 0 ? (keys[event.key] > 0 ? -1 : count) : activeBar) + keys[event.key])));
        } else if (event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            setActive(event.key === 'Home' ? 0 : count - 1);
        }
    }

    const tab = event.target.closest?.('[data-au-serie]');

    if (tab && ['ArrowRight', 'ArrowLeft'].includes(event.key)) {
        const tabs = $$('[data-au-serie]', tab.parentElement);
        const next = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length];

        event.preventDefault();
        selectSerie(next.dataset.auSerie);
        $(`[data-au-serie="${next.dataset.auSerie}"]`)?.focus();
    }
}

window.addEventListener('resize', () => {
    clearTimeout(window.__auResize);
    window.__auResize = setTimeout(() => root && drawCharts(), 150);
});
