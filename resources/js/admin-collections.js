/*
 * Koleksi (admin/collections/index). Loaded on demand by admin.js when a [data-col] page is open.
 *
 * Progressive enhancement: without JS the filters are a GET form, "Tambah"/"Edit" are links to the form page and
 * saving redirects. With JS nothing reloads the document: the regions marked [data-col-swap] are replaced from a
 * fetched copy of the same URL, the address bar follows through the History API, collection members load into
 * their panel when it opens, and add/edit/add-sources/remove-source post JSON. Rules (duplicate names, pivot
 * uniqueness, authorization) stay on the server. The PDF viewer is Pustaka's own ([data-lib-view] buttons).
 */

const root = document.querySelector('[data-col]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const url = (template, id) => template.replace('__ID__', id);

const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };

export function initAdminCollections() {
    if (!root) {
        return;
    }

    const ui = createUi();

    initList();
    initMembers(ui);
    initFormDialog(ui);
    initPicker(ui);
}

/* ------------------------------------------------------------------------------------------ feedback */

function createUi() {
    const toast = $('[data-col-toast]');
    const dialog = $('[data-col-confirm]');
    let timer = 0;
    const hide = () => {
        clearTimeout(timer);
        toast.hidden = true;
    };

    $('[data-col-toast-close]').addEventListener('click', hide);

    return {
        notify(message, ok = true) {
            clearTimeout(timer);
            toast.className = `bd-col__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-col-toast-text]', toast).textContent = message;
            toast.hidden = false;

            if (ok) {
                timer = setTimeout(hide, 6000); // errors stay until dismissed
            }
        },

        confirm({ title, text, ok }) {
            $('[data-col-confirm-title]').textContent = title;
            $('[data-col-confirm-text]').textContent = text;
            $('[data-col-confirm-ok]').textContent = ok;

            return new Promise((resolve) => {
                dialog.returnValue = '';
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
                $('[data-col-confirm-ok]').onclick = () => dialog.close('ok');
                $('[data-col-confirm-cancel]').onclick = () => dialog.close('cancel');
                dialog.showModal();
            });
        },
    };
}

async function postJson(target, method, body) {
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

// Parses server-rendered HTML safely and moves its nodes into `target` (no innerHTML of unparsed text).
function fill(target, html) {
    const parsed = new DOMParser().parseFromString(html, 'text/html');

    target.replaceChildren(...[...parsed.body.childNodes].map((node) => document.importNode(node, true)));
}

/* --------------------------------------------------------------------------- async list (swap + history) */

let controller = null;

async function swapFrom(target, signal) {
    const response = await fetch(target, { headers: HEADERS_HTML, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(target, location.href).pathname) {
        return false; // session ended: real navigation shows the login page
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
    const open = $$('[data-col-toggle][aria-expanded="true"]').map((button) => button.closest('[data-col-item]').dataset.id);

    $$('[data-col-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-col-swap="${region.dataset.colSwap}"]`);

        if (fresh) {
            region.replaceWith(document.importNode(fresh, true));
        }
    });

    // Panels that were open stay open (their members are fetched again).
    open.forEach((id) => {
        const item = $(`[data-col-item][data-id="${id}"]`);

        if (item) {
            setOpen(item, true);
        }
    });

    return true;
}

// mode: 'push' (new history entry), 'replace' (typing), 'none' (Back/Forward or refresh after saving)
async function go(target, mode = 'push', { quiet = false } = {}) {
    controller?.abort();
    controller = new AbortController();

    const mine = controller;

    if (!quiet) {
        $('[data-col-swap="list"]')?.setAttribute('aria-busy', 'true');
    }

    try {
        if (!(await swapFrom(target, mine.signal))) {
            location.assign(target);

            return;
        }

        if (mode === 'push' && target !== location.pathname + location.search) {
            history.pushState({ col: 1 }, '', target);
        } else if (mode === 'replace') {
            history.replaceState({ col: 1 }, '', target);
        }
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            const list = $('[data-col-swap="list"]');

            if (list && !$('[data-col-load-error]', list)) {
                const note = document.createElement('p');
                const retry = document.createElement('button');

                note.className = 'bd-col__alert';
                note.setAttribute('role', 'alert');
                note.setAttribute('data-col-load-error', '');
                note.append('Daftar tidak bisa dimuat. Periksa koneksi, lalu ');
                retry.type = 'button';
                retry.className = 'bd-col__link';
                retry.setAttribute('data-col-retry', '');
                retry.textContent = 'coba lagi';
                note.append(retry, '.');
                list.prepend(note);
            }
        }
    } finally {
        if (controller === mine) {
            $('[data-col-swap="list"]')?.removeAttribute('aria-busy');
        }
    }
}

