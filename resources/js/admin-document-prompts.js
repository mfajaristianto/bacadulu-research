/*
 * Prompt Dokumen (admin/prompt-documents). Dimuat sesuai kebutuhan oleh admin.js saat halaman [data-dp] terbuka.
 *
 * Peningkatan progresif: setiap aksi adalah tautan atau form nyata (GET filter, POST/PUT dengan CSRF) yang tetap bekerja
 * tanpa JavaScript. Dengan JS dokumen tidak dimuat ulang: region [data-dp-swap] diganti dari salinan URL yang sama, bilah alamat
 * mengikuti lewat History API, dan simpan/aktifkan/revisi dikirim sebagai JSON. Server menegakkan semua aturan (hanya draf yang
 * boleh diedit, aktivasi per bagian, template baru selalu draf); pengecekan di sini hanya menampilkan jawaban yang sama lebih
 * cepat. Tidak ada yang disimpan atau diaktifkan otomatis: "Jadikan Aktif" dinonaktifkan selagi form punya perubahan yang belum
 * disimpan, supaya aktivasi tidak pernah menerbitkan teks yang belum disimpan admin.
 */

import { initWordImport } from './admin-document-word-import.js';
import { initPrintFormat } from './admin-print-format.js';

const root = document.querySelector('[data-dp]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
const WORD = /[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*/gu; // sama dengan DocumentPrompts::wordCount di server

const here = () => location.pathname + location.search;
const plainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

let ui = null;
let controller = null;
let current = '';
let timer = 0;
let pristine = new WeakMap();

export function initAdminDocumentPrompts() {
    if (!root) {
        return;
    }

    ui = createUi();
    current = here();
    history.replaceState({ dp: 1 }, '', location.href);

    wireEvents();
    prepare();
}

/* ---------------------------------------------------------------------------------------------- umpan balik */

function createUi() {
    const toast = $('[data-dp-toast]');
    const dialog = $('[data-dp-confirm]');
    let hideTimer = 0;
    const hide = () => {
        clearTimeout(hideTimer);
        toast.hidden = true;
    };

    $('[data-dp-toast-close]').addEventListener('click', hide);

    return {
        notify(message, ok = true) {
            clearTimeout(hideTimer);
            toast.className = `bd-ix__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-dp-toast-text]', toast).textContent = message;
            toast.hidden = false;

            if (ok) {
                hideTimer = setTimeout(hide, 8000); // galat bertahan sampai ditutup
            }
        },

        // lines berupa string biasa yang dirender lewat textContent: tidak ada yang diparse sebagai HTML
        confirm({ title, lines, ok, cancel = 'Batal', danger = false }) {
            $('[data-dp-confirm-title]').textContent = title;
            $('[data-dp-confirm-text]').replaceChildren(...lines.map((line) => Object.assign(document.createElement('p'), { textContent: line })));

            const okButton = $('[data-dp-confirm-ok]');

            okButton.textContent = ok;
            okButton.classList.toggle('bd-ix__btn--danger', danger);
            okButton.classList.toggle('bd-ix__btn--primary', !danger); // primary + danger = teks merah di atas latar merah
            $('[data-dp-confirm-cancel]').textContent = cancel;

            return new Promise((resolve) => {
                dialog.returnValue = '';
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
                okButton.onclick = () => dialog.close('ok');
                $('[data-dp-confirm-cancel]').onclick = () => dialog.close('cancel');
                dialog.showModal();
            });
        },
    };
}

async function send(target, body, method = 'POST') {
    try {
        const response = await fetch(target, { method, headers: HEADERS_JSON, credentials: 'same-origin', body });
        const json = await response.json().catch(() => null);

        if (response.status === 419) {
            return { ok: false, status: 419, json, message: 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.' };
        }

        return { ok: response.ok && json?.ok !== false, status: response.status, json, message: json?.message ?? (response.ok ? '' : 'Permintaan gagal. Coba lagi.') };
    } catch {
        return { ok: false, status: 0, json: null, message: 'Koneksi terputus. Periksa jaringan lalu coba lagi.' };
    }
}

/* ------------------------------------------------------------------------ navigasi async (swap + riwayat) */

async function swapFrom(target, signal) {
    const response = await fetch(target, { headers: HEADERS_HTML, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(target, location.href).pathname) {
        return false; // sesi berakhir: navigasi nyata menampilkan halaman masuk
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
    const nextMode = next.querySelector('[data-dp]')?.dataset.mode ?? 'list';

    $$('[data-dp-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-dp-swap="${region.dataset.dpSwap}"]`);

        // Filter tidak diganti saat daftar disegarkan agar pengetikan tidak terputus.
        if (!fresh || (region.dataset.dpSwap === 'filters' && region.dataset.mode === nextMode && nextMode === 'list')) {
            return;
        }

        region.replaceWith(document.importNode(fresh, true));
    });

    // Filter yang dibiarkan di tempat tetap harus mengikuti URL (Back/Forward) dan tombol "Atur ulang" harus muncul/hilang.
    const keptForm = $('[data-dp-filters]');
    const freshForm = next.querySelector('[data-dp-filters]');

    if (keptForm && freshForm && nextMode === 'list') {
        $$('input[name], select[name]', keptForm).forEach((field) => {
            const fresh = freshForm.elements[field.name];

            if (fresh && field !== document.activeElement && field.value !== fresh.value) {
                field.value = fresh.value;
            }
        });

        const keptReset = $('[data-dp-reset]', keptForm);
        const freshReset = $('[data-dp-reset]', freshForm);

        if (keptReset && freshReset) {
            keptReset.hidden = freshReset.hidden;
        }
    }

    const modeChanged = root.dataset.mode !== nextMode;

    root.dataset.mode = nextMode;

    return { modeChanged };
}

// mode: 'push' (entri riwayat baru), 'replace' (mengetik / segarkan di tempat), 'none' (Back/Forward)
async function go(target, mode = 'push', { quiet = false, force = false, focus = null } = {}) {
    if (!force && isDirty() && new URL(target, location.href).href !== location.href) {
        const leave = await ui.confirm({
            title: 'Ada perubahan yang belum disimpan',
            lines: ['Perubahan pada form ini akan hilang bila Anda pindah. Draf yang tersimpan tidak terpengaruh.'],
            ok: 'Tinggalkan perubahan',
            cancel: 'Tetap di sini',
            danger: true,
        });

        if (!leave) {
            if (mode === 'none') {
                history.pushState({ dp: 1 }, '', current); // batalkan Back/Forward
            }

            return false;
        }
    }

    controller?.abort();
    controller = new AbortController();
    root.setAttribute('aria-busy', 'true');

    try {
        const result = await swapFrom(target, controller.signal);

        if (result === false) {
            location.assign(target);

            return false;
        }

        if (mode === 'push') {
            history.pushState({ dp: 1 }, '', target);
        } else if (mode === 'replace') {
            history.replaceState({ dp: 1 }, '', target);
        }

        current = here();
        prepare();

        if (result.modeChanged && !quiet) {
            window.scrollTo({ top: 0 });
            $('h1')?.setAttribute('tabindex', '-1');
            $('h1')?.focus({ preventScroll: true });
        }

        if (focus) {
            const again = $$('button, a').find((el) => el.getAttribute('aria-label') === focus && !el.disabled) ?? $$('button, a').find((el) => el.getAttribute('aria-label') === focus);

            again?.focus({ preventScroll: true });
        }

        return true;
    } catch (error) {
        if (error.name === 'AbortError') {
            return false;
        }

        ui.notify('Gagal memuat halaman. Periksa koneksi lalu coba lagi.', false);

        return false;
    } finally {
        root.removeAttribute('aria-busy');
    }
}

function filterUrl(form) {
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        if (String(value).trim() !== '' && !(key === 'sort' && value === 'updated')) {
            params.set(key, String(value).trim());
        }
    });

    const query = params.toString();

    return form.getAttribute('action') + (query ? `?${query}` : '');
}

