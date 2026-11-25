/*
 * Instruksi & Versi (admin/prompts). Loaded on demand by admin.js when a [data-ix] page is open.
 *
 * Progressive enhancement: every action is a real link or form (GET filters, POST/PUT/PATCH with CSRF) that works
 * without JavaScript. With JS nothing reloads the document: the regions marked [data-ix-swap] are replaced from a fetched
 * copy of the same URL, the address bar follows through the History API, and saving/activating/revising post as JSON.
 * The server enforces every rule (draft-only edits, single active version, archived tools stay archived); the checks here
 * only show the same answers sooner. A draft is never saved or activated automatically: "Jadikan Aktif" is disabled while
 * the form has unsaved edits so an activation can never publish text the admin has not saved.
 */

const root = document.querySelector('[data-ix]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };

const here = () => location.pathname + location.search;
const plainClick = (event) => event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;

let ui = null;
let controller = null;
let current = '';
let pristine = '';
let timer = 0;

export function initAdminInstructions() {
    if (!root) {
        return;
    }

    ui = createUi();
    current = here();
    history.replaceState({ ix: 1 }, '', location.href);

    wireNavigation();
    wireFilters();
    wireForms();
    prepareForms();
}

/* ------------------------------------------------------------------------------------------ feedback */

function createUi() {
    const toast = $('[data-ix-toast]');
    const dialog = $('[data-ix-confirm]');
    let hideTimer = 0;
    const hide = () => {
        clearTimeout(hideTimer);
        toast.hidden = true;
    };

    $('[data-ix-toast-close]').addEventListener('click', hide);

    return {
        notify(message, ok = true) {
            clearTimeout(hideTimer);
            toast.className = `bd-ix__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-ix-toast-text]', toast).textContent = message;
            toast.hidden = false;

            if (ok) {
                hideTimer = setTimeout(hide, 8000); // errors stay until dismissed
            }
        },

        // lines are plain strings rendered with textContent: nothing from the page or server is parsed as HTML
        confirm({ title, lines, ok, danger = false }) {
            $('[data-ix-confirm-title]').textContent = title;
            $('[data-ix-confirm-text]').replaceChildren(...lines.map((line) => Object.assign(document.createElement('p'), { textContent: line })));

            const okButton = $('[data-ix-confirm-ok]');

            okButton.textContent = ok;
            okButton.classList.toggle('bd-ix__btn--danger', danger);

            return new Promise((resolve) => {
                dialog.returnValue = '';
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
                okButton.onclick = () => dialog.close('ok');
                $('[data-ix-confirm-cancel]').onclick = () => dialog.close('cancel');
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

/* ------------------------------------------------------------------------- async navigation (swap + history) */

async function swapFrom(target, signal) {
    const response = await fetch(target, { headers: HEADERS_HTML, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(target, location.href).pathname) {
        return false; // session ended: a real navigation shows the login page
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
    const nextMode = next.querySelector('[data-ix]')?.dataset.mode ?? 'list';

    $$('[data-ix-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-ix-swap="${region.dataset.ixSwap}"]`);

        // Filters stay put while the list is refreshed, so typing is never interrupted; they swap only when the page mode changes.
        if (!fresh || (region.dataset.ixSwap === 'filters' && region.dataset.mode === nextMode && nextMode === 'list')) {
            return;
        }

        region.replaceWith(document.importNode(fresh, true));
    });

    root.dataset.mode = nextMode;

    return true;
}