const here = () => location.pathname + location.search;

function initList() {
    const form = $('[data-col-filters]');
    const search = $('[data-col-search]');
    let timer = 0;

    history.replaceState({ col: 1 }, '', location.href);

    const currentUrl = () => {
        const params = new URLSearchParams();

        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '' && !(key === 'sort' && value === 'updated')) {
                params.set(key, value.trim());
            }
        });

        const query = params.toString();

        return location.pathname + (query ? `?${query}` : '');
    };

    const request = (mode) => {
        const target = currentUrl();

        if (target !== here()) {
            go(target, mode);
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        request('push');
    });
    $$('[data-col-auto]', form).forEach((select) => select.addEventListener('change', () => {
        clearTimeout(timer);
        request('push');
    }));
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => request('replace'), 350);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-col-retry]')) {
            go(here(), 'none');

            return;
        }

        const reset = event.target.closest('[data-col-reset]');
        const page = event.target.closest('a[data-col-nav]');

        if (reset && event.button === 0 && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            clearTimeout(timer);
            search.value = '';
            $$('select', form).forEach((select) => { select.selectedIndex = 0; });
            go(location.pathname, 'push');
        } else if (page && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
            event.preventDefault();

            const next = new URL(page.href, location.href);

            go(next.pathname + next.search, 'push').then(() => $('[data-col-swap="list"]')?.scrollIntoView({ block: 'start' }));
        }
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.col) {
            return;
        }

        clearTimeout(timer);

        const params = new URLSearchParams(location.search);

        $$('input[name], select[name]', form).forEach((field) => {
            field.value = params.get(field.name) ?? (field.name === 'sort' ? 'updated' : '');
        });
        go(here(), 'none');
    });
}

/* ------------------------------------------------------------------------------- expandable members */

async function loadMembers(item) {
    const body = $('[data-col-body]', item);
    const id = item.dataset.id;

    body.setAttribute('aria-busy', 'true');

    try {
        const response = await fetch(url(root.dataset.membersUrl, id), { headers: HEADERS_HTML, credentials: 'same-origin' });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        fill(body, await response.text());
        item.dataset.loaded = '1';
    } catch {
        const note = document.createElement('p');
        const retry = document.createElement('button');

        note.className = 'bd-col__alert';
        note.setAttribute('role', 'alert');
        note.append('Sumber koleksi tidak bisa dimuat. ');
        retry.type = 'button';
        retry.className = 'bd-col__link';
        retry.textContent = 'Coba lagi';
        retry.addEventListener('click', () => loadMembers(item));
        note.append(retry);
        body.replaceChildren(note);
    } finally {
        body.removeAttribute('aria-busy');
    }
}

function setOpen(item, open) {
    const button = $('[data-col-toggle]', item);
    const inner = $('[data-col-panel] > div', item);

    button.setAttribute('aria-expanded', String(open));
    item.classList.toggle('is-open', open);
    inner.toggleAttribute('inert', !open); // collapsed content must not be reachable by keyboard

    // Members are fetched when a panel opens (and again after a list refresh), never all at once.
    if (open && !item.dataset.loaded) {
        loadMembers(item);
    }
}

function initMembers(ui) {
    document.addEventListener('click', async (event) => {
        const toggle = event.target.closest('[data-col-toggle]');

        if (toggle) {
            const item = toggle.closest('[data-col-item]');

            setOpen(item, toggle.getAttribute('aria-expanded') !== 'true');

            return;
        }

        const remove = event.target.closest('[data-col-remove]');

        if (!remove || remove.getAttribute('aria-busy') === 'true') {
            return;
        }

        const item = remove.closest('[data-col-item]');
        const sure = await ui.confirm({
            title: 'Lepas sumber dari koleksi?',
            text: `"${remove.dataset.title}" dilepas dari "${remove.dataset.collection}". Dokumen dan PDF-nya tetap ada di Pustaka dan di koleksi lain.`,
            ok: 'Lepas dari koleksi',
        });

        if (!sure) {
            return;
        }

        remove.setAttribute('aria-busy', 'true');

        const token = $('input[name="_token"]', $('[data-col-form]')).value;
        const data = new FormData();

        data.set('_token', token);
        data.set('_method', 'DELETE');

        const reply = await postJson(remove.dataset.url, 'POST', data);

        remove.removeAttribute('aria-busy');

        if (!reply.ok) {
            ui.notify(reply.message, false);

            if (reply.status === 409) {
                await loadMembers(item);
            }

            return;
        }

        $('[data-col-count]', item).textContent = `${Number(reply.json.count).toLocaleString('id-ID')} sumber`;
        await loadMembers(item);
        ui.notify(reply.message);
    });

    // The picker asks the list to refresh a collection after sources were added.
    document.addEventListener('col:members-changed', async (event) => {
        const item = $(`[data-col-item][data-id="${event.detail.id}"]`);

        if (item) {
            $('[data-col-count]', item).textContent = `${Number(event.detail.count).toLocaleString('id-ID')} sumber`;
            await loadMembers(item);
        }
    });
}

