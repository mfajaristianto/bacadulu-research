/*
 * Impor Sumber (admin/source-sync/index). Loaded on demand by admin.js when a [data-sync] page is open.
 *
 * Progressive enhancement: without JS every filter is a link, scan/import/ignore are normal form posts that
 * redirect, and the page works. With JS nothing reloads the document: the regions marked [data-sync-swap] are
 * replaced from a fetched copy of the same URL, the address bar follows through the History API, and the
 * actions (scan, import, ignore, bulk) post to the same routes asking for JSON. All server rules (CSRF,
 * authorization, duplicate checks, locks) still decide; this file only shows their result without a reload.
 */

const root = document.querySelector('[data-sync]');
const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];

export function initAdminSync() {
    if (!root) {
        return;
    }

    const ui = createUi();

    initList(ui);
    initDetails();
    initSelection(ui);
    initActions(ui);
    initScan(ui);
}

/* ---------------------------------------------------------------------------------------------- feedback */

function createUi() {
    const toast = $('[data-sync-toast]');
    const confirmDialog = $('[data-sync-confirm]');
    let timer = 0;

    const hideToast = () => {
        clearTimeout(timer);
        toast.hidden = true;
    };

    $('[data-sync-toast-close]').addEventListener('click', hideToast);

    return {
        notify(message, ok = true, link = null) {
            clearTimeout(timer);
            toast.className = `bd-sync__toast ${ok ? 'is-ok' : 'is-bad'}`;
            $('[data-sync-toast-text]', toast).textContent = message;

            const anchor = $('[data-sync-toast-link]', toast);

            anchor.hidden = !link;

            if (link) {
                anchor.href = link;
            }

            toast.hidden = false;

            if (ok) {
                timer = setTimeout(hideToast, 8000); // errors stay until dismissed
            }
        },

        confirm({ title, text, ok }) {
            $('[data-sync-confirm-title]').textContent = title;
            $('[data-sync-confirm-text]').textContent = text;
            $('[data-sync-confirm-ok]').textContent = ok;

            return new Promise((resolve) => {
                const done = (value) => {
                    confirmDialog.removeEventListener('close', onClose);
                    resolve(value);
                };
                const onClose = () => done(confirmDialog.returnValue === 'ok');

                confirmDialog.returnValue = '';
                confirmDialog.addEventListener('close', onClose, { once: true });
                $('[data-sync-confirm-ok]').onclick = () => confirmDialog.close('ok');
                $('[data-sync-confirm-cancel]').onclick = () => confirmDialog.close('cancel');
                confirmDialog.showModal();
            });
        },
    };
}

/* --------------------------------------------------------------------------- async list (swap + history) */

const swapRegions = () => $$('[data-sync-swap]');

// Replaces every [data-sync-swap] region from a fetched copy of `url`. Resolves 'navigate' when the current page
// lacks a region the new page has (first ever scan) or the session ended, so the caller does a real navigation.
async function swapFrom(url, signal) {
    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' }, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(url, location.href).pathname) {
        return 'navigate';
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
    const fresh = [...next.querySelectorAll('[data-sync-swap]')];

    if (fresh.some((region) => !$(`[data-sync-swap="${region.dataset.syncSwap}"]`))) {
        return 'navigate';
    }

    const open = $$('[data-sync-toggle][aria-expanded="true"]').map((button) => button.getAttribute('aria-controls'));
    const ink = $('.bd-sync__ink');
    const before = ink ? { x: ink.style.getPropertyValue('--ink-x'), w: ink.style.getPropertyValue('--ink-w') } : null;

    swapRegions().forEach((region) => {
        const replacement = next.querySelector(`[data-sync-swap="${region.dataset.syncSwap}"]`);

        if (replacement) {
            region.replaceWith(document.importNode(replacement, true));
        } else {
            region.remove(); // the new page has no such region (for example no run any more)
        }
    });

    // Slide the tab indicator from where it was to the new tab.
    const fresher = $('.bd-sync__ink');

    if (fresher && before?.w) {
        fresher.style.transition = 'none';
        fresher.style.setProperty('--ink-x', before.x);
        fresher.style.setProperty('--ink-w', before.w);
        fresher.getBoundingClientRect();
        fresher.style.transition = '';
    }

    open.forEach((id) => {
        const button = $(`[data-sync-toggle][aria-controls="${id}"]`);

        if (button) {
            setOpen(button, true);
        }
    });

    placeInk();
    document.dispatchEvent(new CustomEvent('sync:swapped'));

    return 'swapped';
}