// mode: 'push' (new history entry), 'replace' (typing / refresh in place), 'none' (Back/Forward)
async function go(target, mode = 'push', { quiet = false, focus = false } = {}) {
    controller?.abort();
    controller = new AbortController();

    const mine = controller;
    const previous = $('[data-ix-swap="body"]');

    if (!quiet) {
        previous?.setAttribute('aria-busy', 'true');
    }

    try {
        if (!(await swapFrom(target, mine.signal))) {
            location.assign(target);

            return false;
        }

        if (mode === 'push' && target !== here()) {
            history.pushState({ ix: 1 }, '', target);
        } else if (mode === 'replace') {
            history.replaceState({ ix: 1 }, '', target);
        }

        current = here();
        prepareForms();
        syncReset();

        if (focus) {
            const heading = $('[data-ix-swap="body"] h2');

            heading?.setAttribute('tabindex', '-1');
            heading?.focus({ preventScroll: true });
            $('[data-ix-swap="body"]')?.scrollIntoView({ block: 'start' });
        }

        return true;
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            const body = $('[data-ix-swap="body"]');

            if (body && !$('[data-ix-load-error]', body)) {
                const note = document.createElement('p');
                const retry = document.createElement('button');

                note.className = 'bd-ix__alert';
                note.setAttribute('role', 'alert');
                note.setAttribute('data-ix-load-error', '');
                note.append('Halaman tidak bisa dimuat. Periksa koneksi, lalu ');
                retry.type = 'button';
                retry.className = 'bd-ix__link';
                retry.setAttribute('data-ix-retry', '');
                retry.textContent = 'coba lagi';
                note.append(retry, '.');
                body.prepend(note);
            }
        }

        return false;
    } finally {
        if (controller === mine) {
            $('[data-ix-swap="body"]')?.removeAttribute('aria-busy');
        }
    }
}

// Leaving a form with unsaved edits asks first. Resolves true when it is fine to leave.
async function guard() {
    if (!isDirty()) {
        return true;
    }

    return ui.confirm({ title: 'Buang perubahan?', lines: ['Perubahan pada draf ini belum disimpan dan akan hilang.'], ok: 'Buang' });
}

async function navigate(target, mode = 'push') {
    if (!(await guard())) {
        return;
    }

    clearTimeout(timer);

    const toDetail = new URL(target, location.href).searchParams.has('instruksi');

    await go(target, mode, { focus: toDetail || mode === 'push' });
}

function wireNavigation() {
    document.addEventListener('click', async (event) => {
        if (event.target.closest('[data-ix-retry]')) {
            // In the list, retry what the filters ask for now, not the last URL that loaded.
            go(root.dataset.mode === 'list' && $('[data-ix-filters]') ? filterUrl() : here(), 'replace');

            return;
        }

        const link = event.target.closest('a[data-ix-nav], a[data-ix-reset]');

        if (!link || !plainClick(event) || !root.contains(link)) {
            return;
        }

        event.preventDefault();

        if (link.matches('[data-ix-reset]')) {
            resetFilters();

            return;
        }

        const next = new URL(link.href, location.href);

        navigate(next.pathname + next.search, 'push');
    });

    window.addEventListener('popstate', async (event) => {
        if (!event.state?.ix) {
            return;
        }

        clearTimeout(timer);

        if (isDirty() && !(await guard())) {
            history.pushState({ ix: 1 }, '', current); // stay where the unsaved edits are

            return;
        }

        const params = new URLSearchParams(location.search);

        $$('input[name], select[name]', $('[data-ix-filters]') ?? document.createElement('form')).forEach((field) => {
            field.value = params.get(field.name) ?? (field.name === 'sort' ? 'updated' : '');
        });
        go(here(), 'none');
    });

    window.addEventListener('beforeunload', (event) => {
        if (isDirty()) {
            event.preventDefault();
        }
    });

    // Compare form: a plain GET that is turned into an in-place navigation.
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-ix-compare]');

        if (!form) {
            return;
        }

        event.preventDefault();

        const params = new URLSearchParams(new FormData(form));

        navigate(`${form.getAttribute('action')}?${params}`, 'push');
    });
}

/* ------------------------------------------------------------------------------------------------ filters */

function filterUrl() {
    const form = $('[data-ix-filters]');
    const params = new URLSearchParams();

    new FormData(form).forEach((value, key) => {
        if (typeof value === 'string' && value.trim() !== '' && !(key === 'sort' && value === 'updated')) {
            params.set(key, value.trim());
        }
    });

    const query = params.toString();

    return location.pathname + (query ? `?${query}` : '');
}

function syncReset() {
    const form = $('[data-ix-filters]');
    const reset = form && $('[data-ix-reset]', form);

    if (reset) {
        reset.hidden = filterUrl() === location.pathname;
    }
}

function resetFilters() {
    const form = $('[data-ix-filters]');

    if (form) {
        $('[data-ix-search]', form).value = '';
        $$('select', form).forEach((select) => { select.selectedIndex = 0; });
    }

    clearTimeout(timer);
    go(location.pathname, 'push');
}

