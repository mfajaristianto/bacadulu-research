/*
 * Alat Riset (admin/tools/index). Loaded on demand by admin.js when a [data-tl] page is open.
 *
 * Progressive enhancement: without JS the filters are a GET form, "Tambah"/"Edit" are links to the form page and saving
 * redirects. With JS nothing reloads the document: the regions marked [data-tl-swap] are replaced from a fetched copy of
 * the same URL, the address bar follows through the History API, and add/edit happen in a dialog that posts JSON. The
 * slug (code) of an existing tool is locked until the admin deliberately unlocks it and confirms. Every rule (unique slug,
 * slug format, code-change confirmation) is enforced by the server; the hints here only show the same checks earlier.
 * This page never edits instructions: it links to Versi Instruksi.
 */

const root = document.querySelector('[data-tl]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const url = (template, id) => template.replace('__ID__', id);

const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };

export function initAdminTools() {
    if (!root) {
        return;
    }

    const ui = createUi();

    initList();
    initDetails();
    initDialog(ui);
}

/* ------------------------------------------------------------------------------------------ feedback */

function createUi() {
    const toast = $('[data-tl-toast]');
    const dialog = $('[data-tl-confirm]');
    let timer = 0;
    const hide = () => {
        clearTimeout(timer);
        toast.hidden = true;
    };

    $('[data-tl-toast-close]').addEventListener('click', hide);

    return {
        notify(message, ok = true, link = null) {
            clearTimeout(timer);
            toast.className = `bd-tl__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-tl-toast-text]', toast).textContent = message;

            const anchor = $('[data-tl-toast-link]', toast);

            anchor.hidden = !link;

            if (link) {
                anchor.href = link;
            }

            toast.hidden = false;

            if (ok) {
                timer = setTimeout(hide, 8000); // errors stay until dismissed
            }
        },

        confirm({ title, text, ok }) {
            $('[data-tl-confirm-title]').textContent = title;
            $('[data-tl-confirm-text]').textContent = text;
            $('[data-tl-confirm-ok]').textContent = ok;

            return new Promise((resolve) => {
                dialog.returnValue = '';
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
                $('[data-tl-confirm-ok]').onclick = () => dialog.close('ok');
                $('[data-tl-confirm-cancel]').onclick = () => dialog.close('cancel');
                dialog.showModal();
            });
        },
    };
}

async function postJson(target, body) {
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

/* --------------------------------------------------------------------------- async list (swap + history) */

let controller = null;

async function swapFrom(target, signal) {
    const response = await fetch(target, { headers: HEADERS_HTML, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(target, location.href).pathname) {
        return false; // session ended: a real navigation shows the login page
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
    const open = $$('[data-tl-toggle][aria-expanded="true"]').map((button) => button.closest('[data-tl-item]').dataset.id);

    $$('[data-tl-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-tl-swap="${region.dataset.tlSwap}"]`);

        if (fresh) {
            region.replaceWith(document.importNode(fresh, true));
        }
    });

    open.forEach((id) => {
        const item = $(`[data-tl-item][data-id="${id}"]`);

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
        $('[data-tl-swap="list"]')?.setAttribute('aria-busy', 'true');
    }

    try {
        if (!(await swapFrom(target, mine.signal))) {
            location.assign(target);

            return;
        }

        if (mode === 'push' && target !== location.pathname + location.search) {
            history.pushState({ tl: 1 }, '', target);
        } else if (mode === 'replace') {
            history.replaceState({ tl: 1 }, '', target);
        }
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            const list = $('[data-tl-swap="list"]');

            if (list && !$('[data-tl-load-error]', list)) {
                const note = document.createElement('p');
                const retry = document.createElement('button');

                note.className = 'bd-tl__alert';
                note.setAttribute('role', 'alert');
                note.setAttribute('data-tl-load-error', '');
                note.append('Daftar tidak bisa dimuat. Periksa koneksi, lalu ');
                retry.type = 'button';
                retry.className = 'bd-tl__link';
                retry.setAttribute('data-tl-retry', '');
                retry.textContent = 'coba lagi';
                note.append(retry, '.');
                list.prepend(note);
            }
        }
    } finally {
        if (controller === mine) {
            $('[data-tl-swap="list"]')?.removeAttribute('aria-busy');
        }
    }
}

const here = () => location.pathname + location.search;

