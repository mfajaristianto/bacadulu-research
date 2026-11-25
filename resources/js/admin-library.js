/*
 * Pustaka admin (admin/library/index + form). Loaded on demand by admin.js when a [data-lib] page is open.
 * Everything is progressive enhancement: without JS the filters are a plain GET form, version history is a hidden
 * table row, and the upload form posts normally. Server-side checks (duplicates, size, type) always apply;
 * this file only makes them visible earlier and adds real upload progress.
 */

const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

const formatSize = (bytes) => (bytes >= 1048576
    ? `${(bytes / 1048576).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB`
    : `${Math.max(1, Math.round(bytes / 1024)).toLocaleString('id-ID')} KB`);

export function initAdminLibrary() {
    const list = initList();

    initVersionToggles();
    initProcessing(list);
    initViewer();
    initConfirm();
    initUploader();
}

/* ------------------------------------------------------------------------------------ in-place updates */

/*
 * The Pustaka list never reloads the document. Search, filters, sorting and pagination fetch the same URL
 * and swap the regions marked [data-lib-swap]; the address bar follows through the History API. The same
 * swap refreshes a page after background processing finishes. Without JS everything is a normal GET/POST.
 */

const NAV_HEADERS = { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' };

// Fetches a page and swaps its [data-lib-swap] regions into the current document. Returns false when the
// session is gone (the caller then falls back to a real navigation, which shows the login page).
async function swapFrom(url, signal) {
    const response = await fetch(url, { headers: NAV_HEADERS, credentials: 'same-origin', signal });

    if (new URL(response.url).pathname !== new URL(url, location.href).pathname) {
        return false;
    }

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const next = new DOMParser().parseFromString(await response.text(), 'text/html');
    const open = [...document.querySelectorAll('[data-lib-toggle][aria-expanded="true"]')].map((button) => button.getAttribute('aria-controls'));

    document.querySelectorAll('[data-lib-swap]').forEach((region) => {
        const fresh = next.querySelector(`[data-lib-swap="${region.dataset.libSwap}"]`);

        if (fresh) {
            region.replaceWith(document.importNode(fresh, true));
        }
    });

    // Expanded version panels stay expanded across a refresh.
    open.forEach((id) => {
        const button = document.querySelector(`[data-lib-toggle][aria-controls="${id}"]`);

        if (button) {
            setOpen(button, true);
        }
    });

    return true;
}

function initList() {
    const form = document.querySelector('[data-lib-filters]');

    if (!form) {
        return null;
    }

    const search = form.querySelector('[data-lib-search]');
    const notice = document.querySelector('[data-lib-notice]');
    let controller = null;
    let timer = 0;
    let shown = location.pathname + location.search;

    const currentUrl = () => {
        const params = new URLSearchParams();

        new FormData(form).forEach((value, key) => {
            if (typeof value === 'string' && value.trim() !== '' && !(key === 'sort' && value === 'newest')) {
                params.set(key, value.trim());
            }
        });

        const query = params.toString();

        return location.pathname + (query ? `?${query}` : '');
    };

    const results = () => document.querySelector('[data-lib-swap="results"]');

    // mode: 'push' (new history entry), 'replace' (typing), 'none' (Back/Forward or background refresh)
    const load = async (url, mode = 'push', { quiet = false } = {}) => {
        controller?.abort();
        controller = new AbortController();

        const mine = controller;

        notice.hidden = true;

        if (!quiet) {
            results()?.setAttribute('aria-busy', 'true');
        }

        try {
            if (!(await swapFrom(url, mine.signal))) {
                location.assign(url);

                return;
            }

            shown = url;

            if (mode === 'push' && url !== location.pathname + location.search) {
                history.pushState({ lib: 1 }, '', url);
            } else if (mode === 'replace') {
                history.replaceState({ lib: 1 }, '', url);
            }

            document.dispatchEvent(new CustomEvent('lib:swapped'));
        } catch (error) {
            if (error.name !== 'AbortError' && !quiet) {
                notice.querySelector('[data-lib-notice-text]').textContent = 'Daftar tidak bisa dimuat. Periksa koneksi, lalu coba lagi.';
                notice.hidden = false;
            }
        } finally {
            if (controller === mine) {
                results()?.removeAttribute('aria-busy');
            }
        }
    };

    const request = (mode) => {
        const url = currentUrl();

        // Nothing changed (for example a trailing space): no request at all.
        if (url === shown && notice.hidden) {
            return;
        }

        load(url, mode);
    };

    history.replaceState({ lib: 1 }, '', location.href);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        clearTimeout(timer);
        request('push');
    });
    form.querySelectorAll('[data-lib-auto]').forEach((select) => select.addEventListener('change', () => {
        clearTimeout(timer);
        request('push');
    }));
    search?.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => request('replace'), 400);
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('.bd-lib__pager a[href], [data-lib-reset]');

        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        event.preventDefault();

        const url = new URL(link.href, location.href);

        if (link.matches('[data-lib-reset]')) {
            clearTimeout(timer);
            syncForm(form, new URLSearchParams());
        }

        load(url.pathname + url.search, 'push').then(() => {
            if (link.closest('.bd-lib__pager')) {
                results()?.scrollIntoView({ block: 'start', behavior: 'auto' });
            }
        });
    });

    document.querySelector('[data-lib-retry]')?.addEventListener('click', () => load(currentUrl(), 'none'));

    window.addEventListener('popstate', (event) => {
        if (!event.state?.lib) {
            return;
        }

        clearTimeout(timer);
        syncForm(form, new URLSearchParams(location.search));
        load(location.pathname + location.search, 'none');
    });

    return { refresh: () => load(shown, 'none', { quiet: true }) };
}