/* ------------------------------------------------------------------------------- add / edit dialog */

function initFormDialog(ui) {
    const dialog = $('[data-col-dialog]');
    const form = $('[data-col-form]');
    const method = $('[data-col-method]', form);
    const name = $('[data-col-name]', form);
    const description = $('[data-col-description]', form);
    const status = $('[data-col-status]', form);
    const save = $('[data-col-save]', form);
    const general = $('[data-col-error]', form);
    let editing = null;
    let pristine = '';
    let busy = false;

    const snapshot = () => JSON.stringify([name.value, description.value, status.value]);

    const note = (field, message, pointer = null) => {
        const slot = $(`[data-error-for="${field}"]`, form);
        const control = $(`[name="${field}"]`, form);

        slot.textContent = message || '';

        if (pointer && message) {
            const open = document.createElement('a');

            open.href = pointer.url;
            open.className = 'bd-col__link';
            open.textContent = 'Buka koleksi itu';
            slot.append(' ', open);
        }

        control.toggleAttribute('aria-invalid', Boolean(message));

        if (message) {
            control.setAttribute('aria-invalid', 'true');
        }
    };

    const clearNotes = () => {
        ['name', 'description', 'status'].forEach((field) => note(field, ''));
        general.hidden = true;
    };

    const open = (data = null) => {
        editing = data;
        busy = false;
        clearNotes();
        $('[data-col-title]').textContent = data ? 'Edit koleksi' : 'Tambah koleksi';
        $('[data-col-subtitle]').textContent = data ? 'Ubah nama, deskripsi, atau status. Sumber di dalamnya tidak berubah.' : 'Beri nama dan deskripsi singkat. Sumber ditambahkan setelah koleksi dibuat.';
        save.textContent = data ? 'Simpan perubahan' : 'Simpan koleksi';
        form.action = data ? url(root.dataset.updateUrl, data.id) : root.dataset.storeUrl;
        method.disabled = !data;
        name.value = data?.name ?? '';
        description.value = data?.description ?? '';
        status.value = data?.status ?? 'active';
        pristine = snapshot();
        dialog.showModal();
        name.focus();
        name.select();
    };

    const close = () => {
        pristine = snapshot();
        dialog.close();
    };

    const requestClose = async () => {
        if (busy) {
            return;
        }

        if (snapshot() === pristine) {
            close();

            return;
        }

        if (await ui.confirm({ title: 'Buang perubahan?', text: 'Perubahan yang belum disimpan akan hilang.', ok: 'Buang' })) {
            close();
        }
    };

    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-col-new]');
        const edit = event.target.closest('[data-col-edit]');

        if ((!add && !edit) || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        open(edit ? { id: edit.dataset.id, name: edit.dataset.name, description: edit.dataset.description, status: edit.dataset.status } : null);
    });

    $('[data-col-close]', dialog).addEventListener('click', requestClose);
    $('[data-col-cancel]', dialog).addEventListener('click', requestClose);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        requestClose();
    });
    dialog.addEventListener('mousedown', (event) => event.target === dialog && requestClose());
    name.addEventListener('input', () => note('name', ''));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (busy) {
            return;
        }

        clearNotes();

        if (!name.value.trim()) {
            note('name', 'Nama koleksi wajib diisi.');
            name.focus();

            return;
        }

        busy = true;
        save.setAttribute('aria-busy', 'true');
        save.disabled = true;

        const label = save.textContent;

        save.textContent = 'Menyimpan…';

        const reply = await postJson(form.action, 'POST', new FormData(form));

        busy = false;
        save.removeAttribute('aria-busy');
        save.disabled = false;
        save.textContent = label;

        if (reply.ok) {
            close();
            await go(here(), 'none', { quiet: true });
            ui.notify(reply.message);

            return;
        }

        if (reply.status === 422 && reply.json?.errors) {
            let first = null;

            Object.entries(reply.json.errors).forEach(([field, messages]) => {
                note(field, messages[0], field === 'name' ? reply.json.existing : null);
                first ??= $(`[name="${field}"]`, form);
            });
            first?.focus();

            return;
        }

        general.textContent = reply.message;
        general.hidden = false;
    });
}

