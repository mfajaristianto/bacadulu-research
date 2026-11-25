/*
 * Template & Format Cetak (admin/document-templates, editor). Dimuat bersama admin-document-prompts.js; kerangka async (swap, form JSON,
 * dirty guard) milik modul itu. Di sini hanya perilaku khusus editor format:
 *
 *  - tab pengaturan (ARIA tabs); pada layar sempit pratinjau menjadi tab tersendiri, pada layar lebar selalu berdampingan;
 *  - konversi satuan mm/cm/in di tampilan (nilai kanonik disimpan server dalam mm);
 *  - kolom ukuran kustom dan margin cermin hanya tampil bila relevan;
 *  - pratinjau langsung: nilai form yang BELUM disimpan dikirim ke endpoint pratinjau (tidak menulis apa pun) dan hasil SVG dari
 *    mesin tata letak yang sama dengan ekspor PDF ditampilkan. Pengecekan cetak ikut diperbarui.
 *
 * Server tetap menjadi penentu: validasi, simpan, dan aktivasi tidak dilakukan di sini.
 */

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

const MM = { mm: 1, cm: 10, in: 25.4 };
const BADGE = { ready: 'ok', review: 'warn', blocked: 'bad' };
const WIDE = window.matchMedia('(min-width: 1100px)');

let wired = false;
let activeTab = 'halaman';
let unit = 'mm';
let timer = 0;
let controller = null;
let serial = 0;
let large = false;

export function initPrintFormat() {
    const root = $('[data-pf]');

    if (!root) {
        return;
    }

    if (!wired) {
        wired = true;

        // Tombol "Pratinjau" di daftar membuka editor langsung pada pratinjau (tab Pratinjau di layar sempit).
        if (new URLSearchParams(location.search).has('pratinjau')) {
            activeTab = 'pratinjau';
        }

        document.addEventListener('input', onInput);
        document.addEventListener('change', onChange);
        document.addEventListener('reset', onReset, true);
        document.addEventListener('pf:reveal', (event) => reveal(event.target));
        document.addEventListener('keydown', onKey);
        document.addEventListener('click', onClick);
        WIDE.addEventListener('change', () => activate(activeTab, false));
    }

    unit = $('[data-pf-unitselect]')?.value ?? 'mm';
    applyZoom();
    syncVisibility();
    activate($(`[data-pf-tab="${activeTab}"]`) ? activeTab : 'halaman', false);
}

/* --------------------------------------------------------------------------------------------------- tab */

function activate(key, focus) {
    const tabs = $$('[data-pf-tab]');

    if (!tabs.length) {
        return;
    }

    if (WIDE.matches && key === 'pratinjau') {
        key = 'halaman'; // di layar lebar pratinjau selalu terlihat, bukan tab
    }

    activeTab = key;

    tabs.forEach((tab) => {
        const on = tab.dataset.pfTab === key;

        tab.setAttribute('aria-selected', on ? 'true' : 'false');
        tab.tabIndex = on ? 0 : -1;
        tab.classList.toggle('is-on', on);

        if (on && focus) {
            tab.focus();
        }
    });

    $$('.bd-pf__panel').forEach((panel) => {
        panel.hidden = panel.dataset.pfPanel !== key;
    });

    const preview = $('.bd-pf__preview');

    if (preview) {
        preview.hidden = WIDE.matches ? false : key !== 'pratinjau';
    }

    $('.bd-pf__workspace')?.setAttribute('data-pf-view', key === 'pratinjau' ? 'preview' : 'settings');
}

function reveal(target) {
    const panel = target.closest?.('.bd-pf__panel');

    if (panel) {
        activate(panel.dataset.pfPanel, false);
    }
}

function onClick(event) {
    const tab = event.target.closest?.('[data-pf-tab]');

    if (tab) {
        activate(tab.dataset.pfTab, false);
    }
}

