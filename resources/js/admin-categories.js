/*
 * Kategori (admin/categories/index). Loaded on demand by admin.js when a [data-cat] page is open.
 *
 * Progressive enhancement: without JS the filters are a GET form, "Tambah"/"Edit" are links to the form page and
 * saving redirects. With JS nothing reloads the document: the regions marked [data-cat-swap] are replaced from a
 * fetched copy of the same URL, the address bar follows through the History API, and add/edit happen in a dialog
 * that posts JSON. Every rule (duplicates, slug, parent, cycles) is enforced by the server; the hints here only
 * show the same checks earlier.
 */

const root = document.querySelector('[data-cat]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

const FOLD_KEY = 'bd.cat.folded';

export function initAdminCategories() {
    if (!root) {
        return;
    }

    const notify = createToast();

    initList();
    initFold();
    initDialog(notify);
}

/* ---------------------------------------------------------------------------------------------- toast */

function createToast() {
    const toast = $('[data-cat-toast]');
    let timer = 0;
    const hide = () => {
        clearTimeout(timer);
        toast.hidden = true;
    };

    $('[data-cat-toast-close]').addEventListener('click', hide);

    return (message, ok = true) => {
        clearTimeout(timer);
        toast.className = `bd-cat__toast ${ok ? 'is-ok' : 'is-bad'}`;
        $('[data-cat-toast-text]', toast).textContent = message;
        toast.hidden = false;

        if (ok) {
            timer = setTimeout(hide, 6000); // errors stay until dismissed
        }
    };
}

/* --------------------------------------------------------------------------- async list (swap + history) */

async function swapFrom(url, signal) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin', signal });

    // A redirect to another path means the session ended: let the browser navigate (login page).
    if (new URL(response.url).pathname !== new URL(url, location.href).pathname) {
        return false;
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');

    $$('[data-cat-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-cat-swap="${region.dataset.catSwap}"]`);

        if (fresh) {
            region.replaceWith(document.importNode(fresh, true));
        }
    });

    applyFold();
    document.dispatchEvent(new CustomEvent('cat:swapped'));

    return true;
}

let controller = null;

// mode: 'push' (new history entry), 'replace' (typing), 'none' (Back/Forward or refresh after saving)
async function go(url, mode = 'push', { quiet = false } = {}) {
    controller?.abort();
    controller = new AbortController();

    const mine = controller;

    if (!quiet) {
        $('[data-cat-swap="list"]')?.setAttribute('aria-busy', 'true');
    }

    try {
        if (!(await swapFrom(url, mine.signal))) {
            location.assign(url);

            return;
        }

        if (mode === 'push' && url !== location.pathname + location.search) {
            history.pushState({ cat: 1 }, '', url);
        } else if (mode === 'replace') {
            history.replaceState({ cat: 1 }, '', url);
        }
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            const list = $('[data-cat-swap="list"]');

            if (list && !$('[data-cat-load-error]', list)) {
                const note = document.createElement('p');
                const retry = document.createElement('button');

                note.className = 'bd-cat__alert';
                note.setAttribute('role', 'alert');
                note.setAttribute('data-cat-load-error', '');
                note.append('Daftar tidak bisa dimuat. Periksa koneksi, lalu ');
                retry.type = 'button';
                retry.className = 'bd-cat__link';
                retry.setAttribute('data-cat-retry', '');
                retry.textContent = 'coba lagi';
                note.append(retry, '.');
                list.prepend(note);
            }
        }
    } finally {
        if (controller === mine) {
            $('[data-cat-swap="list"]')?.removeAttribute('aria-busy');
        }
    }
}