/* ------------------------------------------------------------------------------- source picker */

function initPicker(ui) {
    const dialog = $('[data-col-picker]');
    const list = $('[data-col-picker-list]', dialog);
    const search = $('[data-col-picker-search]', dialog);
    const count = $('[data-col-picker-count]', dialog);
    const add = $('[data-col-picker-add]', dialog);
    const error = $('[data-col-picker-error]', dialog);
    const selected = new Map(); // id -> title, kept while searching and paging
    let collection = null;
    let timer = 0;
    let loading = null;
    let page = 1;
    let busy = false;

    const refreshCount = () => {
        const n = selected.size;

        count.textContent = n ? `${n} sumber dipilih` : 'Belum ada yang dipilih';
        add.disabled = n === 0 || busy;
        add.textContent = n ? `Tambahkan ${n} sumber` : 'Tambahkan';
    };

    const load = async () => {
        loading?.abort();
        loading = new AbortController();

        const mine = loading;
        const params = new URLSearchParams({ q: search.value.trim(), page: String(page) });

        list.setAttribute('aria-busy', 'true');
        error.hidden = true;

        try {
            const response = await fetch(`${url(root.dataset.candidatesUrl, collection.id)}?${params}`, { headers: HEADERS_HTML, credentials: 'same-origin', signal: mine.signal });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            fill(list, await response.text());
            $$('[data-col-pick]', list).forEach((box) => { box.checked = selected.has(box.value); });
        } catch (failure) {
            if (failure.name !== 'AbortError') {
                error.textContent = 'Daftar sumber tidak bisa dimuat. Periksa koneksi lalu coba lagi.';
                error.hidden = false;
            }
        } finally {
            if (loading === mine) {
                list.removeAttribute('aria-busy');
            }
        }
    };

    const open = (target) => {
        collection = target;
        selected.clear();
        page = 1;
        search.value = '';
        $('[data-col-picker-name]', dialog).textContent = `"${target.name}"`;
        refreshCount();
        list.replaceChildren();
        dialog.showModal();
        search.focus();
        load();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-col-add]');

        if (trigger) {
            open({ id: trigger.dataset.id, name: trigger.dataset.name });
        }
    });

    $('[data-col-picker-close]', dialog).addEventListener('click', () => dialog.close());
    $('[data-col-picker-cancel]', dialog).addEventListener('click', () => dialog.close());
    dialog.addEventListener('mousedown', (event) => event.target === dialog && dialog.close());

    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            page = 1;
            load();
        }, 300);
    });

    list.addEventListener('change', (event) => {
        const box = event.target.closest('[data-col-pick]');

        if (!box) {
            return;
        }

        box.checked ? selected.set(box.value, box.dataset.title) : selected.delete(box.value);
        refreshCount();
    });

    list.addEventListener('click', (event) => {
        const next = event.target.closest('[data-col-page]');

        if (next && !next.disabled) {
            page = Number(next.dataset.colPage);
            load();
        }
    });

    add.addEventListener('click', async () => {
        if (busy || !selected.size) {
            return;
        }

        busy = true;
        add.setAttribute('aria-busy', 'true');
        add.disabled = true;

        const data = new FormData();

        data.set('_token', $('input[name="_token"]', $('[data-col-form]')).value);
        [...selected.keys()].forEach((id) => data.append('document_ids[]', id));

        const reply = await postJson(url(root.dataset.attachUrl, collection.id), 'POST', data);

        busy = false;
        add.removeAttribute('aria-busy');
        refreshCount();

        if (!reply.ok) {
            error.textContent = reply.status === 422 && reply.json?.errors ? Object.values(reply.json.errors)[0][0] : reply.message;
            error.hidden = false;

            return;
        }

        dialog.close();
        document.dispatchEvent(new CustomEvent('col:members-changed', { detail: { id: collection.id, count: reply.json.count } }));
        ui.notify(reply.message);
    });
}