function syncForm(form, params) {
    [...form.elements].forEach((field) => {
        if (field.name && (field.tagName === 'SELECT' || field.type === 'search' || field.type === 'text')) {
            field.value = params.get(field.name) ?? (field.name === 'sort' ? 'newest' : '');
        }
    });
}

/* ------------------------------------------------------------------------------- version history rows */

function setOpen(button, open) {
    const panel = document.getElementById(button.getAttribute('aria-controls'));
    const inner = panel?.querySelector('.bd-lib__collapse > div');

    if (!panel || !inner) {
        return;
    }

    button.setAttribute('aria-expanded', String(open));
    panel.classList.toggle('is-open', open);
    panel.previousElementSibling?.classList.toggle('is-open', open);
    inner.toggleAttribute('inert', !open); // closed content must not be reachable by keyboard
}

function initVersionToggles() {
    // Delegated: rows are replaced when the list refreshes.
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-lib-toggle]');

        if (button) {
            setOpen(button, button.getAttribute('aria-expanded') !== 'true');
        }
    });
}

/* ------------------------------------------------------------------------------- background processing */

/*
 * Sources being processed by the queue worker are watched through the JSON status endpoint (a plain XHR, not a
 * navigation). Polling pauses while the tab is hidden, backs off when idle or failing, and stops by itself.
 */
function initProcessing(list) {
    const stages = { queued: 'menunggu antrean', extracting: 'membaca teks PDF', chunking: 'membentuk potongan teks' };
    let delay = 3000;
    let fails = 0;
    let timer = 0;
    let running = false;

    const schedule = () => {
        clearTimeout(timer);
        timer = 0;

        if (document.querySelector('[data-lib-watch]') && !document.hidden && fails < 5) {
            timer = setTimeout(tick, delay);
        }
    };

    const refresh = async () => {
        if (list) {
            await list.refresh();

            return;
        }

        await swapFrom(location.pathname + location.search).catch(() => {});
        document.dispatchEvent(new CustomEvent('lib:swapped'));
    };

    async function tick() {
        timer = 0;

        if (running) {
            return;
        }

        running = true;

        try {
            const watched = [...document.querySelectorAll('[data-lib-watch]')];
            const results = await Promise.all(watched.map(async (element) => {
                const response = await fetch(element.dataset.libWatch, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return [element, await response.json()];
            }));

            fails = 0;
            delay = Math.min(20000, Math.round(delay * 1.25));

            let finished = false;

            results.forEach(([element, data]) => {
                if (data.terminal) {
                    finished = true;

                    return;
                }

                const label = `${stages[data.stage] ?? 'memproses'} · ${data.progress}%`;
                const text = document.querySelector('[data-lib-stage]');

                element.title = label;

                if (text) {
                    text.textContent = `PDF sedang ${label}. Status diperbarui otomatis tanpa memuat ulang halaman.`;
                }
            });

            if (finished) {
                delay = 3000;
                await refresh();
            }
        } catch {
            fails += 1;
            delay = Math.min(30000, delay * 2);
        } finally {
            running = false;
            schedule();
        }
    }

    document.addEventListener('lib:swapped', schedule);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            fails = 0;
            delay = 3000;
            schedule();
        }
    });

    schedule();
}