function initList() {
    const form = $('[data-cat-filters]');
    const search = $('[data-cat-search]');
    let timer = 0;

    history.replaceState({ cat: 1 }, '', location.href);

    const currentUrl = () => {
        const params = new URLSearchParams();

        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '') {
                params.set(key, value.trim());
            }
        });

        const query = params.toString();

        return location.pathname + (query ? `?${query}` : '');
    };

    const request = (mode) => {
        const url = currentUrl();

        if (url !== location.pathname + location.search) {
            go(url, mode);
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        request('push');
    });
    $$('[data-cat-auto]', form).forEach((select) => select.addEventListener('change', () => {
        clearTimeout(timer);
        request('push');
    }));
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => request('replace'), 350);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-cat-retry]')) {
            go(location.pathname + location.search, 'none');

            return;
        }

        const reset = event.target.closest('[data-cat-reset]');

        if (reset && event.button === 0 && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            clearTimeout(timer);
            form.reset();
            search.value = '';
            $$('select', form).forEach((select) => { select.value = ''; });
            go(location.pathname, 'push');
        }
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.cat) {
            return;
        }

        clearTimeout(timer);

        const params = new URLSearchParams(location.search);

        $$('input[name], select[name]', form).forEach((field) => { field.value = params.get(field.name) ?? ''; });
        go(location.pathname + location.search, 'none');
    });
}

/* ------------------------------------------------------------------------------------------------ fold */

const folded = () => {
    try {
        return new Set(JSON.parse(sessionStorage.getItem(FOLD_KEY) ?? '[]'));
    } catch {
        return new Set();
    }
};

function setFolded(node, fold) {
    const button = $('[data-cat-twist]', node);
    const kids = $('.bd-cat__kids > ul', node);

    if (!button || !kids) {
        return;
    }

    node.classList.toggle('is-folded', fold);
    button.setAttribute('aria-expanded', String(!fold));
    button.setAttribute('aria-label', `${fold ? 'Buka' : 'Ciutkan'} subkategori ${button.dataset.name}`);
    kids.toggleAttribute('inert', fold); // hidden rows must not be reachable by keyboard
}

function applyFold() {
    const ids = folded();

    $$('[data-cat-node]').forEach((node) => setFolded(node, ids.has(node.dataset.id)));
}

function initFold() {
    applyFold();

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cat-twist]');

        if (!button) {
            return;
        }

        const node = button.closest('[data-cat-node]');
        const ids = folded();
        const fold = !node.classList.contains('is-folded');

        fold ? ids.add(node.dataset.id) : ids.delete(node.dataset.id);
        setFolded(node, fold);

        try {
            sessionStorage.setItem(FOLD_KEY, JSON.stringify([...ids]));
        } catch {
            // storage blocked: the fold state simply is not remembered
        }
    });
}

/* ------------------------------------------------------------------------------------- add / edit dialog */

const slugify = (text) => text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/&/g, ' dan ')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