function wireFilters() {
    const request = (mode) => {
        const target = filterUrl();

        if (target !== here()) {
            go(target, mode);
        }
    };

    // The filter form is swapped when the page changes mode, so listeners live on the document.
    document.addEventListener('submit', (event) => {
        if (event.target.closest('[data-ix-filters]')) {
            event.preventDefault();
            clearTimeout(timer);
            request('push');
        }
    });
    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-ix-filters] [data-ix-auto]')) {
            clearTimeout(timer);
            request('push');
        }
    });
    document.addEventListener('input', (event) => {
        if (event.target.matches('[data-ix-search]')) {
            clearTimeout(timer);
            timer = setTimeout(() => request('replace'), 350); // debounce; a newer request aborts the older one
        }
    });
}

/* ---------------------------------------------------------------------------------------- forms (edit) */

const serialize = (form) => JSON.stringify($$('[name]', form).filter((field) => field.name !== '_token' && field.name !== '_method').map((field) => [field.name, field.value]));
const formEl = () => $('[data-ix-form]');
const isDirty = () => {
    const form = formEl();

    return Boolean(form) && serialize(form) !== pristine;
};

function updateCounts(form) {
    $$('[data-ix-count]', form).forEach((counter) => {
        const field = $(`[name="${counter.dataset.ixCount}"]`, form);
        const length = field.value.length;
        const max = Number(field.dataset.max);

        counter.textContent = length > max * 0.6 ? `${length.toLocaleString('id-ID')} / ${max.toLocaleString('id-ID')}` : '';
        counter.classList.toggle('is-near', length > max * 0.9);
    });
}

function syncActivate() {
    const form = formEl();
    const dirty = Boolean(form) && serialize(form) !== pristine;

    $$('[data-ix-activate]').forEach((button) => {
        button.disabled = dirty;
        button.title = dirty ? 'Simpan draf dulu agar yang diaktifkan sama dengan yang Anda lihat.' : '';
    });
}

// Run after every swap: remember the clean state of the (new) form.
function prepareForms() {
    const form = formEl();

    pristine = form ? serialize(form) : '';

    if (form) {
        updateCounts(form);
    }

    syncActivate();
}

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

const clearNotes = (form) => {
    $$('[data-error-for]', form).forEach((slot) => note(form, slot.dataset.errorFor, ''));

    const general = $('[data-ix-error]', form);

    if (general) {
        general.hidden = true;
    }
};

function wireForms() {
    document.addEventListener('input', (event) => {
        const form = event.target.closest('[data-ix-form]');

        if (!form) {
            return;
        }

        note(form, event.target.name, '');
        updateCounts(form);
        syncActivate();
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-ix-form]');
        const action = event.target.closest('[data-ix-act]');

        if (form) {
            event.preventDefault();
            await saveForm(form);
        } else if (action) {
            event.preventDefault();
            await runAction(action);
        }
    });

    document.addEventListener('click', async (event) => {
        const revert = event.target.closest('[data-ix-revert]');

        if (!revert) {
            return;
        }

        const form = formEl();

        if (isDirty() && !(await ui.confirm({ title: 'Batalkan perubahan?', lines: ['Isian dikembalikan ke draf yang tersimpan.'], ok: 'Batalkan perubahan' }))) {
            return;
        }

        form.reset();
        clearNotes(form);
        updateCounts(form);
        syncActivate();
    });
}