function initList() {
    const form = $('[data-tl-filters]');
    const search = $('[data-tl-search]');
    let timer = 0;

    history.replaceState({ tl: 1 }, '', location.href);

    const currentUrl = () => {
        const params = new URLSearchParams();

        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '' && !(key === 'sort' && value === 'status')) {
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
    $$('[data-tl-auto]', form).forEach((select) => select.addEventListener('change', () => {
        clearTimeout(timer);
        request('push');
    }));
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => request('replace'), 350);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-tl-retry]')) {
            go(currentUrl(), 'replace'); // retry what the filters ask for now, not the last URL that loaded

            return;
        }

        const reset = event.target.closest('[data-tl-reset]');
        const page = event.target.closest('a[data-tl-nav]');

        if (reset && event.button === 0 && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            clearTimeout(timer);
            search.value = '';
            $$('select', form).forEach((select) => { select.selectedIndex = 0; });
            go(location.pathname, 'push');
        } else if (page && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
            event.preventDefault();

            const next = new URL(page.href, location.href);

            go(next.pathname + next.search, 'push').then(() => $('[data-tl-swap="list"]')?.scrollIntoView({ block: 'start' }));
        }
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.tl) {
            return;
        }

        clearTimeout(timer);

        const params = new URLSearchParams(location.search);

        $$('input[name], select[name]', form).forEach((field) => {
            field.value = params.get(field.name) ?? (field.name === 'sort' ? 'status' : '');
        });
        go(here(), 'none');
    });
}

/* ------------------------------------------------------------------------------- expandable detail */

function setOpen(item, open) {
    $('[data-tl-toggle]', item).setAttribute('aria-expanded', String(open));
    item.classList.toggle('is-open', open);
    $('.bd-tl__panel > div', item).toggleAttribute('inert', !open); // collapsed content must not be reachable by keyboard
}

function initDetails() {
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-tl-toggle]');

        if (toggle) {
            setOpen(toggle.closest('[data-tl-item]'), toggle.getAttribute('aria-expanded') !== 'true');
        }
    });
}

/* ------------------------------------------------------------------------------- add / edit dialog */

const slugify = (text) => text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