function onKey(event) {
    const tab = event.target.closest?.('[data-pf-tab]');

    if (!tab || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
        return;
    }

    const tabs = $$('[data-pf-tab]').filter((item) => item.offsetParent !== null);
    const at = tabs.indexOf(tab);
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (at + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;

    event.preventDefault();
    activate(tabs[next].dataset.pfTab, true);
}

/* ------------------------------------------------------------------------------- satuan dan bagian kondisional */

const fromMm = (mm, to) => {
    const value = mm / MM[to];

    return Number(value.toFixed(to === 'in' ? 3 : 2));
};

function changeUnit(to) {
    $$('[data-pf-len]').forEach((input) => {
        const mm = Number(input.dataset.mm);

        if (Number.isFinite(mm)) {
            input.value = String(fromMm(mm, to));
        }
    });
    $$('[data-pf-unit]').forEach((label) => {
        label.textContent = to;
    });
    unit = to;
}

function syncVisibility() {
    const size = $('select[name="layout[page][size]"]');
    const mirror = $('input[type="checkbox"][name="layout[margins][mirror]"]');

    $('[data-pf-custom]')?.toggleAttribute('hidden', size?.value !== 'custom');

    if (mirror) {
        $('[data-pf-plain]')?.toggleAttribute('hidden', mirror.checked);
        $('[data-pf-mirrored]')?.toggleAttribute('hidden', !mirror.checked);
    }
}

function onReset(event) {
    if (!event.target.matches?.('form[data-pf-form]')) {
        return;
    }

    // Setelah reset bawaan peramban: kembalikan nilai kanonik dan kondisi tampil, lalu segarkan pratinjau.
    setTimeout(() => {
        unit = $('[data-pf-unitselect]')?.value ?? 'mm';
        $$('[data-pf-len]').forEach((input) => {
            input.dataset.mm = String(Number(input.defaultValue) * MM[unit] || 0);
        });
        syncVisibility();
        refresh(0);
    }, 0);
}

/* ----------------------------------------------------------------------------------------- input dan pratinjau */

function onInput(event) {
    const field = event.target;

    if (field.matches?.('[data-pf-len]')) {
        const value = Number(field.value);

        if (field.value !== '' && Number.isFinite(value)) {
            field.dataset.mm = String(value * MM[unit]); // nilai kanonik mengikuti ketikan, bukan pembulatan tampilan
        }
    }

    if (field.matches?.('[data-pf-field]')) {
        refresh(450);
    }
}

function applyZoom() {
    const box = $('[data-pf-zoom]');

    if (box) {
        box.checked = large;
    }

    $('[data-pf-pages]')?.classList.toggle('is-large', large);
}

function onChange(event) {
    const field = event.target;

    if (field.matches?.('[data-pf-zoom]')) {
        large = field.checked;
        applyZoom();

        return;
    }

    if (field.matches?.('[data-pf-unitselect]')) {
        changeUnit(field.value);
    }

    if (field.matches?.('select[name="layout[page][size]"], input[name="layout[margins][mirror]"]')) {
        syncVisibility();
    }

    if (field.matches?.('[data-pf-field], [data-pf-sample], [data-pf-structure]')) {
        refresh(field.matches('[data-pf-field]') ? 150 : 0);
    }
}

function refresh(delay) {
    const root = $('[data-pf]');
    const url = root?.dataset.pfPreviewUrl;

    if (!url) {
        return;
    }

    clearTimeout(timer);
    timer = setTimeout(() => render(root, url), delay);
}

async function render(root, url) {
    const form = $('form[data-pf-form]', root);
    const status = $('[data-pf-status]');
    const body = new FormData();

    // Hanya isian layout (bila form dapat disunting) yang dikirim. Versi terkunci tidak punya isian aktif: pratinjau dari versi tersimpan.
    const fields = form ? [...new FormData(form)].filter(([key]) => key.startsWith('layout[')) : [];

    fields.forEach(([key, value]) => body.append(key, value));

    if (!fields.length) {
        body.append('v', root.dataset.pfVersion ?? '');
    }

    body.append('contoh', $('[data-pf-sample]')?.value ?? 'all');

    const structure = $('[data-pf-structure]')?.value;

    if (structure) {
        body.append('struktur', structure);
    }

    controller?.abort();
    controller = new AbortController();
    const mine = ++serial;
    const token = $('input[name="_token"]', form ?? document)?.value ?? $('meta[name="csrf-token"]')?.content ?? '';

    $('.bd-pf__preview')?.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            credentials: 'same-origin',
            body,
            signal: controller.signal,
        });
        const json = await response.json().catch(() => null);

        if (mine !== serial) {
            return;
        }

        if (!response.ok || json?.ok === false) {
            stale(status, response.status === 419 ? 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.' : (json?.message ?? 'Pratinjau tidak dapat dibuat.'));

            return;
        }

        paint(json);
    } catch (error) {
        if (error.name !== 'AbortError' && mine === serial) {
            stale(status, 'Koneksi terputus. Pratinjau menampilkan isian terakhir yang berhasil.');
        }
    } finally {
        if (mine === serial) {
            $('.bd-pf__preview')?.removeAttribute('aria-busy');
        }
    }
}

function stale(status, message) {
    $('.bd-pf__preview')?.classList.add('is-stale');

    if (status) {
        status.textContent = `${message} Pratinjau di bawah belum mengikuti isian ini.`;
    }
}

function paint(json) {
    $('.bd-pf__preview')?.classList.remove('is-stale');

    const pages = $('[data-pf-pages]');

    if (pages) {
        pages.innerHTML = json.html; // SVG dari mesin tata letak server; seluruh teks dokumen sudah di-escape di sana
    }

    const status = $('[data-pf-status]');

    if (status) {
        status.textContent = `${json.pages} halaman · ${Math.round(json.width_mm * 10) / 10} × ${Math.round(json.height_mm * 10) / 10} mm`;
    }

    const check = json.check;

    if (!check) {
        return;
    }

    $$('[data-pf-badge], [data-pf-check-badge]').forEach((badge) => {
        badge.textContent = check.label;
        badge.className = badge.className.replace(/bd-pf__badge--\w+/, `bd-pf__badge--${BADGE[check.status] ?? 'warn'}`);
    });

    const list = $('[data-pf-checks]');

    if (list) {
        const text = { ok: 'Baik', warn: 'Perlu diperiksa', error: 'Masalah' };

        list.replaceChildren(...check.items.map((item) => {
            const li = document.createElement('li');
            const hidden = document.createElement('span');

            li.className = `is-${item.level}`;
            hidden.className = 'bd-ix__sr';
            hidden.textContent = `${text[item.level] ?? ''}: `;
            li.append(hidden, document.createTextNode(item.text));

            return li;
        }));
    }
}