/* ------------------------------------------------------------------------------------------- PDF viewer */

function initViewer() {
    const dialog = document.querySelector('[data-lib-viewer]');

    if (!dialog) {
        return;
    }

    const frame = dialog.querySelector('[data-viewer-frame]');
    const close = () => dialog.close();

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-lib-view]');

        if (!button) {
            return;
        }

        dialog.querySelector('[data-viewer-title]').textContent = button.dataset.title;
        dialog.querySelector('[data-viewer-version]').textContent = button.dataset.version;
        dialog.querySelector('[data-viewer-download]').href = button.dataset.download;
        dialog.querySelector('[data-viewer-open]').href = button.dataset.url;

        // The PDF is only requested now, from the authenticated admin route; the browser viewer streams pages itself.
        frame.src = `${button.dataset.url}#view=FitH`;
        dialog.showModal();
    });

    dialog.querySelector('[data-viewer-close]').addEventListener('click', close);
    dialog.addEventListener('click', (event) => event.target === dialog && close());
    dialog.addEventListener('close', () => frame.removeAttribute('src')); // stop loading and release the document
}

/* ---------------------------------------------------------------------------------- confirm dialog */

function initConfirm() {
    const dialog = document.querySelector('[data-lib-confirm-dialog]');

    if (!dialog) {
        return;
    }

    let pending = null;

    document.addEventListener('submit', (event) => {
        const form = event.target.closest?.('form[data-lib-confirm]');

        if (!form || form.dataset.confirmed) {
            return;
        }

        event.preventDefault();
        pending = form;
        dialog.querySelector('[data-confirm-title]').textContent = form.dataset.confirmTitle || 'Konfirmasi';
        dialog.querySelector('[data-confirm-text]').textContent = form.dataset.confirmText || '';
        dialog.querySelector('button[data-confirm-ok]').textContent = form.dataset.confirmOk || 'Lanjutkan';
        dialog.showModal();
    });

    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('button[data-confirm-ok]').addEventListener('click', () => {
        const form = pending;

        dialog.close();

        if (form) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
    dialog.addEventListener('click', (event) => event.target === dialog && dialog.close());
}

/* ------------------------------------------------------------------------------------------- uploader */

function initUploader() {
    const form = document.querySelector('[data-lib-form]');

    if (!form) {
        return;
    }

    const input = form.querySelector('[data-lib-file]');
    const area = form.querySelector('[data-lib-area]');
    const drop = form.querySelector('[data-lib-drop]');
    const card = form.querySelector('[data-lib-card]');
    const nameEl = form.querySelector('[data-lib-name]');
    const metaEl = form.querySelector('[data-lib-meta]');
    const progress = form.querySelector('[data-lib-progress]');
    const bar = form.querySelector('[data-lib-bar]');
    const progressText = form.querySelector('[data-lib-progress-text]');
    const title = form.querySelector('[data-lib-title]');
    const doi = form.querySelector('[data-lib-doi]');
    const decision = form.querySelector('[data-lib-decision]');
    const target = form.querySelector('[data-lib-target]');
    const dialog = document.querySelector('[data-lib-duplicate]');
    const maxBytes = Number(form.dataset.maxBytes);
    const maxMb = form.dataset.maxMb;

    let current = null;
    let request = null;
    let lastSubmitter = null;
    let check = 0;

    const setMeta = (text, bad = false) => {
        metaEl.textContent = text;
        metaEl.classList.toggle('is-bad', bad);
    };

    const setError = (field, message) => {
        const slot = form.querySelector(`[data-error-for="${field}"]`);
        const control = form.querySelector(`[name="${field}"]`);

        if (slot) {
            slot.textContent = message || '';
        }

        control?.toggleAttribute('aria-invalid', Boolean(message));

        if (control && message) {
            control.setAttribute('aria-invalid', 'true');
        }
    };

    const clearErrors = () => ['title', 'category_id', 'doi', 'source_file'].forEach((field) => setError(field, ''));

    const clearFile = () => {
        check += 1;
        current = null;
        input.value = '';
        card.hidden = true;
        drop.hidden = false;
        decision.value = '';
        target.value = '';
        setError('source_file', '');
    };

    const sha256 = async (file) => {
        // Hashing needs the whole file in memory, so very large files rely on the server-side check instead.
        if (!crypto?.subtle || file.size > 64 * 1048576) {
            return null;
        }

        const digest = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());

        return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    };

    const preflight = async (file) => {
        const mine = ++check;

        try {
            const hash = await sha256(file);

            if (!hash && !doi.value.trim()) {
                setMeta(`${formatSize(file.size)} · duplikat diperiksa saat menyimpan`);

                return;
            }

            const response = await fetch(form.dataset.checkUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ sha256: hash, doi: doi.value.trim() || null, document_id: form.dataset.documentId || null }),
            });

            if (mine !== check) {
                return; // another file was chosen meanwhile
            }

            const data = response.ok ? await response.json() : null;

            if (data?.exact) {
                setMeta('File identik sudah ada di Pustaka', true);
                showDuplicate({ code: 'exact_duplicate', message: data.message, match: data.exact });
            } else if (data?.same_source) {
                setMeta(`${formatSize(file.size)} · DOI sama dengan "${data.same_source.title}"; Anda akan diminta memilih saat menyimpan`);
            } else {
                setMeta(`${formatSize(file.size)} · siap diunggah`);
            }
        } catch {
            if (mine === check) {
                setMeta(`${formatSize(file.size)} · duplikat diperiksa saat menyimpan`);
            }
        }
    };

    const choose = (file) => {
        setError('source_file', '');

        if (!file) {
            return;
        }

        if (!(file.type === 'application/pdf' || /\.pdf$/i.test(file.name))) {
            clearFile();
            setError('source_file', 'File harus berformat PDF.');

            return;
        }

        if (maxBytes && file.size > maxBytes) {
            clearFile();
            setError('source_file', `Ukuran PDF maksimal ${maxMb} MB. File ini ${formatSize(file.size)}.`);

            return;
        }

        current = file;
        nameEl.textContent = file.name;
        setMeta(`${formatSize(file.size)} · memeriksa duplikat…`);
        card.hidden = false;
        drop.hidden = true;

        if (title && !title.value.trim()) {
            title.value = file.name.replace(/\.pdf$/i, '').replace(/[_-]+/g, ' ').replace(/\s+/g, ' ').trim();
        }

        preflight(file);
    };

    input.addEventListener('change', () => choose(input.files?.[0]));
    form.querySelector('[data-lib-clear]').addEventListener('click', () => {
        clearFile();
        input.focus();
    });

    ['dragenter', 'dragover'].forEach((type) => area.addEventListener(type, (event) => {
        event.preventDefault();
        area.classList.add('is-drag');
    }));
    ['dragleave', 'drop'].forEach((type) => area.addEventListener(type, () => area.classList.remove('is-drag')));
    area.addEventListener('drop', (event) => {
        event.preventDefault();

        const file = event.dataTransfer?.files?.[0];

        if (file) {
            try {
                input.files = event.dataTransfer.files;
            } catch {
                // some browsers refuse assignment; the chosen File is still used below
            }

            choose(file);
        }
    });
    // A file dropped beside the area must not make the browser navigate away to the PDF.
    window.addEventListener('dragover', (event) => event.preventDefault());
    window.addEventListener('drop', (event) => event.preventDefault());

    /* ---- duplicate dialog ---- */

    const showDuplicate = ({ code, message, match }) => {
        if (!dialog) {
            setError('source_file', message);

            return;
        }

        const exact = code === 'exact_duplicate';

        dialog.querySelector('[data-dup-title]').textContent = exact ? 'File ini sudah ada di Pustaka' : 'Sumber ini mungkin sudah ada';
        dialog.querySelector('[data-dup-text]').textContent = message;
        dialog.querySelector('[data-dup-match]').textContent = match.title;
        dialog.querySelector('[data-dup-detail]').textContent = exact ? `Dokumen yang sama persis · ${match.version}` : 'DOI sama, tetapi isi file berbeda';
        dialog.querySelector('[data-dup-view]').href = match.url;

        const version = dialog.querySelector('[data-dup-version]');

        version.hidden = exact;
        version.onclick = () => {
            decision.value = 'new_version';
            target.value = match.id;
            dialog.close();
            send(lastSubmitter);
        };
        dialog.querySelector('[data-dup-cancel]').onclick = () => {
            dialog.close();
            clearFile();
        };

        if (!dialog.open) {
            dialog.showModal();
        }
    };

    /* ---- submit with real progress ---- */

    const busy = (on) => {
        form.setAttribute('aria-busy', String(on));
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = on;
        });
        progress.hidden = !on;
    };

    const setProgress = (ratio) => {
        bar.style.setProperty('--p', String(ratio));
        bar.setAttribute('aria-valuenow', String(Math.round(ratio * 100)));
    };

    const send = (submitter) => {
        lastSubmitter = submitter;
        clearErrors();
        busy(true);
        setProgress(0);
        progressText.textContent = 'Mengunggah…';

        let data;

        try {
            data = new FormData(form, submitter);
        } catch {
            data = new FormData(form);

            if (submitter?.name) {
                data.set(submitter.name, submitter.value);
            }
        }

        request = new XMLHttpRequest();
        // getAttribute: tombol name="action" menimpa properti form.action (hasilnya elemen tombol, bukan URL).
        request.open('POST', form.getAttribute('action'));
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        request.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) {
                return;
            }

            const ratio = event.loaded / event.total;

            setProgress(ratio);
            progressText.textContent = ratio >= 1
                ? 'Diterima. Memeriksa dan menyimpan…'
                : `Mengunggah… ${Math.round(ratio * 100)}% · ${formatSize(event.loaded)} dari ${formatSize(event.total)}`;
        });

        request.addEventListener('load', () => {
            busy(false);

            let json = null;

            try {
                json = JSON.parse(request.responseText);
            } catch {
                // not JSON: most likely an expired session answered with the login page
            }

            if (request.status === 200 && json?.redirect) {
                progressText.textContent = 'Selesai. Mengalihkan…';
                progress.hidden = false;
                window.location.assign(json.redirect);

                return;
            }

            if (request.status === 409 && json?.match) {
                showDuplicate(json);

                return;
            }

            if (request.status === 422 && json?.errors) {
                let first = null;

                Object.entries(json.errors).forEach(([field, messages]) => {
                    setError(field, messages[0]);
                    first ??= form.querySelector(`[name="${field}"]`);
                });

                // The file never reached storage; keep the chosen file so the admin can correct the form and retry.
                first?.focus();

                return;
            }

            setError('source_file', json?.message || 'Unggah gagal. Muat ulang halaman, lalu coba lagi.');
        });

        request.addEventListener('error', () => {
            busy(false);
            setError('source_file', 'Koneksi terputus saat mengunggah. Periksa jaringan lalu coba lagi.');
        });

        request.addEventListener('abort', () => {
            busy(false);
            setError('source_file', 'Unggah dibatalkan. File belum disimpan.');
        });

        request.send(data);
    };

    form.querySelector('[data-lib-cancel]').addEventListener('click', () => request?.abort());

    form.addEventListener('submit', (event) => {
        if (!current || !input.files?.length) {
            return; // metadata-only or lifecycle action: normal form post
        }

        event.preventDefault();
        send(event.submitter);
    });

    if (reduced()) {
        bar.style.setProperty('transition', 'none');
    }
}
