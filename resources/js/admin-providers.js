/*
 * Provider & Analitik AI (admin/providers). Loaded on demand by admin.js when a [data-pv] page is open.
 *
 * Regions marked [data-pv-swap] (stats, table, chart, config, drawer) are replaced from a fetched copy of the same URL, so
 * period, filters, provider detail and Back/Forward never reload the document. Every number is rendered by the server from the
 * same data the Dashboard uses; the chart only draws the JSON the server embedded (no random or generated values). The API key
 * never reaches this file: the page only knows "configured" or not. The connection test and config save run on the backend.
 */

const root = document.querySelector('[data-pv]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
const ALL = ['stats', 'table', 'chart', 'drawer'];
const COLORS = ['#BF4628', '#2A2018', '#2D7757', '#C98A1B', '#6B7F9E', '#8A5A7A'];
const NAMES = { openai: 'OpenAI', mock: 'Mock' };
const SVG = 'http://www.w3.org/2000/svg';
const METRICS = { input: ['Input token'], output: ['Output token'], total: ['Total token'], cost: ['Biaya AI'], runs: ['Request'] };

const here = () => location.pathname + location.search;
const plain = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
const fmtInt = (n) => (n === null || n === undefined ? '—' : Number(n).toLocaleString('id-ID'));
const fmtUsd = (n) => (n === null || n === undefined ? '—' : `$${Number(n).toLocaleString('en-US', { minimumFractionDigits: n < 1 ? 4 : 2, maximumFractionDigits: n < 1 ? 4 : 2 })}`);
const fmtValue = (metric, n) => (metric === 'cost' ? fmtUsd(n) : fmtInt(n));
const compact = (n) => {
    if (n >= 1e9) return `${+(n / 1e9).toFixed(1)} M`;
    if (n >= 1e6) return `${+(n / 1e6).toFixed(1)} jt`;
    if (n >= 1e3) return `${+(n / 1e3).toFixed(1)} rb`;

    return String(+n.toFixed(2));
};

let ui = null;
let controller = null;
let timer = 0;
let metric = 'total';
let hidden = new Set();
let chart = null;
let configPristine = '';

export function initAdminProviders() {
    if (!root) {
        return;
    }

    ui = createUi();
    metric = root.dataset.metric || 'total';
    history.replaceState({ pv: 1 }, '', location.href);

    wireNavigation();
    wireFilters();
    wireActions();
    wireConfig();
    afterSwap(ALL);
}

/* ------------------------------------------------------------------------------------------ feedback */

function createUi() {
    const toast = $('[data-pv-toast]');
    const dialog = $('[data-pv-confirm]');
    let hideTimer = 0;
    const hide = () => {
        clearTimeout(hideTimer);
        toast.hidden = true;
    };

    $('[data-pv-toast-close]').addEventListener('click', hide);

    return {
        notify(message, ok = true) {
            clearTimeout(hideTimer);
            toast.className = `bd-pv__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-pv-toast-text]', toast).textContent = message;
            toast.hidden = false;

            if (ok) {
                hideTimer = setTimeout(hide, 8000); // errors stay until dismissed
            }
        },

        // lines are plain strings rendered with textContent: nothing from the page or server is parsed as HTML
        confirm({ title, lines, ok }) {
            $('[data-pv-confirm-title]').textContent = title;
            $('[data-pv-confirm-text]').replaceChildren(...lines.map((line) => Object.assign(document.createElement('p'), { textContent: line })));
            $('[data-pv-confirm-ok]').textContent = ok;

            return new Promise((resolve) => {
                dialog.returnValue = '';
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
                $('[data-pv-confirm-ok]').onclick = () => dialog.close('ok');
                $('[data-pv-confirm-cancel]').onclick = () => dialog.close('cancel');
                dialog.showModal();
            });
        },
    };
}

const csrf = () => $('input[name="_token"]')?.value ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function send(target, body) {
    try {
        const response = await fetch(target, { method: 'POST', headers: HEADERS_JSON, credentials: 'same-origin', body });
        const json = await response.json().catch(() => null);

        if (response.status === 419) {
            return { ok: false, status: 419, json, message: 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.' };
        }

        return { ok: response.ok && json?.ok !== false, status: response.status, json, message: json?.message ?? (response.ok ? '' : 'Permintaan gagal. Coba lagi.') };
    } catch {
        return { ok: false, status: 0, json: null, message: 'Koneksi terputus. Periksa jaringan lalu coba lagi.' };
    }
}

/* ------------------------------------------------------------------------- async regions (swap + history) */

async function swapFrom(target, regions, signal) {
    const response = await fetch(target, { headers: HEADERS_HTML, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(target, location.href).pathname) {
        return false; // session ended: a real navigation shows the login page
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');

    regions.forEach((name) => {
        const region = $(`[data-pv-swap="${name}"]`);
        const fresh = next.querySelector(`[data-pv-swap="${name}"]`);

        if (region && fresh) {
            region.replaceWith(document.importNode(fresh, true));
        }
    });

    return true;
}

// mode: 'push' (new entry), 'replace' (typing / refresh in place), 'none' (Back/Forward)
async function go(target, mode = 'push', regions = ALL, { quiet = false } = {}) {
    controller?.abort();
    controller = new AbortController();

    const mine = controller;
    const busy = regions.map((name) => $(`[data-pv-swap="${name}"]`)).filter(Boolean);

    if (!quiet) {
        busy.forEach((region) => region.setAttribute('aria-busy', 'true'));
    }

    try {
        if (!(await swapFrom(target, regions, mine.signal))) {
            location.assign(target);

            return false;
        }

        if (mode === 'push' && target !== here()) {
            history.pushState({ pv: 1, open: regions.length === 1 && regions[0] === 'drawer' }, '', target);
        } else if (mode === 'replace') {
            history.replaceState(history.state ?? { pv: 1 }, '', target);
        }

        afterSwap(regions);

        return true;
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            ui.notify('Halaman tidak bisa dimuat. Periksa koneksi, lalu coba lagi.', false);
        }

        return false;
    } finally {
        if (controller === mine) {
            $$('[data-pv-swap][aria-busy]').forEach((region) => region.removeAttribute('aria-busy'));
        }
    }
}

function afterSwap(regions) {
    if (regions.includes('chart')) {
        initChart();
    }

    if (regions.includes('drawer')) {
        const drawer = $('[data-pv-drawer]');

        if (drawer && !drawer.open) {
            drawer.addEventListener('close', onDrawerClose, { once: true });
            drawer.showModal();
        }
    }

    if (regions.includes('config') || configPristine === '') {
        configPristine = serializeConfig();
    }

    syncKeepers();
    syncReset();
}

// The filter form is not swapped, so its hidden period/metric carriers follow the URL here.
function syncKeepers() {
    const form = $('[data-pv-filters]');
    const params = new URLSearchParams(location.search);

    if (!form) {
        return;
    }

    $$('[data-pv-keep]', form).forEach((input) => input.remove());
    ['periode', 'dari', 'sampai', 'metrik'].forEach((key) => {
        if (params.get(key)) {
            form.append(Object.assign(document.createElement('input'), { type: 'hidden', name: key, value: params.get(key) }));
            form.lastElementChild.setAttribute('data-pv-keep', '');
        }
    });
}

function filterUrl(extra = {}) {
    const form = $('[data-pv-filters]');
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        if (typeof value === 'string' && value.trim() !== '' && !(key === 'sort' && value === 'name')) {
            params.set(key, value.trim());
        }
    });
    Object.entries(extra).forEach(([key, value]) => (value ? params.set(key, value) : params.delete(key)));

    const query = params.toString();

    return location.pathname + (query ? `?${query}` : '');
}

function syncReset() {
    const reset = $('[data-pv-reset]', $('[data-pv-filters]') ?? document);
    const params = new URLSearchParams(filterUrl().split('?')[1] ?? '');

    ['periode', 'dari', 'sampai', 'metrik', 'provider'].forEach((key) => params.delete(key));

    if (reset) {
        reset.hidden = params.toString() === '';
    }
}

/* ---------------------------------------------------------------------------------------------- wiring */

function onDrawerClose() {
    const params = new URLSearchParams(location.search);

    if (!params.has('provider')) {
        return;
    }

    if (history.state?.open) {
        history.back(); // opened by a push: Back removes the entry the drawer added
    } else {
        params.delete('provider');
        history.replaceState({ pv: 1 }, '', location.pathname + (params.toString() ? `?${params}` : ''));
    }

    $('[data-pv-swap="drawer"]')?.replaceChildren();
}

function wireNavigation() {
    document.addEventListener('click', (event) => {
        const period = event.target.closest('a[data-pv-nav="period"]');
        const open = event.target.closest('a[data-pv-open]');
        const reset = event.target.closest('a[data-pv-reset]');
        const close = event.target.closest('[data-pv-drawer-close]');

        if (close) {
            $('[data-pv-drawer]')?.close();

            return;
        }

        if (!plain(event)) {
            return;
        }

        if (period) {
            event.preventDefault();
            go(new URL(period.href, location.href).pathname + new URL(period.href, location.href).search, 'push', ALL);
        } else if (open) {
            event.preventDefault();
            go(new URL(open.href, location.href).pathname + new URL(open.href, location.href).search, 'push', ['drawer']);
        } else if (reset) {
            event.preventDefault();
            const form = $('[data-pv-filters]');

            $('[data-pv-search]', form).value = '';
            $$('select', form).forEach((select) => { select.selectedIndex = 0; });
            go(filterUrl(), 'push', ['table']);
        }
    });

    // Drawer: a click on the backdrop closes it.
    document.addEventListener('mousedown', (event) => {
        const drawer = $('[data-pv-drawer]');

        if (drawer?.open && event.target === drawer) {
            drawer.close();
        }
    });

    document.addEventListener('submit', (event) => {
        const range = event.target.closest('[data-pv-range]');

        if (range) {
            event.preventDefault();
            go(`${location.pathname}?${new URLSearchParams(new FormData(range))}`, 'push', ALL);
        }
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.pv) {
            return;
        }

        clearTimeout(timer);
        $('[data-pv-drawer]')?.open && $('[data-pv-drawer]').removeEventListener('close', onDrawerClose);
        $('[data-pv-drawer]')?.open && $('[data-pv-drawer]').close();

        const params = new URLSearchParams(location.search);
        const form = $('[data-pv-filters]');

        $$('input[name="search"], select[name]', form).forEach((field) => {
            field.value = params.get(field.name) ?? (field.name === 'sort' ? 'name' : '');
        });
        metric = params.get('metrik') || 'total';
        go(here(), 'none', ALL);
    });

    window.addEventListener('beforeunload', (event) => {
        if (serializeConfig() !== configPristine) {
            event.preventDefault();
        }
    });
}

function wireFilters() {
    const request = (mode) => {
        const target = filterUrl();

        if (target !== here()) {
            go(target, mode, ['table']);
        }
    };

    document.addEventListener('submit', (event) => {
        if (event.target.closest('[data-pv-filters]')) {
            event.preventDefault();
            clearTimeout(timer);
            request('push');
        }
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-pv-filters] [data-pv-auto]')) {
            clearTimeout(timer);
            request('push');
        }
    });
    document.addEventListener('input', (event) => {
        if (event.target.matches('[data-pv-search]')) {
            clearTimeout(timer);
            timer = setTimeout(() => request('replace'), 350); // debounce; a newer request aborts the older one
        }
    });
}

function wireActions() {
    document.addEventListener('click', async (event) => {
        const test = event.target.closest('[data-pv-test]');

        if (!test || test.disabled) {
            return;
        }

        const name = test.dataset.name;
        const ok = await ui.confirm({
            title: `Periksa koneksi ke ${name}?`,
            lines: [
                `Server akan meminta daftar model ke ${name} (GET /models). Ini hanya metadata: tidak memakai token dan tidak ditagih.`,
                'Kunci API dipakai di server dan tidak pernah dikirim ke browser.',
            ],
            ok: 'Periksa koneksi',
        });

        if (!ok) {
            return;
        }

        $$(`[data-pv-test="${test.dataset.pvTest}"]`).forEach((button) => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });

        const body = new FormData();

        body.append('_token', csrf());

        const reply = await send(root.dataset.testUrl.replace('__ID__', test.dataset.pvTest), body);

        ui.notify(reply.status === 419 || reply.status === 0 ? reply.message : (reply.json?.message ?? reply.message), Boolean(reply.json?.ok));
        await go(here(), 'replace', ['table', 'drawer'], { quiet: true });
    });
}

/* ------------------------------------------------------------------------------------------ config form */

const serializeConfig = () => {
    const form = $('[data-pv-config-form]');

    return form ? JSON.stringify($$('[data-pv-field]', form).map((field) => [field.name, field.value])) : '';
};

const note = (form, field, message) => {
    const slot = $(`[data-error-for="${field}"]`, form);
    const control = $(`[name="${field}"]`, form);

    if (slot) {
        slot.textContent = message || '';
    }

    if (control) {
        control.toggleAttribute('aria-invalid', Boolean(message));

        if (message) {
            control.setAttribute('aria-invalid', 'true');
        }
    }
};

function wireConfig() {
    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-pv-config-form]');

        if (form) {
            note(form, event.target.name, '');
        }
    });

    document.addEventListener('click', async (event) => {
        if (!event.target.closest('[data-pv-config-revert]')) {
            return;
        }

        const form = $('[data-pv-config-form]');

        if (serializeConfig() !== configPristine && !(await ui.confirm({ title: 'Batalkan perubahan?', lines: ['Isian dikembalikan ke konfigurasi yang tersimpan.'], ok: 'Batalkan perubahan' }))) {
            return;
        }

        form.reset();
        $$('[data-error-for]', form).forEach((slot) => note(form, slot.dataset.errorFor, ''));
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-pv-config-form]');

        if (!form) {
            return;
        }

        event.preventDefault();

        if (form.dataset.busy) {
            return;
        }

        $$('[data-error-for]', form).forEach((slot) => note(form, slot.dataset.errorFor, ''));
        $('[data-pv-config-error]', form).hidden = true;

        const provider = $('[name="provider"]', form).value;
        const model = $('[name="model"]', form).value.trim();
        const missing = [
            provider === 'openai' && !model ? ['model', 'Nama model wajib diisi untuk OpenAI.'] : null,
            !(Number($('[name="timeout"]', form).value) >= 10 && Number($('[name="timeout"]', form).value) <= 300) ? ['timeout', 'Timeout 10 sampai 300 detik.'] : null,
            !(Number($('[name="max_output_tokens"]', form).value) >= 256 && Number($('[name="max_output_tokens"]', form).value) <= 32000) ? ['max_output_tokens', 'Batas token 256 sampai 32.000.'] : null,
        ].filter(Boolean);

        if (missing.length) {
            missing.forEach(([field, message]) => note(form, field, message));
            $(`[name="${missing[0][0]}"]`, form).focus();

            return;
        }

        if (serializeConfig() === configPristine) {
            ui.notify('Tidak ada perubahan untuk disimpan.', true);

            return;
        }

        const label = $('[name="provider"] option:checked', form).textContent.replace(/\s*\(.*\)$/, '');
        const lines = [
            `Riset baru akan memakai ${label}${provider !== 'mock' && model ? ` · ${model}` : ''}. Riset yang sudah berjalan tidak berubah.`,
            provider === 'openai' ? 'OpenAI dihitung per token. Tidak ada pengulangan otomatis bila panggilan gagal.' : 'Penyedia mock tidak memanggil API dan tidak menghasilkan biaya.',
        ];

        if (!(await ui.confirm({ title: 'Simpan konfigurasi AI?', lines, ok: 'Simpan konfigurasi' }))) {
            return;
        }

        const save = $('[data-pv-config-save]', form);
        const text = save.textContent;

        form.dataset.busy = '1';
        save.setAttribute('aria-busy', 'true');
        save.disabled = true;
        save.textContent = 'Menyimpan…';

        const reply = await send(form.action, new FormData(form));

        delete form.dataset.busy;
        save.removeAttribute('aria-busy');
        save.disabled = false;
        save.textContent = text;

        if (reply.ok) {
            configPristine = serializeConfig();
            await go(here(), 'replace', ['stats', 'table', 'drawer', 'config'], { quiet: true });
            ui.notify(reply.message, true);

            return;
        }

        if (reply.status === 422 && reply.json?.errors) {
            Object.entries(reply.json.errors).forEach(([field, messages]) => note(form, field, messages[0]));
            $('[aria-invalid="true"]', form)?.focus();

            return;
        }

        const general = $('[data-pv-config-error]', form);

        general.textContent = reply.message;
        general.hidden = false;
    });
}

/* ------------------------------------------------------------------------------------------------ chart */

function svg(name, attrs = {}, text = null) {
    const element = document.createElementNS(SVG, name);

    Object.entries(attrs).forEach(([key, value]) => element.setAttribute(key, value));

    if (text !== null) {
        element.textContent = text;
    }

    return element;
}

function initChart() {
    const script = $('[data-pv-chart-data]');

    if (!script) {
        chart = null;

        return;
    }

    let data;

    try {
        data = JSON.parse(script.textContent);
    } catch {
        return;
    }

    chart?.observer?.disconnect();
    chart = { data, active: null, observer: null };
    metric = METRICS[metric] ? metric : 'total';

    const section = $('[data-pv-chart-root]');

    $$('[data-pv-metric]', section).forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.pvMetric === metric)));
    hidden = new Set([...hidden].filter((id) => data.series.some((s) => s.id === id)));

    if (data.empty) {
        return;
    }

    const plot = $('[data-pv-plot]', section);

    buildLegend();
    draw();
    buildTable();

    chart.observer = new ResizeObserver(() => requestAnimationFrame(draw));
    chart.observer.observe(plot);
    plot.addEventListener('pointermove', (event) => hover(event));
    plot.addEventListener('pointerleave', () => leave());
    plot.addEventListener('keydown', (event) => keyboard(event));
    plot.addEventListener('blur', () => leave());
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-pv-metric]');

    if (!button || !chart) {
        return;
    }

    metric = button.dataset.pvMetric;
    $$('[data-pv-metric]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));

    const params = new URLSearchParams(location.search);

    params.set('metrik', metric);
    history.replaceState(history.state ?? { pv: 1 }, '', `${location.pathname}?${params}`);
    syncKeepers();
    buildLegend();
    draw();
    buildTable();
});

const label = (s) => `${NAMES[s.provider] ?? s.provider} · ${s.model}`;
const colorOf = (s) => COLORS[chart.data.series.indexOf(s) % COLORS.length];
const visible = () => chart.data.series.filter((s) => !hidden.has(s.id) && (metric !== 'cost' || s.priced));

function buildLegend() {
    const box = $('[data-pv-legend]');

    box.replaceChildren(...chart.data.series.map((s) => {
        const button = document.createElement('button');
        const off = metric === 'cost' && !s.priced;

        button.type = 'button';
        button.disabled = off;
        button.setAttribute('aria-pressed', String(!off && !hidden.has(s.id)));
        button.style.setProperty('--sw', colorOf(s));
        button.append(Object.assign(document.createElement('span'), { className: 'bd-pv__swatch' }), label(s));

        if (off) {
            button.append(Object.assign(document.createElement('small'), { textContent: 'tanpa tarif' }));
        }

        button.addEventListener('click', () => {
            hidden.has(s.id) ? hidden.delete(s.id) : hidden.add(s.id);
            buildLegend();
            draw();
            buildTable();
        });

        return button;
    }));
}

function niceMax(value) {
    if (value <= 0) {
        return 1;
    }

    const exponent = 10 ** Math.floor(Math.log10(value));
    const fraction = value / exponent;

    return (fraction <= 1 ? 1 : fraction <= 2 ? 2 : fraction <= 2.5 ? 2.5 : fraction <= 5 ? 5 : 10) * exponent;
}

function layout() {
    const plot = $('[data-pv-plot]');
    const width = Math.max(plot.clientWidth, 280);
    const height = width < 640 ? 300 : 340;
    const m = { l: metric === 'cost' ? 66 : 54, r: 14, t: 26, b: 30 };

    return { plot, width, height, m, w: width - m.l - m.r, h: height - m.t - m.b };
}

function draw() {
    if (!chart) {
        return;
    }

    const L = layout();
    const { data } = chart;
    const n = data.buckets.length;
    const shown = visible();
    const state = $('[data-pv-state]');
    const target = $('[data-pv-svg]');
    const x = (i) => L.m.l + (n === 1 ? L.w / 2 : (i / (n - 1)) * L.w);

    chart.L = L;
    chart.x = x;
    target.setAttribute('viewBox', `0 0 ${L.width} ${L.height}`);
    target.replaceChildren();

    let message = null;

    if (metric === 'cost' && !data.series.some((s) => s.priced)) {
        message = ['Biaya belum dapat dihitung', data.cost_note ?? 'Tarif model belum tersedia.'];
    } else if (metric !== 'runs' && !data.has_tokens) {
        message = ['Token belum tercatat', 'Run ke provider ada, tetapi tidak ada yang melaporkan penggunaan token.'];
    } else if (shown.length === 0) {
        message = ['Tidak ada seri yang dipilih', 'Pilih setidaknya satu provider atau model pada legenda.'];
    }

    state.hidden = message === null;

    if (message) {
        state.replaceChildren(Object.assign(document.createElement('strong'), { textContent: message[0] }), Object.assign(document.createElement('span'), { textContent: message[1] }));
        $('[data-pv-note]').textContent = '';
        chart.shown = [];

        return;
    }

    const values = shown.flatMap((s) => s.metrics[metric]).filter((v) => v !== null);
    const max = niceMax(Math.max(0, ...values) * 1.08);
    const y = (v) => L.m.t + L.h - (v / max) * L.h;

    target.append(svg('text', { x: L.m.l, y: 12, class: 'unit' }, METRICS[metric][0].toUpperCase() + (metric === 'cost' ? ' (USD)' : '')));

    // y grid + labels
    for (let i = 0; i <= 4; i++) {
        const v = (max / 4) * i;

        target.append(svg('line', { x1: L.m.l, x2: L.m.l + L.w, y1: y(v), y2: y(v), class: 'grid' }));
        target.append(svg('text', { x: L.m.l - 8, y: y(v) + 4, 'text-anchor': 'end', class: 'axis' }, metric === 'cost' ? fmtUsd(v).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '') : compact(v)));
    }

    // x labels
    const every = Math.max(1, Math.ceil(n / Math.max(2, Math.floor(L.w / 78))));

    data.buckets.forEach((b, i) => {
        if (i % every === 0 || i === n - 1 && (n - 1) % every >= every / 2) {
            target.append(svg('text', { x: x(i), y: L.height - 8, 'text-anchor': i === 0 ? 'start' : i === n - 1 ? 'end' : 'middle', class: 'axis' }, b.l));
        }
    });

    // "no data" band: leading buckets where no visible series has a value
    const first = shown.map((s) => s.metrics[metric].findIndex((v) => v !== null)).filter((i) => i >= 0);
    const firstKnown = first.length ? Math.min(...first) : 0;

    if (firstKnown > 0) {
        const edge = x(firstKnown) - (L.w / Math.max(1, n - 1)) / 2;

        target.append(svg('rect', { x: L.m.l, y: L.m.t, width: Math.max(0, edge - L.m.l), height: L.h, class: 'nodata' }));
        target.append(svg('text', { x: L.m.l + 8, y: L.m.t + 16, class: 'axis' }, 'Belum ada data'));
    }

    // lines: broken at null, isolated points drawn as dots
    shown.forEach((s) => {
        const color = colorOf(s);
        const series = s.metrics[metric];
        let path = '';
        let run = 0;

        series.forEach((v, i) => {
            if (v === null) {
                run = 0;

                return;
            }

            path += `${run === 0 ? 'M' : 'L'}${x(i).toFixed(1)} ${y(v).toFixed(1)} `;
            run++;

            if ((series[i - 1] ?? null) === null && (series[i + 1] ?? null) === null) {
                target.append(svg('circle', { cx: x(i), cy: y(v), r: 3.5, fill: color, class: 'dot' }));
            }
        });

        target.append(svg('path', { d: path.trim(), stroke: color, class: 'line' }));
    });

    target.append(svg('line', { class: 'cross', 'data-pv-cross': '', y1: L.m.t, y2: L.m.t + L.h, x1: 0, x2: 0, visibility: 'hidden' }));
    chart.shown = shown;
    chart.y = y;
    chart.max = max;

    const note = [];

    if (data.tracked_from && !data.tracked_before_range) {
        note.push(`Pencatatan token dimulai ${data.tracked_from}; hari sebelumnya tidak ada data (bukan nol).`);
    }

    note.push(data.range_text);
    $('[data-pv-note]').textContent = note.join(' ');

    if (chart.active !== null) {
        show(Math.min(chart.active, n - 1));
    }
}

function show(index) {
    const { data, L, shown } = chart;

    if (!shown?.length || index < 0) {
        return;
    }

    chart.active = index;

    const target = $('[data-pv-svg]');
    const px = chart.x(index);
    const cross = $('[data-pv-cross]', target);

    cross.setAttribute('x1', px);
    cross.setAttribute('x2', px);
    cross.setAttribute('visibility', 'visible');
    $$('.dot.hover', target).forEach((dot) => dot.remove());

    const tip = $('[data-pv-tip]');
    const bucket = data.buckets[index];
    const rows = shown.map((s) => ({ s, v: s.metrics[metric][index] }));

    rows.forEach(({ s, v }) => {
        if (v !== null) {
            target.append(svg('circle', { cx: px, cy: chart.y(v), r: 4.5, fill: colorOf(s), class: 'dot hover' }));
        }
    });

    tip.replaceChildren(Object.assign(document.createElement('strong'), { textContent: bucket.t }));
    rows.forEach(({ s, v }) => {
        const line = document.createElement('div');
        const swatch = document.createElement('i');
        const name = document.createElement('span');
        const value = document.createElement('b');

        swatch.style.setProperty('--sw', colorOf(s));
        name.textContent = rows.length > 1 ? label(s) : METRICS[metric][0];
        value.textContent = v === null ? 'Tidak ada data' : fmtValue(metric, v);
        line.append(swatch, name, value);
        tip.append(line);
    });
    tip.hidden = false;

    const width = tip.offsetWidth;
    const left = px + 14 + width > L.width ? px - 14 - width : px + 14;

    tip.style.left = `${Math.max(4, left)}px`;
    tip.style.top = `${L.m.t}px`;

    $('[data-pv-live]')?.remove();
    $('[data-pv-plot]').append(Object.assign(document.createElement('div'), { className: 'bd-pv__sr', textContent: `${bucket.t}: ${rows.map(({ s, v }) => `${label(s)} ${v === null ? 'tidak ada data' : fmtValue(metric, v)}`).join('; ')}` }));
    $('[data-pv-plot] .bd-pv__sr').setAttribute('data-pv-live', '');
    $('[data-pv-plot] .bd-pv__sr').setAttribute('aria-live', 'polite');
}

function hover(event) {
    if (!chart?.shown?.length) {
        return;
    }

    const rect = $('[data-pv-plot]').getBoundingClientRect();
    const n = chart.data.buckets.length;
    const ratio = (event.clientX - rect.left - chart.L.m.l) / chart.L.w;

    show(Math.max(0, Math.min(n - 1, Math.round(ratio * (n - 1)))));
}

function leave() {
    if (!chart) {
        return;
    }

    chart.active = null;
    $('[data-pv-tip]').hidden = true;
    $('[data-pv-cross]')?.setAttribute('visibility', 'hidden');
    $$('.dot.hover').forEach((dot) => dot.remove());
}

function keyboard(event) {
    if (!chart?.shown?.length) {
        return;
    }

    const n = chart.data.buckets.length;
    const step = { ArrowLeft: -1, ArrowRight: 1 }[event.key];

    if (event.key === 'Escape') {
        leave();
    } else if (event.key === 'Home' || event.key === 'End') {
        event.preventDefault();
        show(event.key === 'Home' ? 0 : n - 1);
    } else if (step) {
        event.preventDefault();
        show(Math.max(0, Math.min(n - 1, (chart.active ?? (step > 0 ? -1 : n)) + step)));
    }
}

// The same numbers as a table: for screen readers, and for copying exact values.
function buildTable() {
    const holder = $('[data-pv-table]');

    if (!holder || !chart) {
        return;
    }

    const shown = visible();
    const table = document.createElement('table');
    const head = table.createTHead().insertRow();

    head.append(Object.assign(document.createElement('th'), { textContent: 'Tanggal' }));
    shown.forEach((s) => head.append(Object.assign(document.createElement('th'), { textContent: `${label(s)} (${METRICS[metric][0]})` })));

    const body = table.createTBody();

    chart.data.buckets.forEach((bucket, i) => {
        const row = body.insertRow();

        row.insertCell().textContent = bucket.t;
        shown.forEach((s) => {
            const v = s.metrics[metric][i];

            row.insertCell().textContent = v === null ? '—' : fmtValue(metric, v);
        });
    });

    holder.replaceChildren(table);
}