/* ------------------------------------------------------------------------------------------------- form */

const dirtyForms = () => $$('form[data-dp-dirty]').filter((form) => !form.closest('dialog'));
const serialize = (form) => JSON.stringify([...new FormData(form)].filter(([key]) => key !== '_token'));

function isDirty() {
    // Hasil impor Word yang belum disimpan hidup di memori halaman (bukan di form), jadi ditandai terpisah.
    return Boolean($('[data-dp-word][data-wi-pending="1"]')) || dirtyForms().some((form) => pristine.has(form) && pristine.get(form) !== serialize(form));
}

function prepare() {
    // Impor Word dibundel bersama modul ini (bukan import dinamis): import dinamis menambah modulepreload absolut yang diblokir CSP di lingkungan lokal.
    if ($('[data-dp-word]')) {
        initWordImport();
    }

    // Editor Template & Format Cetak: tab, satuan, pratinjau langsung (pengaturan dipasang ulang setiap region diganti).
    if ($('[data-pf]')) {
        initPrintFormat();
    }

    // Daftar bagian punya scroll sendiri: pastikan bagian terpilih terlihat tanpa menggeser halaman.
    const picked = $('.bd-dp__section.is-selected');
    const box = picked?.closest('.bd-dp__sections');

    if (picked && box && box.scrollHeight > box.clientHeight) {
        box.scrollTop = Math.max(0, picked.offsetTop - 8);
    }

    pristine = new WeakMap();
    $$('form[data-dp-dirty]').forEach((form) => pristine.set(form, serialize(form)));
    $$('textarea[data-dp-text]').forEach(refreshCount);
    refreshDirty();
}