function initDialog(notify) {
    const dialog = $('[data-cat-dialog]');
    const discard = $('[data-cat-discard]');
    const form = $('[data-cat-form]', dialog);
    const method = $('[data-cat-method]', form);
    const name = $('[data-cat-name]', form);
    const slug = $('[data-cat-slug]', form);
    const parent = $('[data-cat-parent]', form);
    const status = $('[data-cat-status]', form);
    const save = $('[data-cat-save]', form);
    const general = $('[data-cat-error]', form);
    const slugText = $('[data-cat-slug-text]', form);
    const suggest = $('[data-cat-slug-suggest]', form);
    const statusHint = $('[data-cat-status-hint]', form);

    let editing = null; // { id, blocked, sources, parent } while editing, null while creating
    let pristine = '';
    let slugTouched = false;
    let busy = false;
    let checking = null;
    let timer = 0;

    const snapshot = () => JSON.stringify([name.value, slug.value, parent.value, status.value]);
    const dirty = () => snapshot() !== pristine;

    const note = (field, message, soft = false, link = null) => {
        const slot = $(`[data-error-for="${field}"]`, form);
        const control = $(`[name="${field}"]`, form);

        slot.textContent = message || '';
        slot.classList.toggle('is-soft', soft);

        if (link && message) {
            const open = document.createElement('button');

            open.type = 'button';
            open.className = 'bd-cat__link';
            open.textContent = 'Buka kategori itu';
            open.addEventListener('click', () => openExisting(link));
            slot.append(' ', open);
        }

        control.toggleAttribute('aria-invalid', Boolean(message) && !soft);

        if (message && !soft) {
            control.setAttribute('aria-invalid', 'true');
        }
    };

    const clearNotes = () => {
        ['name', 'slug', 'parent_id', 'status'].forEach((field) => note(field, ''));
        general.hidden = true;
    };

    const updateSlugUi = () => {
        const wanted = slugify(name.value);

        if (!editing && !slugTouched) {
            slug.value = wanted;
        }

        suggest.hidden = !(wanted && slug.value !== wanted);
        suggest.textContent = `Pakai saran: ${wanted}`;
        slugText.textContent = editing
            ? 'Slug lama dipertahankan saat nama berubah. Mengubahnya mengubah tautan yang memakainya.'
            : (slugTouched ? 'Slug diatur manual.' : 'Dibuat otomatis dari nama (diberi angka bila bentrok); Anda bisa menggantinya.');
    };

    const updateStatusHint = () => {
        statusHint.textContent = editing && editing.sources > 0 && status.value === 'archived'
            ? `${editing.sources} sumber masih memakai kategori ini dan tetap aman; kategori hanya tidak muncul sebagai pilihan baru.`
            : 'Kategori diarsipkan tidak muncul sebagai pilihan baru. Sumber yang sudah memakainya tetap aman.';
    };

    // Same checks as the server runs on save, shown while typing. The server stays the authority.
    const check = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            checking?.abort();
            checking = new AbortController();

            const params = new URLSearchParams({ name: name.value, slug: slug.value, parent_id: parent.value });

            if (editing) {
                params.set('ignore', editing.id);
            }

            if (!name.value.trim() && !slug.value.trim()) {
                return;
            }

            try {
                const response = await fetch(`${root.dataset.checkUrl}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: checking.signal });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                note('name', data.name ? `"${data.name.name}" sudah ada${parent.value ? ' di induk yang sama' : ''}.` : (data.elsewhere ? `Ada kategori bernama sama di induk "${data.elsewhere.parent ?? 'utama'}". Boleh tetap dibuat.` : ''), !data.name && Boolean(data.elsewhere), data.name?.id ?? null);
                // An automatic slug never conflicts: the server adds a number. Only a slug the admin typed can clash.
                const manual = editing || slugTouched;

                note('slug', data.slug && manual ? `Slug sudah dipakai "${data.slug.name}".` : '', false, manual ? data.slug?.id ?? null : null);
            } catch {
                // offline or aborted: the save itself still validates
            }
        }, 350);
    };

    const setParentOptions = (blocked, currentParent) => {
        $$('option', parent).forEach((option) => {
            const id = Number(option.value);
            const archived = option.dataset.status === 'archived' && String(currentParent ?? '') !== option.value;

            option.hidden = Boolean(id) && (blocked.includes(id) || archived);
            option.disabled = option.hidden;
        });
    };

    const open = (data = null) => {
        editing = data ? { id: data.id, blocked: data.blocked, sources: Number(data.sources) || 0 } : null;
        busy = false;
        slugTouched = Boolean(data);
        clearNotes();
        $('[data-cat-title]').textContent = data ? 'Edit kategori' : 'Tambah kategori';
        $('[data-cat-subtitle]').textContent = data ? 'Ubah nama, induk, atau status. Sumber yang memakai kategori ini tidak berubah.' : 'Nama, induk, dan status. Slug dibuat otomatis dan bisa Anda ubah.';
        save.textContent = data ? 'Simpan perubahan' : 'Simpan kategori';
        form.action = data ? root.dataset.updateUrl.replace('__ID__', data.id) : root.dataset.storeUrl;
        method.disabled = !data;
        name.value = data?.name ?? '';
        slug.value = data?.slug ?? '';
        status.value = data?.status ?? 'active';
        setParentOptions(data?.blocked ?? [], data?.parent ?? null);
        parent.value = data?.parent ?? '';
        updateSlugUi();
        updateStatusHint();
        pristine = snapshot();
        dialog.showModal();
        name.focus();
        name.select();
    };

    const close = () => {
        pristine = snapshot(); // the confirmation must not fire again for the same close
        dialog.close();
    };

    const requestClose = () => {
        if (busy) {
            return;
        }

        if (!dirty()) {
            close();

            return;
        }

        discard.showModal();
    };

    const fromRow = (link) => ({
        id: Number(link.dataset.id),
        name: link.dataset.name,
        slug: link.dataset.slug,
        parent: link.dataset.parent || null,
        status: link.dataset.status,
        blocked: link.dataset.blocked.split(',').filter(Boolean).map(Number),
        sources: link.dataset.sources,
    });

    function openExisting(pointer) {
        const row = $(`[data-cat-edit][data-id="${pointer}"]`);

        if (!row) {
            location.assign(`${root.dataset.indexUrl}/${pointer}/edit`);

            return;
        }

        close();
        open(fromRow(row));
    }

    // Opening
    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-cat-new]');
        const edit = event.target.closest('[data-cat-edit]');

        if ((!add && !edit) || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        open(edit ? fromRow(edit) : null);
    });

    // Closing (button, Cancel, Escape, backdrop) always passes through the unsaved-changes check
    $('[data-cat-close]', dialog).addEventListener('click', requestClose);
    $('[data-cat-cancel]', dialog).addEventListener('click', requestClose);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        requestClose();
    });
    dialog.addEventListener('mousedown', (event) => {
        if (event.target === dialog) {
            requestClose();
        }
    });
    $('[data-cat-keep]', discard).addEventListener('click', () => discard.close());
    $('[data-cat-drop]', discard).addEventListener('click', () => {
        discard.close();
        close();
    });

    // Fields
    name.addEventListener('input', () => {
        updateSlugUi();
        note('name', '');
        check();
    });
    slug.addEventListener('input', () => {
        slugTouched = true;
        updateSlugUi();
        note('slug', '');
        check();
    });
    slug.addEventListener('blur', () => {
        const clean = slugify(slug.value);

        if (clean !== slug.value) {
            slug.value = clean;
            updateSlugUi();
        }
    });
    suggest.addEventListener('click', () => {
        slug.value = slugify(name.value);
        slugTouched = true;
        updateSlugUi();
        check();
        slug.focus();
    });
    parent.addEventListener('change', () => {
        note('parent_id', '');
        check();
    });
    status.addEventListener('change', updateStatusHint);

    // Saving
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (busy) {
            return;
        }

        clearNotes();

        if (!name.value.trim()) {
            note('name', 'Nama kategori wajib diisi.');
            name.focus();

            return;
        }

        busy = true;
        save.setAttribute('aria-busy', 'true');
        save.disabled = true;

        const label = save.textContent;
        const data = new FormData(form);

        if (!editing && !slugTouched) {
            data.set('slug', ''); // automatic slug: let the server make it unique
        }

        save.textContent = 'Menyimpan…';

        let reply = { ok: false, status: 0, json: null };

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: data,
            });

            reply = { ok: response.ok, status: response.status, json: await response.json().catch(() => null) };
        } catch {
            reply.status = 0;
        }

        busy = false;
        save.removeAttribute('aria-busy');
        save.disabled = false;
        save.textContent = label;

        if (reply.ok) {
            close();
            await go(location.pathname + location.search, 'none', { quiet: true });
            notify(reply.json?.message ?? 'Kategori disimpan.');

            return;
        }

        if (reply.status === 422 && reply.json?.errors) {
            let first = null;

            Object.entries(reply.json.errors).forEach(([field, messages]) => {
                const pointer = field === 'slug' || field === 'name' ? reply.json.existing?.id ?? null : null;

                note(field, messages[0], false, pointer);
                first ??= $(`[name="${field}"]`, form);
            });

            first?.focus();

            return;
        }

        general.textContent = reply.status === 419
            ? 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.'
            : (reply.status === 0 ? 'Koneksi terputus. Periksa jaringan lalu coba lagi.' : (reply.json?.message ?? 'Kategori tidak bisa disimpan. Coba lagi.'));
        general.hidden = false;
    });
}
