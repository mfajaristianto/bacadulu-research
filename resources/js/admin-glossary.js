/*
 * Glosarium (admin/glossary/index). Loaded on demand by admin.js when a [data-gl] page is open.
 *
 * Progressive enhancement: without JS the filters are a GET form, "Tambah"/"Edit" are links to the form page (aliases
 * are one comma-separated field there) and saving redirects. With JS nothing reloads the document: the regions marked
 * [data-gl-swap] are replaced from a fetched copy of the same URL, the address bar follows through the History API,
 * add/edit happen in a dialog with alias chips and post JSON, and deactivating asks first. Every rule (case-insensitive
 * unique terms and aliases within a scope, limits) is enforced by the server; the hints here show the same checks earlier.
 */

const root = document.querySelector('[data-gl]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const url = (template, id) => template.replace('__ID__', id);

const HEADERS_JSON = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
const HEADERS_HTML = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
const ALIAS_LIMIT = 30;
const ALIAS_LENGTH = 120;

export function initAdminGlossary() {
    if (!root) {
        return;
    }

    const ui = createUi();

    initList();
    initDetails(ui);
    initDialog(ui);
}

/* ------------------------------------------------------------------------------------------ feedback */

function createUi() {
    const toast = $('[data-gl-toast]');
    const dialog = $('[data-gl-confirm]');
    let timer = 0;
    const hide = () => {
        clearTimeout(timer);
        toast.hidden = true;
    };

    $('[data-gl-toast-close]').addEventListener('click', hide);

    return {
        notify(message, ok = true) {
            clearTimeout(timer);
            toast.className = `bd-gl__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-gl-toast-text]', toast).textContent = message;
            toast.hidden = false;

            if (ok) {
                timer = setTimeout(hide, 6000); // errors stay until dismissed
            }
        },

        confirm({ title, text, ok }) {
            $('[data-gl-confirm-title]').textContent = title;
            $('[data-gl-confirm-text]').textContent = text;
            $('[data-gl-confirm-ok]').textContent = ok;

            return new Promise((resolve) => {
                dialog.returnValue = '';
                dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
                $('[data-gl-confirm-ok]').onclick = () => dialog.close('ok');
                $('[data-gl-confirm-cancel]').onclick = () => dialog.close('cancel');
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
    const open = $$('[data-gl-toggle][aria-expanded="true"]').map((button) => button.closest('[data-gl-item]').dataset.id);

    $$('[data-gl-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-gl-swap="${region.dataset.glSwap}"]`);

        if (fresh) {
            region.replaceWith(document.importNode(fresh, true));
        }
    });

    open.forEach((id) => {
        const item = $(`[data-gl-item][data-id="${id}"]`);

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
        $('[data-gl-swap="list"]')?.setAttribute('aria-busy', 'true');
    }

    try {
        if (!(await swapFrom(target, mine.signal))) {
            location.assign(target);

            return;
        }

        if (mode === 'push' && target !== location.pathname + location.search) {
            history.pushState({ gl: 1 }, '', target);
        } else if (mode === 'replace') {
            history.replaceState({ gl: 1 }, '', target);
        }
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            const list = $('[data-gl-swap="list"]');

            if (list && !$('[data-gl-load-error]', list)) {
                const note = document.createElement('p');
                const retry = document.createElement('button');

                note.className = 'bd-gl__alert';
                note.setAttribute('role', 'alert');
                note.setAttribute('data-gl-load-error', '');
                note.append('Daftar tidak bisa dimuat. Periksa koneksi, lalu ');
                retry.type = 'button';
                retry.className = 'bd-gl__link';
                retry.setAttribute('data-gl-retry', '');
                retry.textContent = 'coba lagi';
                note.append(retry, '.');
                list.prepend(note);
            }
        }
    } finally {
        if (controller === mine) {
            $('[data-gl-swap="list"]')?.removeAttribute('aria-busy');
        }
    }
}

const here = () => location.pathname + location.search;