async function saveForm(form) {
    if (form.dataset.busy) {
        return;
    }

    clearNotes(form);

    const creating = form.dataset.ixKind === 'create';
    const missing = [
        creating && !$('[name="ai_tool_id"]', form).value ? ['ai_tool_id', 'Pilih alat riset untuk instruksi ini.'] : null,
        creating && !$('[name="name"]', form).value.trim() ? ['name', 'Nama instruksi wajib diisi.'] : null,
        !$('[name="task_prompt"]', form).value.trim() ? ['task_prompt', 'Instruksi tugas wajib diisi.'] : null,
    ].filter(Boolean);

    if (missing.length) {
        missing.forEach(([field, message]) => note(form, field, message));

        const first = $(`[name="${missing[0][0]}"]`, form);

        first.closest('details')?.setAttribute('open', '');
        first.focus();

        return;
    }

    const save = $('[data-ix-save]', form);
    const label = save.textContent;

    form.dataset.busy = '1';
    save.setAttribute('aria-busy', 'true');
    save.disabled = true;
    save.textContent = 'Menyimpan…';

    const reply = await send(form.action, new FormData(form));

    delete form.dataset.busy;
    save.removeAttribute('aria-busy');
    save.disabled = false;
    save.textContent = label;

    if (reply.ok) {
        pristine = serialize(form); // saved: leaving no longer asks to discard
        syncActivate();

        if (creating) {
            await go(reply.json.url, 'push', { focus: true });
        } else {
            await go(reply.json.url, 'replace', { quiet: true });
        }

        ui.notify(reply.message, true);

        return;
    }

    if (reply.status === 422 && reply.json?.errors) {
        let first = null;

        Object.entries(reply.json.errors).forEach(([field, messages]) => {
            note(form, field, messages[0]);

            const control = $(`[name="${field}"]`, form);

            control?.closest('details')?.setAttribute('open', '');
            first ??= control;
        });

        if (reply.json.existing?.url) {
            const slot = $('[data-error-for="name"]', form);
            const link = Object.assign(document.createElement('a'), { href: reply.json.existing.url, className: 'bd-ix__link', textContent: 'Buka instruksi itu' });

            link.setAttribute('data-ix-nav', '');
            slot.append(' ', link);
        }

        first?.focus();

        return;
    }

    const general = $('[data-ix-error]', form);

    general.textContent = reply.message;
    general.hidden = false;

    if (reply.status === 409) {
        // The version was locked meanwhile (activated elsewhere): show the real state instead of a stale editor.
        pristine = serialize(form);
        await go(here(), 'replace', { quiet: true });
        ui.notify(reply.message, false);
    }
}

/* ------------------------------------------------------------------------- activate / revise / archive */

async function runAction(form) {
    const kind = form.dataset.ixAct;
    const button = $('button[type="submit"]', form);
    const version = form.dataset.version;

    if (button.disabled || form.dataset.busy) {
        return;
    }

    if (kind === 'activate') {
        const tool = form.dataset.tool;
        const lines = [
            `Jadikan ${version} aktif untuk alat ${tool}?`,
            form.dataset.current
                ? `${form.dataset.current} yang aktif sekarang akan diarsipkan, bukan dihapus, dan bisa diaktifkan lagi kapan saja.`
                : 'Saat ini belum ada versi aktif untuk alat ini.',
            `Riset baru akan memakai ${version}. Riset yang sudah ada tetap tercatat dengan versi yang dulu dipakai.`,
        ];

        if (form.dataset.toolArchived === '1') {
            lines.push('Alat ini masih diarsipkan, jadi instruksi belum tersedia untuk pengguna sampai alatnya diaktifkan lagi.');
        }

        if (!(await ui.confirm({ title: `Jadikan ${version} aktif?`, lines, ok: 'Jadikan Aktif' }))) {
            return;
        }
    } else if (kind === 'archive') {
        const lines = [`Draf ${version} dipindahkan ke arsip. Isinya tetap tersimpan dan tidak dipakai pengguna.`];

        if (isDirty()) {
            lines.push('Perubahan yang belum disimpan pada draf ini tidak ikut tersimpan.');
        }

        if (!(await ui.confirm({ title: `Arsipkan draf ${version}?`, lines, ok: 'Arsipkan' }))) {
            return;
        }
    } else if (kind === 'revise' && !(await guard())) {
        return;
    }

    const label = button.textContent;

    form.dataset.busy = '1';
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
    button.textContent = { activate: 'Mengaktifkan…', revise: 'Membuat revisi…', archive: 'Mengarsipkan…' }[kind];

    const reply = await send(form.action, new FormData(form));

    delete form.dataset.busy;
    button.removeAttribute('aria-busy');
    button.disabled = false;
    button.textContent = label;

    if (reply.ok) {
        pristine = ''; // the form, if any, is about to be replaced by the server's copy
        await go(reply.json.url, kind === 'revise' ? 'push' : 'replace', { quiet: kind !== 'revise', focus: kind === 'revise' });
        ui.notify(reply.message, true);

        return;
    }

    ui.notify(reply.message, false);

    if (reply.status === 409 || reply.status === 422) {
        await go(here(), 'replace', { quiet: true }); // show what the server actually has now
    }
}