function refreshDirty() {
    const dirty = isDirty();

    $$('[data-dp-activate], [data-dp-revise]').forEach((button) => {
        button.disabled = dirty;
        button.title = dirty ? 'Simpan draf terlebih dahulu' : '';
    });
}

function refreshCount(area) {
    const out = $(`[data-dp-count="${area.name}"]`);
    const words = (area.value.match(WORD) ?? []).length;

    if (out) {
        out.textContent = `${words.toLocaleString('id-ID')} kata · ${area.value.length.toLocaleString('id-ID')} / ${Number(area.dataset.max).toLocaleString('id-ID')} karakter`;
    }

    const known = new Set($$('[data-dp-insert]').map((chip) => chip.dataset.dpInsert));
    const unknown = [...new Set([...area.value.matchAll(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g)].map((m) => m[1]))].filter((name) => !known.has(name));
    const note = $(`[data-error-for="${area.name}"]`);

    if (note && known.size) {
        note.textContent = unknown.length ? `Placeholder tidak dikenal: {${unknown.join('}, {')}}. Gunakan variabel yang tersedia.` : '';
        area.setAttribute('aria-invalid', unknown.length ? 'true' : 'false');
    }
}

function showErrors(form, errors, message) {
    $$('[data-error-for]', form).forEach((note) => {
        note.textContent = '';
    });
    $$('[aria-invalid="true"]', form).forEach((field) => field.removeAttribute('aria-invalid'));

    let first = null;

    Object.entries(errors ?? {}).forEach(([key, messages]) => {
        const name = key.replace(/\.\d+$/, '');
        const note = $(`[data-error-for="${name}"]`, form);
        // Galat bersarang ("layout.margins.top") dipetakan ke nama kolom berkurung ("layout[margins][top]").
        const element = form.elements[name] ?? form.elements[name.replace(/^([^.]+)((?:\.[^.]+)+)$/, (_, head, rest) => `${head}[${rest.slice(1).split('.').join('][')}]`)];
        const field = element instanceof RadioNodeList ? element[0] : element;

        if (note) {
            note.textContent = [].concat(messages)[0];
        }

        if (field?.setAttribute) {
            field.setAttribute('aria-invalid', 'true');
            first ??= field;
        }
    });

    const box = $('[data-dp-error]', form);
    const covered = Object.keys(errors ?? {}).some((key) => $(`[data-error-for="${key.replace(/\.\d+$/, '')}"]`, form));

    // Galat tanpa kolom (mis. aturan server) ditampilkan di kotak peringatan form.
    if (box) {
        box.hidden = covered;
        box.textContent = covered ? '' : message;
    }

    first?.dispatchEvent(new CustomEvent('pf:reveal', { bubbles: true })); // tab format: tampilkan panel yang memuat kolom bermasalah
    first?.focus();
    first?.closest('details')?.setAttribute('open', '');

    return covered || Boolean(box); // false: form ini tidak punya tempat untuk pesan (mis. tombol geser), pemanggil memakai toast
}

async function submitForm(form, submitter) {
    const title = form.dataset.dpAskTitle;

    if (title) {
        const ok = await ui.confirm({
            title,
            lines: [form.dataset.dpAskText ?? ''].filter(Boolean),
            ok: form.dataset.dpAskOk ?? 'Lanjutkan',
            danger: form.hasAttribute('data-dp-ask-danger'),
        });

        if (!ok) {
            return;
        }
    }

    const buttons = $$('button[type="submit"]', form);
    const label = submitter?.textContent;

    buttons.forEach((button) => {
        button.disabled = true;
    });

    if (submitter?.hasAttribute('data-dp-save')) {
        submitter.textContent = 'Menyimpan…';
    }

    const result = await send(form.action, new FormData(form));

    buttons.forEach((button) => {
        button.disabled = false;
    });

    if (submitter && label) {
        submitter.textContent = label;
    }

    if (!result.ok) {
        if (result.status === 422 || result.status === 409) {
            const shown = showErrors(form, result.json?.errors, result.message);

            if (!shown) {
                ui.notify(result.message, false);
            }
        } else {
            ui.notify(result.message, false);
        }

        return;
    }

    $$('[data-error-for]', form).forEach((note) => {
        note.textContent = '';
    });

    if (form.hasAttribute('data-dp-dirty')) {
        pristine.set(form, serialize(form)); // tersimpan: bukan perubahan lagi
    }

    form.closest('dialog')?.close();
    ui.notify(result.message, true);

    const target = result.json?.url ?? here();
    const focus = submitter?.getAttribute('aria-label');

    await go(target, target === here() ? 'replace' : 'push', { force: true, quiet: true, focus });
}