// instant: first paint and resize jump straight to the tab; only a tab change slides.
function placeInk(instant = false) {
    const ink = $('.bd-sync__ink');
    const active = $('.bd-sync__tabs a[aria-current]');

    if (!ink) {
        return;
    }

    if (instant) {
        ink.style.transition = 'none';
    }

    ink.style.setProperty('--ink-x', `${active ? active.offsetLeft : 0}px`);
    ink.style.setProperty('--ink-w', `${active ? active.offsetWidth : 0}px`);

    if (instant) {
        ink.getBoundingClientRect();
        ink.style.transition = '';

        // On narrow screens the strip scrolls sideways: keep the current tab in view (horizontal only, no page scroll).
        const strip = active?.parentElement;

        if (strip && active.offsetLeft + active.offsetWidth > strip.clientWidth) {
            strip.scrollLeft = active.offsetLeft + active.offsetWidth - strip.clientWidth + 8;
        }
    }
}

let controller = null;

// mode: 'push' (new history entry), 'replace' (typing), 'none' (Back/Forward or refresh after an action)
async function go(url, mode = 'push', { quiet = false } = {}) {
    controller?.abort();
    controller = new AbortController();

    const mine = controller;
    const list = $('[data-sync-swap="list"]');

    if (!quiet) {
        list?.setAttribute('aria-busy', 'true');
    }

    try {
        const result = await swapFrom(url, mine.signal);

        if (result === 'navigate') {
            location.assign(url);

            return true;
        }

        if (mode === 'push' && url !== location.pathname + location.search) {
            history.pushState({ sync: 1 }, '', url);
        } else if (mode === 'replace') {
            history.replaceState({ sync: 1 }, '', url);
        }

        return true;
    } catch (error) {
        if (error.name !== 'AbortError' && !quiet) {
            const target = $('[data-sync-swap="list"]');

            if (target && !$('[data-sync-load-error]', target)) {
                const note = document.createElement('p');
                const retry = document.createElement('button');

                note.className = 'bd-sync__alert';
                note.setAttribute('role', 'alert');
                note.setAttribute('data-sync-load-error', '');
                note.append('Daftar tidak bisa dimuat. Periksa koneksi, lalu ');
                retry.type = 'button';
                retry.className = 'bd-sync__btn bd-sync__btn--small';
                retry.setAttribute('data-sync-retry', '');
                retry.textContent = 'coba lagi';
                note.append(retry, '.');
                target.prepend(note);
            }
        }

        return false;
    } finally {
        if (controller === mine) {
            $('[data-sync-swap="list"]')?.removeAttribute('aria-busy');
        }
    }
}

function initList() {
    const search = $('[data-sync-search]');
    let timer = 0;

    history.replaceState({ sync: 1 }, '', location.href);
    placeInk(true);
    window.addEventListener('resize', () => placeInk(true), { passive: true });

    const currentUrl = (changes = {}) => {
        const params = new URLSearchParams(location.search);

        Object.entries(changes).forEach(([key, value]) => (value ? params.set(key, value) : params.delete(key)));

        const query = params.toString();

        return location.pathname + (query ? `?${query}` : '');
    };

    search?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            const url = currentUrl({ q: search.value.trim(), page: null });

            if (url !== location.pathname + location.search) {
                go(url, 'replace');
            }
        }, 400);
    });

    $('[data-sync-filters]')?.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        go(currentUrl({ q: search.value.trim(), page: null }), 'replace');
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-sync-retry]')) {
            go(location.pathname + location.search, 'none');

            return;
        }

        const link = event.target.closest('a[data-sync-nav]');

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        const url = new URL(link.href, location.href);

        // Selecting another run starts from that run's own default tab; filters and search stay when only the tab changes.
        go(url.pathname + url.search, 'push');
    });

    window.addEventListener('popstate', (event) => {
        if (!event.state?.sync) {
            return;
        }

        clearTimeout(timer);

        if (search) {
            search.value = new URLSearchParams(location.search).get('q') ?? '';
        }

        go(location.pathname + location.search, 'none');
    });
}