function initList() {
    const form = $('[data-gl-filters]');
    const search = $('[data-gl-search]');
    let timer = 0;

    history.replaceState({ gl: 1 }, '', location.href);

    const currentUrl = () => {
        const params = new URLSearchParams();

        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '' && !(key === 'sort' && value === 'alpha')) {
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
    $$('[data-gl-auto]', form).forEach((select) => select.addEventListener('change', () => {
        clearTimeout(timer);
        request('push');
    }));
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => request('replace'), 350);
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-gl-retry]')) {
            go(currentUrl(), 'replace'); // retry what the filters currently ask for, not the last URL that loaded

            return;
        }

        const reset = event.target.closest('[data-gl-reset]');
        const page = event.target.closest('a[data-gl-nav]');

        if (reset && event.button === 0 && !event.metaKey && !event.ctrlKey) {
            event.preventDefault();
            clearTimeout(timer);
            search.value = '';
            $$('select', form).forEach((select) => { select.selectedIndex = 0; });
            go(location.pathname, 'push');
        } else if (page && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
            event.preventDefault();

            const next = new URL(page.href, location.href);

            go(next.pathname + next.search, 'push').then(() => $('[data-gl-swap="list"]')?.scrollIntoView({ block: 'start' }));
        }
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.gl) {
            return;
        }

        clearTimeout(timer);

        const params = new URLSearchParams(location.search);

        $$('input[name], select[name]', form).forEach((field) => {
            field.value = params.get(field.name) ?? (field.name === 'sort' ? 'alpha' : '');
        });
        go(here(), 'none');
    });
}

/* ------------------------------------------------------------------------------- expandable detail */

function setOpen(item, open) {
    const inner = $('.bd-gl__panel > div', item);

    $('[data-gl-toggle]', item).setAttribute('aria-expanded', String(open));
    item.classList.toggle('is-open', open);
    inner.toggleAttribute('inert', !open); // collapsed content must not be reachable by keyboard
}

function initDetails(ui) {
    document.addEventListener('click', async (event) => {
        const toggle = event.target.closest('[data-gl-toggle]');

        if (toggle) {
            setOpen(toggle.closest('[data-gl-item]'), toggle.getAttribute('aria-expanded') !== 'true');

            return;
        }

        const off = event.target.closest('[data-gl-deactivate]');

        if (!off || off.getAttribute('aria-busy') === 'true') {
            return;
        }

        const sure = await ui.confirm({
            title: 'Nonaktifkan istilah?',
            text: `"${off.dataset.term}" tidak lagi dikirim ke AI sebagai konteks. Istilahnya tetap tersimpan dan bisa diaktifkan lagi lewat Edit.`,
            ok: 'Nonaktifkan',
        });

        if (!sure) {
            return;
        }

        off.setAttribute('aria-busy', 'true');

        const data = new FormData();

        data.set('_token', $('input[name="_token"]', $('[data-gl-form]')).value);
        data.set('_method', 'DELETE');

        const reply = await postJson(off.dataset.url, data);

        off.removeAttribute('aria-busy');

        if (!reply.ok) {
            ui.notify(reply.message, false);

            return;
        }

        await go(here(), 'none', { quiet: true });
        ui.notify(reply.message);
    });
}

/* ------------------------------------------------------------------------------- add / edit dialog */