/* ---------------------------------------------------------------------------------------------- peristiwa */

function wireEvents() {
    root.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-dp-nav]');

        if (link && plainClick(event) && !link.target) {
            event.preventDefault();
            go(link.getAttribute('href'), 'push').then((moved) => {
                if (moved && link.closest('.bd-dp__sections') && window.innerWidth < 1024) {
                    $('#dp-pane')?.scrollIntoView({ block: 'start' });
                }
            });

            return;
        }

        const reset = event.target.closest('[data-dp-reset]');

        if (reset && plainClick(event)) {
            event.preventDefault();
            go(reset.getAttribute('href'), 'push');

            return;
        }

        const open = event.target.closest('[data-dp-open]');

        if (open) {
            $(`[data-dp-dialog="${open.dataset.dpOpen}"]`)?.showModal();

            return;
        }

        if (event.target.closest('[data-dp-close]')) {
            event.target.closest('dialog')?.close();

            return;
        }

        const chip = event.target.closest('[data-dp-insert]');

        if (chip) {
            insertVariable(chip);

            return;
        }

        const revert = event.target.closest('[data-dp-revert]');

        if (revert) {
            const form = revert.closest('form');

            form.reset();
            $$('textarea[data-dp-text]', form).forEach(refreshCount);
            $$('[data-error-for]', form).forEach((note) => {
                if (!note.closest('.bd-ix__under')) {
                    note.textContent = '';
                }
            });
            refreshDirty();
        }
    });

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (form.matches('form[data-dp-form]')) {
            event.preventDefault();
            submitForm(form, event.submitter);

            return;
        }

        if (form.matches('form[data-dp-nav-form]')) {
            event.preventDefault();
            go(`${form.getAttribute('action')}?${new URLSearchParams(new FormData(form)).toString()}`, 'push');

            return;
        }

        if (form.matches('form[data-dp-filters]')) {
            event.preventDefault();
            clearTimeout(timer);
            go(filterUrl(form), 'push');
        }
    });

    root.addEventListener('input', (event) => {
        const target = event.target;

        if (target.matches('[data-dp-text]')) {
            refreshCount(target);
        }

        if (target.matches('[data-dp-search]')) {
            clearTimeout(timer);
            timer = setTimeout(() => go(filterUrl(target.form), 'replace'), 350); // debounce; request lama dibatalkan di go()
        }

        refreshDirty();
    });

    root.addEventListener('change', (event) => {
        const target = event.target;

        if (target.matches('[data-dp-auto]')) {
            go(filterUrl(target.form), 'push');
        }

        if (target.matches('[data-dp-type]')) {
            const field = $('[data-dp-type-label]', target.closest('form') ?? target.closest('.bd-ix__fields') ?? document);

            if (field) {
                field.hidden = target.value !== 'other';
            }
        }

        refreshDirty();
    });

    // Dialog: menutup membatalkan isian yang belum dikirim.
    root.addEventListener('close', (event) => {
        if (event.target.matches('dialog[data-dp-dialog]')) {
            $$('form', event.target).forEach((form) => {
                form.reset();
                $$('[data-error-for]', form).forEach((note) => {
                    note.textContent = '';
                });
                const box = $('[data-dp-error]', form);

                if (box) {
                    box.hidden = true;
                }
            });
        }
    }, true);

    // Modul impor Word meminta pesan dan perpindahan lewat peristiwa agar tidak menduplikasi umpan balik dan riwayat.
    root.addEventListener('dp:notify', (event) => ui.notify(event.detail.message, event.detail.ok));
    root.addEventListener('dp:navigate', (event) => {
        ui.notify(event.detail.message, true);
        go(event.detail.url, 'push', { force: true });
    });

    window.addEventListener('popstate', () => go(location.href, 'none'));
    window.addEventListener('beforeunload', (event) => {
        if (isDirty()) {
            event.preventDefault();
        }
    });
}

let lastArea = null;

document.addEventListener('focusin', (event) => {
    if (event.target.matches?.('textarea[data-dp-text]')) {
        lastArea = event.target;
    }
});

function insertVariable(chip) {
    const form = chip.closest('form');
    const area = lastArea && form.contains(lastArea) ? lastArea : $('textarea[name="task_prompt"]', form);

    if (!area) {
        return;
    }

    area.closest('details')?.setAttribute('open', '');
    area.focus();
    area.setRangeText(`{${chip.dataset.dpInsert}}`, area.selectionStart, area.selectionEnd, 'end');
    area.dispatchEvent(new Event('input', { bubbles: true }));
}