/* ------------------------------------------------------------------------------------- expandable detail */

function setOpen(button, open) {
    const item = button.closest('[data-sync-item]');
    const inner = $('.bd-sync__detail > div', item);

    button.setAttribute('aria-expanded', String(open));
    item.classList.toggle('is-open', open);
    inner?.toggleAttribute('inert', !open); // collapsed content must not be reachable by keyboard
}

function initDetails() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-sync-toggle]');

        if (button) {
            setOpen(button, button.getAttribute('aria-expanded') !== 'true');
        }
    });
}

/* ------------------------------------------------------------------------------------ selection + bulk */

function initSelection(ui) {
    const bar = $('[data-sync-bulk]');

    if (!bar) {
        return; // no scan yet, so there is nothing to select
    }

    const count = $('[data-sync-bulk-count]');
    const picks = () => $$('[data-sync-pick]');
    const chosen = () => picks().filter((box) => box.checked);

    const refresh = () => {
        const all = $('[data-sync-all]');
        const total = picks().length;
        const selected = chosen().length;

        count.textContent = String(selected);
        bar.hidden = selected === 0;

        if (all) {
            all.checked = total > 0 && selected === total;
            all.indeterminate = selected > 0 && selected < total;
        }
    };

    const clear = () => {
        picks().forEach((box) => { box.checked = false; });
        refresh();
    };

    document.addEventListener('change', (event) => {
        if (event.target.matches('[data-sync-all]')) {
            picks().forEach((box) => { box.checked = event.target.checked; });
        }

        if (event.target.matches('[data-sync-all], [data-sync-pick]')) {
            refresh();
        }
    });
    document.addEventListener('sync:swapped', clear);
    $('[data-sync-bulk-clear]').addEventListener('click', clear);

    const category = $('[data-sync-bulk-category]');

    // Bulk actions run one item at a time through the same endpoints as a single click, so every server rule applies per item.
    const run = async (kind) => {
        const items = chosen().map((box) => box.closest('[data-sync-item]'));

        if (!items.length) {
            return;
        }

        if (kind === 'import' && !category.value) {
            category.setAttribute('aria-invalid', 'true');
            category.focus();
            ui.notify('Pilih kategori untuk item yang akan diimpor.', false);

            return;
        }

        category.removeAttribute('aria-invalid');

        const verb = kind === 'import' ? 'Impor' : 'Abaikan';
        const sure = await ui.confirm({
            title: `${verb} ${items.length} sumber?`,
            text: kind === 'import'
                ? 'Sumber diimpor ke Pustaka dengan status Siap terbit dan tidak langsung terbit. Item yang identik dengan sumber di Pustaka akan dilewati.'
                : 'Item diabaikan dan Pustaka tidak berubah. Scan berikutnya dapat memunculkannya lagi.',
            ok: verb,
        });

        if (!sure) {
            return;
        }

        const buttons = $$('[data-sync-bulk] button, [data-sync-bulk] select');
        const result = { ok: 0, skipped: 0 };
        const problems = [];

        buttons.forEach((control) => { control.disabled = true; });

        for (const [index, item] of items.entries()) {
            count.textContent = `${index + 1} / ${items.length}`;

            const form = $(`form[data-sync-action="${kind}"]`, item);

            if (!form) {
                result.skipped += 1;
                continue;
            }

            const data = new FormData(form);

            if (kind === 'import') {
                data.set('category_id', category.value);
            }

            const reply = await post(form.action, data);

            if (reply.ok) {
                result.ok += 1;
            } else {
                result.skipped += 1;
                problems.push(`${item.dataset.title}: ${reply.message}`);
            }
        }

        buttons.forEach((control) => { control.disabled = false; });
        await go(location.pathname + location.search, 'none', { quiet: true });

        const done = `${result.ok} sumber ${kind === 'import' ? 'diimpor' : 'diabaikan'}`;

        ui.notify(result.skipped ? `${done}, ${result.skipped} dilewati. ${problems[0] ?? ''}` : `${done}.`, result.skipped === 0);
    };

    $('[data-sync-bulk-import]').addEventListener('click', () => run('import'));
    $('[data-sync-bulk-ignore]').addEventListener('click', () => run('ignore'));
    category.addEventListener('change', () => category.removeAttribute('aria-invalid'));
}