function initDialog(ui) {
    const dialog = $('[data-tl-dialog]');
    const form = $('[data-tl-form]');
    const method = $('[data-tl-method]', form);
    const confirmCode = $('[data-tl-confirm-code]', form);
    const name = $('[data-tl-name]', form);
    const code = $('[data-tl-code]', form);
    const unlock = $('[data-tl-unlock]', form);
    const codeHint = $('[data-tl-code-hint]', form);
    const description = $('[data-tl-description]', form);
    const status = $('[data-tl-status]', form);
    const statusHint = $('[data-tl-status-hint]', form);
    const save = $('[data-tl-save]', form);
    const general = $('[data-tl-error]', form);
    const manage = $('[data-tl-manage]', form);

    let editing = null;
    let pristine = '';
    let busy = false;
    let opener = null;
    let codeTouched = false;
    let checking = null;
    let timer = 0;

    const snapshot = () => JSON.stringify([name.value, code.value, description.value, status.value, confirmCode.disabled]);

    const say = (field, message, pointer = null) => {
        const slot = $(`[data-error-for="${field}"]`, form);
        const control = $(`[name="${field}"]`, form);

        slot.textContent = message || '';

        if (pointer && message) {
            const link = document.createElement('a');

            link.href = pointer.url;
            link.className = 'bd-tl__link';
            link.textContent = 'Buka alat itu';
            slot.append(' ', link);
        }

        control?.toggleAttribute('aria-invalid', Boolean(message));

        if (message) {
            control?.setAttribute('aria-invalid', 'true');
        }
    };

    const clearNotes = () => {
        ['name', 'code', 'description', 'status'].forEach((field) => say(field, ''));
        general.hidden = true;
    };

    const syncCode = () => {
        if (!editing && !codeTouched) {
            code.value = slugify(name.value);
        }
    };

    const updateStatusHint = () => {
        statusHint.textContent = status.value === 'archived'
            ? 'Alat diarsipkan tidak tersedia untuk pengguna, termasuk semua instruksinya. Instruksi dan versinya tetap tersimpan.'
            : 'Alat aktif tersedia untuk pengguna bila punya instruksi AI aktif.';
    };

    // Same slug check the server runs on save, shown while typing. The server stays the authority.
    const scheduleCheck = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            checking?.abort();
            checking = new AbortController();

            if (!code.value.trim() || code.readOnly) {
                return;
            }

            const params = new URLSearchParams({ code: code.value });

            if (editing) {
                params.set('ignore', editing.id);
            }

            try {
                const response = await fetch(`${root.dataset.checkUrl}?${params}`, { headers: HEADERS_JSON, credentials: 'same-origin', signal: checking.signal });

                if (response.ok) {
                    const data = await response.json();

                    say('code', data.code ? `Slug sudah dipakai oleh alat "${data.code.name}".` : '', data.code);
                }
            } catch {
                // offline or aborted: the save itself still validates
            }
        }, 350);
    };

    const lock = (on) => {
        code.readOnly = on;
        unlock.hidden = !on;
        confirmCode.disabled = true;
        codeHint.textContent = on
            ? 'Slug dipakai logika riset dan keluaran, jadi dikunci. Ubah hanya bila perlu.'
            : 'Huruf kecil, angka, dan tanda hubung (mis. research-gap).';
    };

    const open = (data = null, trigger = null) => {
        opener = trigger;
        editing = data;
        busy = false;
        codeTouched = Boolean(data);
        clearNotes();
        $('[data-tl-title]').textContent = data ? 'Edit alat' : 'Tambah alat';
        $('[data-tl-subtitle]').textContent = data ? 'Ubah nama, deskripsi, atau status. Instruksi AI tidak berubah dari sini.' : 'Nama, slug, deskripsi, dan status. Instruksi AI dihubungkan di Versi Instruksi.';
        save.textContent = data ? 'Simpan perubahan' : 'Simpan alat';
        form.action = data ? url(root.dataset.updateUrl, data.id) : root.dataset.storeUrl;
        method.disabled = !data;
        name.value = data?.name ?? '';
        code.value = data?.code ?? '';
        description.value = data?.description ?? '';
        status.value = data?.status ?? 'active';
        lock(Boolean(data));
        manage.hidden = !data;

        if (data) {
            manage.href = data.manage;
        }

        updateStatusHint();
        pristine = snapshot();
        dialog.showModal();
        name.focus();
        name.select();
    };

    const close = () => {
        pristine = snapshot();
        dialog.close();
        opener?.focus?.();
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
        const add = event.target.closest('[data-tl-new]');
        const edit = event.target.closest('[data-tl-edit]');

        if ((!add && !edit) || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        open(edit ? { ...edit.dataset } : null, add ?? edit);
    });

    $('[data-tl-close]', dialog).addEventListener('click', requestClose);
    $('[data-tl-cancel]', dialog).addEventListener('click', requestClose);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        requestClose();
    });
    dialog.addEventListener('mousedown', (event) => event.target === dialog && requestClose());

    name.addEventListener('input', () => {
        say('name', '');
        syncCode();
        scheduleCheck();
    });
    code.addEventListener('input', () => {
        codeTouched = true;
        say('code', '');
        scheduleCheck();
    });
    code.addEventListener('blur', () => {
        if (!code.readOnly && code.value && slugify(code.value) !== code.value) {
            code.value = slugify(code.value);
            scheduleCheck();
        }
    });
    status.addEventListener('change', updateStatusHint);

    unlock.addEventListener('click', async () => {
        const sure = await ui.confirm({
            title: 'Ubah slug alat?',
            text: `Slug "${editing.code}" dipakai logika riset (langkah berikutnya, penyedia mock, dan presentasi hasil). Mengubahnya dapat memutus pengenalan alat ini. Lanjutkan hanya bila Anda yakin.`,
            ok: 'Buka kunci slug',
        });

        if (sure) {
            lock(false);
            confirmCode.disabled = false; // the server requires this explicit confirmation to accept a new slug
            code.focus();
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (busy) {
            return;
        }

        clearNotes();

        if (!name.value.trim()) {
            say('name', 'Nama alat wajib diisi.');
            name.focus();

            return;
        }

        if (!code.value.trim()) {
            say('code', 'Slug wajib diisi.');
            code.focus();

            return;
        }

        busy = true;
        save.setAttribute('aria-busy', 'true');
        save.disabled = true;

        const label = save.textContent;

        save.textContent = 'Menyimpan…';

        const reply = await postJson(form.action, new FormData(form));
        const created = !editing;

        busy = false;
        save.removeAttribute('aria-busy');
        save.disabled = false;
        save.textContent = label;

        if (reply.ok) {
            close();
            await go(here(), 'none', { quiet: true });
            ui.notify(reply.message, true, created ? reply.json?.manage_url ?? null : null);

            return;
        }

        if (reply.status === 422 && reply.json?.errors) {
            let first = null;

            Object.entries(reply.json.errors).forEach(([field, messages]) => {
                say(field, messages[0], field === 'code' ? reply.json.existing : null);
                first ??= $(`[name="${field}"]`, form);
            });
            first?.focus();

            return;
        }

        general.textContent = reply.message;
        general.hidden = false;
    });
}