function initDialog(ui) {
    const dialog = $('[data-gl-dialog]');
    const form = $('[data-gl-form]');
    const method = $('[data-gl-method]', form);
    const term = $('[data-gl-term]', form);
    const definition = $('[data-gl-definition]', form);
    const category = $('[data-gl-category]', form);
    const status = $('[data-gl-status]', form);
    const note = $('[data-gl-note]', form);
    const box = $('[data-gl-chipbox]', form);
    const chipList = $('[data-gl-chips]', form);
    const aliasInput = $('[data-gl-alias]', form);
    const save = $('[data-gl-save]', form);
    const general = $('[data-gl-error]', form);

    let aliases = [];
    let editing = null;
    let pristine = '';
    let busy = false;
    let opener = null;
    let checking = null;
    let timer = 0;

    const squash = (text) => text.replace(/\s+/g, ' ').trim();
    const snapshot = () => JSON.stringify([term.value, definition.value, aliases, aliasInput.value.trim(), category.value, status.value, note.value]);

    const say = (field, message, { soft = false, pointer = null } = {}) => {
        const slot = $(`[data-error-for="${field}"]`, form);

        slot.textContent = message || '';
        slot.classList.toggle('is-soft', soft);

        if (pointer && message) {
            const open = document.createElement('button');

            open.type = 'button';
            open.className = 'bd-gl__link';
            open.textContent = 'Buka istilah itu';
            open.addEventListener('click', () => openExisting(pointer));
            slot.append(' ', open);
        }

        const control = field === 'aliases' ? box : $(`[name="${field}"]`, form);

        if (control) {
            control.toggleAttribute('aria-invalid', Boolean(message) && !soft);

            if (message && !soft) {
                control.setAttribute('aria-invalid', 'true');
            }
        }
    };

    const clearNotes = () => {
        ['term', 'definition', 'aliases', 'category_id', 'status', 'context_note'].forEach((field) => say(field, ''));
        general.hidden = true;
    };

    const renderChips = () => {
        chipList.replaceChildren(...aliases.map((alias, index) => {
            const chip = document.createElement('li');
            const remove = document.createElement('button');

            chip.append(alias);
            remove.type = 'button';
            remove.className = 'bd-gl__chip-x';
            remove.setAttribute('aria-label', `Hapus alias ${alias}`);
            remove.textContent = '×';
            remove.addEventListener('click', () => {
                aliases.splice(index, 1);
                renderChips();
                aliasInput.focus();
                scheduleCheck();
            });
            chip.append(remove);

            return chip;
        }));
    };

    // Returns true when something was added; reports why when not.
    const addAlias = (raw) => {
        const alias = squash(raw);

        if (!alias) {
            return false;
        }

        if (alias.length > ALIAS_LENGTH) {
            say('aliases', `Setiap alias maksimal ${ALIAS_LENGTH} karakter.`);

            return false;
        }

        if (aliases.length >= ALIAS_LIMIT) {
            say('aliases', `Maksimal ${ALIAS_LIMIT} alias per istilah.`);

            return false;
        }

        const lower = alias.toLowerCase();

        if (lower === squash(term.value).toLowerCase()) {
            say('aliases', 'Alias sama dengan istilah utama; tidak perlu ditambahkan.');

            return false;
        }

        if (aliases.some((existing) => existing.toLowerCase() === lower)) {
            say('aliases', `Alias "${alias}" sudah ada.`);

            return false;
        }

        say('aliases', '');
        aliases.push(alias);
        renderChips();

        return true;
    };

    const commitInput = () => {
        const parts = aliasInput.value.split(/[,;\n]+/);
        let added = false;

        parts.forEach((part) => { added = addAlias(part) || added; });

        if (added || !squash(aliasInput.value)) {
            aliasInput.value = '';
        }

        return added;
    };

    // Same conflict checks the server runs on save, shown while typing. The server stays the authority.
    const scheduleCheck = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            checking?.abort();
            checking = new AbortController();

            if (!squash(term.value) && !aliases.length) {
                return;
            }

            const params = new URLSearchParams({ term: term.value, category: category.value });

            aliases.forEach((alias) => params.append('aliases[]', alias));

            if (editing) {
                params.set('ignore', editing.id);
            }

            try {
                const response = await fetch(`${root.dataset.checkUrl}?${params}`, { headers: HEADERS_JSON, credentials: 'same-origin', signal: checking.signal });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();

                if (data.term) {
                    say('term', `Istilah "${data.term.term}" sudah ada di cakupan yang sama.`, { pointer: data.term.id });
                } else if (data.elsewhere) {
                    say('term', `Istilah ini juga ada di ${data.elsewhere.category}. Boleh tetap dibuat bila maknanya berbeda.`, { soft: true });
                } else {
                    say('term', '');
                }

                if (data.aliases.length) {
                    say('aliases', `Alias "${data.aliases[0].alias}" sudah dipakai oleh "${data.aliases[0].term}".`, { pointer: data.aliases[0].id });
                } else if (!$('[data-error-for="aliases"]', form).textContent.startsWith('Alias "')) {
                    say('aliases', '');
                }
            } catch {
                // offline or aborted: the save itself still validates
            }
        }, 350);
    };

    const open = (data = null, trigger = null) => {
        opener = trigger;
        editing = data;
        busy = false;
        clearNotes();
        $('[data-gl-title]').textContent = data ? 'Edit istilah' : 'Tambah istilah';
        $('[data-gl-subtitle]').textContent = data ? 'Ubah definisi, alias, atau status. Perubahan berlaku pada permintaan riset berikutnya.' : 'Istilah, definisi, dan sinonimnya. Alias membantu AI mengenali istilah yang sama dengan penulisan berbeda.';
        save.textContent = data ? 'Simpan perubahan' : 'Simpan istilah';
        form.action = data ? url(root.dataset.updateUrl, data.id) : root.dataset.storeUrl;
        method.disabled = !data;
        term.value = data?.term ?? '';
        definition.value = data?.definition ?? '';
        aliases = [...(data?.aliases ?? [])];
        aliasInput.value = '';
        category.value = data?.category ?? '';
        status.value = data?.status ?? 'active';
        note.value = data?.note ?? '';
        renderChips();
        pristine = snapshot();
        dialog.showModal();
        term.focus();
        term.select();
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

    const fromRow = (link) => {
        let list = [];

        try {
            list = JSON.parse(link.dataset.aliases || '[]');
        } catch {
            list = [];
        }

        return { id: link.dataset.id, term: link.dataset.term, definition: link.dataset.definition, aliases: list, category: link.dataset.category || '', status: link.dataset.status, note: link.dataset.note || '' };
    };

    function openExisting(id) {
        const row = $(`[data-gl-edit][data-id="${id}"]`);

        if (!row) {
            location.assign(`${root.dataset.indexUrl}/${id}/edit`);

            return;
        }

        pristine = snapshot();
        dialog.close();
        open(fromRow(row), row);
    }

    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-gl-new]');
        const edit = event.target.closest('[data-gl-edit]');

        if ((!add && !edit) || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        open(edit ? fromRow(edit) : null, add ?? edit);
    });

    $('[data-gl-close]', dialog).addEventListener('click', requestClose);
    $('[data-gl-cancel]', dialog).addEventListener('click', requestClose);
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        requestClose();
    });
    dialog.addEventListener('mousedown', (event) => event.target === dialog && requestClose());

    // Alias chip input
    box.addEventListener('mousedown', (event) => {
        if (event.target === box) {
            event.preventDefault();
            aliasInput.focus();
        }
    });
    aliasInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ',' || event.key === ';') {
            event.preventDefault(); // Enter must never submit the form while typing an alias
            commitInput();
            scheduleCheck();
        } else if (event.key === 'Backspace' && aliasInput.value === '' && aliases.length) {
            aliases.pop();
            renderChips();
            scheduleCheck();
        }
    });
    aliasInput.addEventListener('paste', (event) => {
        const text = event.clipboardData?.getData('text') ?? '';

        if (/[,;\n]/.test(text)) {
            event.preventDefault();
            aliasInput.value += text;
            commitInput();
            scheduleCheck();
        }
    });
    aliasInput.addEventListener('blur', () => {
        if (commitInput()) {
            scheduleCheck();
        }
    });
    aliasInput.addEventListener('input', () => say('aliases', ''));

    term.addEventListener('input', () => {
        say('term', '');
        scheduleCheck();
    });
    definition.addEventListener('input', () => say('definition', ''));
    category.addEventListener('change', scheduleCheck);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (busy) {
            return;
        }

        commitInput();
        clearNotes();

        if (!squash(term.value)) {
            say('term', 'Istilah wajib diisi.');
            term.focus();

            return;
        }

        if (!definition.value.trim()) {
            say('definition', 'Definisi wajib diisi.');
            definition.focus();

            return;
        }

        busy = true;
        save.setAttribute('aria-busy', 'true');
        save.disabled = true;

        const label = save.textContent;
        const data = new FormData(form);

        aliases.forEach((alias) => data.append('aliases[]', alias));
        save.textContent = 'Menyimpan…';

        const reply = await postJson(form.action, data);

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
                const name = field.startsWith('aliases') ? 'aliases' : field;

                say(name, messages[0], { pointer: name === 'term' || name === 'aliases' ? reply.json.existing?.id ?? null : null });
                first ??= name === 'aliases' ? aliasInput : $(`[name="${name}"]`, form);
            });
            first?.focus();

            return;
        }

        general.textContent = reply.message;
        general.hidden = false;
    });
}