/* ----------------------------------------------------------------------------------------- HTTP helper */

// POST that always resolves to { ok, status, message, json } so callers show errors locally.
async function post(url, data, signal) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: data,
            signal,
        });
        let json = null;

        try {
            json = await response.json();
        } catch {
            // not JSON: an expired session answers with the login page
        }

        if (response.status === 419) {
            return { ok: false, status: 419, message: 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.', json };
        }

        if (response.status === 422 && json?.errors) {
            return { ok: false, status: 422, message: Object.values(json.errors)[0][0], json };
        }

        return { ok: response.ok && json?.ok !== false, status: response.status, message: json?.message || (response.ok ? '' : 'Permintaan gagal. Coba lagi.'), json };
    } catch (error) {
        return {
            ok: false,
            status: 0,
            message: error.name === 'AbortError' ? 'Permintaan dibatalkan.' : 'Koneksi terputus. Periksa jaringan lalu coba lagi.',
            json: null,
        };
    }
}

/* ------------------------------------------------------------------------------ row import / ignore */

function initActions(ui) {
    document.addEventListener('submit', async (event) => {
        const form = event.target.closest?.('form[data-sync-action]');

        if (!form) {
            return;
        }

        event.preventDefault();

        const kind = form.dataset.syncAction;
        const item = form.closest('[data-sync-item]');
        const error = $('[data-sync-error]', item);
        const select = $('select[name="category_id"]', form);

        if (item.classList.contains('is-working')) {
            return; // double click or Enter twice: one request only
        }

        error.hidden = true;

        if (kind === 'import' && !select.value) {
            select.setAttribute('aria-invalid', 'true');
            error.textContent = 'Pilih kategori sebelum mengimpor.';
            error.hidden = false;
            select.focus();

            return;
        }

        select?.removeAttribute('aria-invalid');
        item.classList.add('is-working');

        const data = new FormData(form); // read before the controls are disabled (disabled fields are not submitted)
        const buttons = $$('button, select', item);
        const submit = $('button[type="submit"]', form);
        const label = submit.textContent;

        buttons.forEach((control) => { control.disabled = true; });
        submit.textContent = kind === 'import' ? 'Mengimpor…' : 'Mengabaikan…';
        submit.setAttribute('aria-busy', 'true');

        const reply = await post(form.action, data);

        if (reply.ok) {
            await go(location.pathname + location.search, 'none', { quiet: true });
            ui.notify(reply.message, true, reply.json?.document_url ?? null);

            return;
        }

        buttons.forEach((control) => { control.disabled = false; });
        submit.textContent = label;
        submit.removeAttribute('aria-busy');
        item.classList.remove('is-working');
        error.textContent = reply.message;
        error.hidden = false;

        // The server answered that the item is no longer actionable (already imported, duplicate, ...): show its real state.
        if (reply.status === 409) {
            await go(location.pathname + location.search, 'none', { quiet: true });
        }
    });
}

/* ----------------------------------------------------------------------------------------------- scan */

function initScan(ui) {
    const form = $('[data-sync-scan]');
    const button = $('[data-sync-scan-button]');
    const label = $('[data-sync-scan-label]');
    let running = false;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (running) {
            return;
        }

        running = true;
        button.classList.add('is-busy');
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-disabled', 'true');
        label.textContent = 'Memindai…';

        // The scan is one synchronous request: no percentage is shown because none exists.
        const reply = await post(form.action, new FormData(form));

        running = false;
        button.classList.remove('is-busy');
        button.removeAttribute('aria-busy');
        button.removeAttribute('aria-disabled');
        label.textContent = 'Scan Sumber';

        if (!reply.ok) {
            ui.notify(reply.message, false);

            return;
        }

        const target = `${root.dataset.indexUrl}?run=${reply.json?.run ?? ''}`;

        await go(target, 'push');
        ui.notify(reply.message, true);
    });
}
