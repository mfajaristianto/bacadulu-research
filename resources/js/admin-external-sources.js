/*
 * Sumber Akademik Eksternal (admin/external-sources/index). Dimuat sesuai kebutuhan oleh admin.js bila ada [data-ext].
 *
 * Progressive enhancement: tanpa JS semua tautan/form tetap bekerja (kecuali dialog). Dengan JS: dialog pengaturan dan
 * sinkronisasi berbasis mode, preview, tes koneksi, pencarian subjek DOAJ, rencana "semua kategori", cek DOI, dan progres
 * kampanye yang dipoll dari worker nyata (interval adaptif). Animasi hanya bila pengguna tidak meminta reduced motion.
 * Semua aturan (CSRF, otorisasi, batas, anggaran, validasi) tetap diputuskan server; file ini hanya menampilkan hasilnya.
 */

const root = document.querySelector('[data-ext]');
const $ = (selector, scope = root) => scope.querySelector(selector);
const $$ = (selector, scope = root) => [...scope.querySelectorAll(selector)];
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const token = () => $('input[name="_token"]')?.value ?? '';
const nf = new Intl.NumberFormat('id-ID');

export function initAdminExternalSources() {
    if (!root) {
        return;
    }

    // Konfirmasi tindakan berisiko. Atribut inline (onsubmit) diblokir CSP script-src 'self', jadi memakai data-ext-confirm.
    root.addEventListener('submit', (event) => {
        const message = event.target.closest?.('form[data-ext-confirm]')?.dataset.extConfirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    initDialogs();
    initToast();
    initTest();
    initRecords();
    initMotion();
    initPolling();
}

/* ------------------------------------------------------------------------------------- toast */

let toastTimer;

function toast(message, bad = false) {
    const box = $('[data-ext-toast]');
    $('[data-ext-toast-text]', box).textContent = message;
    box.classList.toggle('is-bad', bad);
    box.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { box.hidden = true; }, 6000);
}

function initToast() {
    $('[data-ext-toast-close]')?.addEventListener('click', () => { $('[data-ext-toast]').hidden = true; });
}

/* ----------------------------------------------------------------------------------- dialogs */

const dialog = (name) => $(`[data-ext-dialog="${name}"]`);
const urlFor = (provider, action) => `${root.dataset.base.replace(/\/$/, '')}/${provider}/${action}`;
const cfgOf = (el) => JSON.parse(el.closest('[data-ext-cfg]').dataset.extCfg);
const getJson = async (url) => (await fetch(url, { headers: { Accept: 'application/json' } })).json();

function initDialogs() {
    $$('[data-ext-dialog]').forEach((d) => {
        d.addEventListener('click', (event) => { if (event.target === d) d.close(); });
        $$('[data-ext-close]', d).forEach((b) => b.addEventListener('click', () => d.close()));
    });

    $$('[data-ext-open]').forEach((button) => button.addEventListener('click', () => {
        const kind = button.dataset.extOpen;

        if (kind === 'settings') openSettings(cfgOf(button));
        else if (kind === 'sync') openSync(cfgOf(button), button.dataset.mode);
        else if (kind === 'sync-all') openAll();
    }));

    initSyncForm();
}

function openSettings(cfg) {
    const d = dialog('settings');
    const form = $('[data-ext-settings-form]', d);

    form.action = urlFor(cfg.provider, 'settings');
    $$('[data-ext-name]', d).forEach((n) => { n.textContent = cfg.name; });
    $('[data-ext-enabled]', d).checked = !!cfg.enabled;
    $('[data-ext-contact]', d).value = cfg.contact || '';
    $('[data-ext-key]', d).value = '';
    $('[data-ext-key-block]', d).hidden = cfg.key === 'none';
    $('[data-ext-clear-row]', d).hidden = cfg.keySource !== 'admin';
    $('[data-ext-clear-row] input', d).checked = false;
    $('[data-ext-key-note]', d).textContent = [cfg.keyNote, cfg.keySet ? `Key terpasang (${cfg.keySource === 'env' ? 'dari env server' : 'tersimpan terenkripsi'}); isi hanya untuk menggantinya.` : 'Belum ada key.'].filter(Boolean).join(' ');
    d.showModal();
}

/* ---------------------------------------------------------------- sinkronisasi berbasis mode */

const MODE_TITLES = { quick: 'Quick', category: 'Per kategori', corpus: 'Korpus penyedia', incremental: 'Incremental' };
const TYPE_LABELS = { article: 'Artikel jurnal', book: 'Buku', chapter: 'Bab buku', proceedings: 'Prosiding', dataset: 'Dataset', thesis: 'Tesis/disertasi' };
const show = (el, on) => {
    el.hidden = !on;
    $$('input,select,textarea', el).forEach((i) => { i.disabled = !on; }); // input tersembunyi tidak ikut terkirim
};
const modeOf = (form) => $('input[name="mode"]:checked', form)?.value ?? 'category';
const cfgForm = (form) => JSON.parse(form.dataset.cfg);

function openSync(cfg, openMode) {
    const d = dialog('sync');
    const form = $('[data-ext-sync-form]', d);

    form.dataset.provider = cfg.provider;
    form.dataset.cfg = JSON.stringify(cfg);
    form.action = urlFor(cfg.provider, 'sync');
    $$('[data-ext-name]', d).forEach((n) => { n.textContent = cfg.name; });
    $('[data-ext-mode-title]', d).textContent = openMode === 'preview' ? 'Preview data' : 'Sinkronisasi';

    const type = $('[data-ext-type]', d);
    type.replaceChildren(new Option('Semua yang didukung', ''), ...cfg.types.map((t) => new Option(TYPE_LABELS[t] || t, t)));

    $$('[data-ext-mode]', d).forEach((label) => {
        const mode = label.dataset.extMode;
        const supported = cfg.modes.includes(mode);
        const input = $('input', label);

        input.disabled = !supported;
        input.checked = false;
        label.classList.toggle('is-off', !supported);
        label.title = supported ? '' : (cfg.modeBlockers[mode] || 'Tidak didukung penyedia ini.');
    });
    // Mode awal: "Per kategori" bila didukung, selain itu mode pertama yang didukung.
    ($('[data-ext-mode="category"] input:not(:disabled)', d) || $('[data-ext-mode] input:not(:disabled)', d)).checked = true;

    $('[data-ext-preview]', d).hidden = true;
    $('[data-ext-preview]', d).replaceChildren();
    $('[data-ext-set]', d).replaceChildren(new Option('Belum ada subjek dipilih', ''));
    $('[data-ext-set-label]', d).value = '';
    applyMode(form);
    d.showModal();
    (openMode === 'preview' ? $('[data-ext-do-preview]', d) : $('[data-ext-cat]', d)).focus();
}

function applyMode(form) {
    const cfg = cfgForm(form);
    const mode = modeOf(form);
    const lim = JSON.parse(form.dataset.limits);
    const doaj = cfg.provider === 'doaj';
    const bulkNoTerms = doaj && ['corpus', 'incremental'].includes(mode);
    const searchRows = mode === 'quick' || mode === 'category' || (mode === 'incremental' && !doaj);

    show($('[data-ext-terms-row]', form), !bulkNoTerms);
    show($('[data-ext-set-row]', form), bulkNoTerms);
    show($('[data-ext-since-row]', form), mode === 'incremental');
    show($('[data-ext-yf-row]', form), searchRows);
    show($('[data-ext-yt-row]', form), searchRows);
    show($('[data-ext-type-row]', form), searchRows);
    show($('[data-ext-lang-row]', form), searchRows && cfg.filters.includes('language'));
    show($('[data-ext-oa-row]', form), searchRows && cfg.filters.includes('oa'));
    show($('[data-ext-save-row]', form), mode !== 'incremental' && mode !== 'corpus');

    const catRequired = !['corpus', 'incremental'].includes(mode) || (mode === 'incremental' && !doaj);
    $('[data-ext-cat-hint]', form).textContent = catRequired ? '(wajib)' : '(opsional: kosong = Belum diklasifikasi)';

    // Batas jumlah rekaman mengikuti mode dan batas pencarian penyedia (server tetap memvalidasi ulang).
    const providerCap = cfg.searchCap ?? (cfg.provider === 'openalex' && !cfg.hasKey ? 10000 : null);
    let max = mode === 'quick' ? lim.quick : lim.campaign;
    if (providerCap && !bulkNoTerms) max = Math.min(max, providerCap);
    const maxInput = $('[data-ext-max]', form);

    maxInput.max = max;
    if (Number(maxInput.value) > max) maxInput.value = max;
    $('[data-ext-max-hint]', form).textContent = `(maks ${nf.format(max)}${providerCap && !bulkNoTerms ? `, batas pencarian ${cfg.name}` : ''})`;
    $$('[data-ext-tier]', form).forEach((b) => { b.disabled = Number(b.dataset.extTier) > max; });

    $('[data-ext-mode-note]', form).textContent = cfg.modeNotes[mode] || ({
        quick: `Verifikasi awal, maksimal ${lim.quick} rekaman.`,
        category: 'Urutan relevansi dipertahankan. Dibatasi batas pencarian penyedia.',
        corpus: '',
        incremental: '',
    })[mode] || '';

    $('[data-ext-type]', form).disabled = !searchRows;
    fillFromMap(form);
    if (mode === 'incremental') suggestSince(form);
    updateSummary(form);
}

async function suggestSince(form) {
    const cfg = cfgForm(form);
    const params = new URLSearchParams({ category_id: $('[data-ext-cat]', form).value || '', set: $('[data-ext-set]', form).value || '' });
    const input = $('[data-ext-since]', form);
    const note = $('[data-ext-since-note]', form);

    try {
        const { since } = await getJson(`${urlFor(cfg.provider, 'since')}?${params}`);
        if (since && !input.value) input.value = since;
        note.textContent = since ? `Saran: akhir sinkronisasi terakhir yang selesai (${since}).` : 'Belum ada sinkronisasi sebelumnya untuk cakupan ini: pilih tanggal awal.';
    } catch {
        note.textContent = '';
    }
}

function fillFromMap(form) {
    const cfg = cfgForm(form);
    const map = (cfg.maps || {})[$('[data-ext-cat]', form).value];

    if (map && !['corpus'].includes(modeOf(form))) {
        $('[data-ext-terms]', form).value = map.terms;
        if (modeOf(form) !== 'quick') $('[data-ext-max]', form).value = Math.min(map.max, Number($('[data-ext-max]', form).max) || map.max);
    }
}

function updateSummary(form) {
    const cfg = cfgForm(form);
    const mode = modeOf(form);
    const cat = $('[data-ext-cat]', form);
    const category = cat.value ? cat.selectedOptions[0].textContent : 'tanpa kategori (belum diklasifikasi)';
    const scopeText = mode === 'corpus' || (mode === 'incremental' && cfg.provider === 'doaj')
        ? `subjek: ${$('[data-ext-set-label]', form).value || 'belum dipilih'}`
        : `${$('[data-ext-terms]', form).value.split(/[\r\n,;]+/).filter((t) => t.trim()).length} istilah · tahun ${$('[data-ext-yf]', form).value || '…'}–${$('[data-ext-yt]', form).value || '…'}`;
    const since = mode === 'incremental' ? ` · sejak ${$('[data-ext-since]', form).value || 'belum dipilih'}` : '';

    $('[data-ext-summary]', form).textContent = `Mode ${MODE_TITLES[mode]} · ${cfg.name} · Kategori: ${category} · ${scopeText}${since} · maks ${nf.format(Number($('[data-ext-max]', form).value) || 0)} rekaman · hanya API gratis, tanpa AI.`;
}

function initSyncForm() {
    const form = $('[data-ext-sync-form]');

    form.addEventListener('input', () => updateSummary(form));
    form.addEventListener('change', (event) => {
        if (event.target.name === 'mode') applyMode(form);
        else if (event.target.matches('[data-ext-cat]')) { fillFromMap(form); if (modeOf(form) === 'incremental') suggestSince(form); }
        else if (event.target.matches('[data-ext-set]')) {
            const opt = event.target.selectedOptions[0];
            $('[data-ext-set-label]', form).value = opt && opt.value ? opt.textContent : '';
            if (modeOf(form) === 'incremental') suggestSince(form);
        }
        updateSummary(form);
    });

    $$('[data-ext-tier]', form).forEach((b) => b.addEventListener('click', () => {
        $('[data-ext-max]', form).value = Math.min(Number(b.dataset.extTier), Number($('[data-ext-max]', form).max) || Number(b.dataset.extTier));
        updateSummary(form);
    }));

    // Pencarian subjek (set LCC) DOAJ: daftar di-cache server, hasil dibatasi 25.
    $('[data-ext-set-search]', form).addEventListener('click', async () => {
        const note = $('[data-ext-set-note]', form);
        const select = $('[data-ext-set]', form);

        note.textContent = 'Mencari subjek…';
        try {
            const json = await getJson(`${urlFor(form.dataset.provider, 'sets')}?q=${encodeURIComponent($('[data-ext-set-q]', form).value)}`);

            if (!json.ok) throw new Error(json.message);
            select.replaceChildren(new Option(json.sets.length ? `Pilih subjek (${json.sets.length} hasil)` : 'Tidak ada subjek cocok', ''), ...json.sets.map((s) => new Option(s.name, s.spec)));
            note.textContent = 'Seluruh DOAJ tidak diambil otomatis: korpus selalu per subjek dan dibatasi batas kampanye.';
        } catch (e) {
            note.textContent = e.message || 'Gagal memuat subjek.';
        }
    });

    form.addEventListener('submit', (event) => {
        const mode = modeOf(form);
        const cfg = cfgForm(form);
        const needCat = !['corpus', 'incremental'].includes(mode) || (mode === 'incremental' && cfg.provider !== 'doaj');

        if (needCat && !$('[data-ext-cat]', form).value) {
            event.preventDefault();
            toast('Pilih kategori BacaDulu sebelum sinkronisasi.', true);

            return;
        }
        if (cfg.provider === 'doaj' && ['corpus', 'incremental'].includes(mode) && !$('[data-ext-set]', form).value) {
            event.preventDefault();
            toast('Pilih subjek (set) DOAJ lebih dulu.', true);

            return;
        }
        const button = $('[data-ext-do-sync]', form);
        button.disabled = true;
        button.textContent = 'Memproses…';
    });

    $('[data-ext-do-preview]', form).addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const box = $('[data-ext-preview]', form);
        const data = new FormData(form);

        button.disabled = true;
        box.hidden = false;
        box.textContent = 'Mengambil contoh dari penyedia…';

        try {
            const response = await fetch(urlFor(form.dataset.provider, 'preview'), { method: 'POST', body: data, headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() } });
            const json = await response.json();

            if (json.ok) {
                box.innerHTML = json.html; // HTML dari server (Blade, sudah di-escape).
            } else {
                box.textContent = json.message || (json.errors && Object.values(json.errors)[0][0]) || 'Preview gagal.';
            }
        } catch {
            box.textContent = 'Preview gagal: tidak dapat menghubungi server.';
        } finally {
            button.disabled = false;
        }
    });
}

/* ------------------------------------------------------- semua kategori terpetakan (grup berurutan) */

function openAll() {
    const d = dialog('sync-all');
    const form = $('[data-ext-all-form]', d);
    const limit = Number(d.dataset.limit);
    const cats = Object.fromEntries($$('[data-ext-all-cat] option', d).filter((o) => o.value).map((o) => [o.value, o.textContent]));
    const connectors = $$('[data-ext-cfg]').map((li) => JSON.parse(li.dataset.extCfg));

    const render = () => {
        const provider = $('[data-ext-all-provider]', d).value;
        const cat = $('[data-ext-all-cat]', d).value;
        const override = Number($('[data-ext-all-max]', d).value) || 0;
        const plan = $('[data-ext-plan]', d);
        const items = [];
        let total = 0;
        let runs = 0;

        connectors.filter((c) => provider === 'all' || c.provider === provider).forEach((c) => {
            const entries = Object.entries(c.maps || {}).filter(([id]) => !cat || id === cat);

            if (!c.enabled) items.push({ skip: true, text: `${c.name}: dilewati, konektor nonaktif` });
            else if (!entries.length) items.push({ skip: true, text: `${c.name}: dilewati, belum ada pemetaan istilah${cat ? ' untuk kategori ini' : ''}` });
            else entries.forEach(([id, m]) => {
                const max = override || m.max;

                total += max;
                runs += 1;
                items.push({ text: `${c.name} · ${cats[id] || `#${id}`}: ${m.terms} · maks ${nf.format(max)}` });
            });
        });

        plan.replaceChildren(...items.map((i) => { const li = document.createElement('li'); li.textContent = i.text; li.className = i.skip ? 'is-skip' : ''; return li; }));
        const userTotal = Number($('[data-ext-all-total]', d).value) || 0;
        const over = userTotal > 0 && total > userTotal;
        const note = !userTotal ? ' — isi "Batas total kampanye" (wajib).' : over ? ` — MELEBIHI batas total ${nf.format(userTotal)}; isi atau kurangi batas per run.` : ` (batas total ${nf.format(userTotal)})`;

        $('[data-ext-all-summary]', d).textContent = runs ? `${runs} run berurutan · total maks ${nf.format(total)} rekaman${note}` : 'Tidak ada pemetaan aktif yang bisa dijalankan.';
        $('[data-ext-all-submit]', d).disabled = !runs || !userTotal || over || userTotal > limit;
    };

    ['change', 'input'].forEach((evt) => form.addEventListener(evt, render, { once: false }));
    render();
    d.showModal();
}

/* ------------------------------------------------------------------------------ test koneksi */

function initTest() {
    $$('[data-ext-test]').forEach((button) => button.addEventListener('click', async () => {
        const result = button.closest('[data-connector]').querySelector('[data-ext-test-result]');
        const label = button.textContent;

        button.disabled = true;
        button.textContent = 'Menguji…';
        result.textContent = 'Menghubungi penyedia…';

        try {
            const response = await fetch(button.dataset.extTest, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() } });
            const json = await response.json();
            const dot = document.createElement('span');

            dot.className = `bd-ext__dot ${json.ok ? 'is-ok' : 'is-bad'}`;
            result.replaceChildren(dot, document.createTextNode(`${json.ok ? 'Koneksi baik' : 'Koneksi gagal'} · ${json.message} · ${json.latency_ms} ms`));
        } catch {
            result.textContent = 'Tes koneksi gagal: tidak dapat menghubungi server.';
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
    }));
}

/* ------------------------------------------------------------------- detail rekaman + cek DOI */

function initRecords() {
    const d = dialog('record');
    const body = $('[data-ext-record-body]', d);

    $$('[data-ext-record]').forEach((button) => button.addEventListener('click', async () => {
        body.textContent = 'Memuat…';
        d.showModal();

        try {
            const response = await fetch(button.dataset.extRecord, { headers: { Accept: 'text/html' } });
            body.innerHTML = response.ok ? await response.text() : 'Rekaman tidak dapat dimuat.'; // HTML dari server (Blade, di-escape).
        } catch {
            body.textContent = 'Rekaman tidak dapat dimuat.';
        }
    }));

    // Pemeriksaan registrasi DOI: satu permintaan per klik (server membatasi kuota dan men-cache hasil).
    body.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-ext-doi-check]');

        if (!button) return;
        const box = button.closest('[data-ext-doi]');
        const status = $('[data-ext-doi-status]', box);

        button.disabled = true;
        status.textContent = 'Memeriksa ke doi.org…';
        try {
            const response = await fetch(box.dataset.url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token() } });
            const json = await response.json();

            status.textContent = json.label || 'Gagal memeriksa.';
            status.className = `bd-ext__chip ${({ registered: 'is-ok', not_found: 'is-bad', unknown: 'is-warn', throttled: 'is-warn' })[json.status] || 'is-mute'}`;
        } catch {
            status.textContent = 'Gagal memeriksa: tidak dapat menghubungi server.';
        } finally {
            button.disabled = false;
        }
    });
}

/* ---------------------------------------------------------------------------------- animasi */

function initMotion() {
    const counters = $$('[data-count]');

    if (!reduced) {
        counters.forEach((el) => {
            const target = Number(el.dataset.count);

            if (!target) return;
            const started = performance.now();
            const tick = (now) => {
                const t = Math.min(1, (now - started) / 900);

                el.textContent = Math.round(target * (1 - (1 - t) ** 3)).toLocaleString('id-ID');
                if (t < 1) requestAnimationFrame(tick);
            };
            el.textContent = '0';
            requestAnimationFrame(tick);
        });
    }

    const figures = $$('.bd-ext__chart');

    if (reduced || !('IntersectionObserver' in window)) {
        figures.forEach((f) => f.classList.add('is-in'));

        return;
    }

    root.classList.add('js-ext-armed');
    const observer = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
        }
    }), { threshold: 0.2 });
    figures.forEach((f) => observer.observe(f));
}

/** Mengubah angka di elemen dengan transisi singkat (langsung bila reduced motion). */
function setNum(el, value) {
    if (!el) return;
    const to = Number(value) || 0;
    const from = Number(String(el.textContent).replace(/\D/g, '')) || 0;

    if (reduced || from === to) {
        el.textContent = nf.format(to);

        return;
    }
    const started = performance.now();
    const tick = (now) => {
        const t = Math.min(1, (now - started) / 450);

        el.textContent = nf.format(Math.round(from + (to - from) * t));
        if (t < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

/* ---------------------------------------------------------------------------------- polling */

const CHIP = { running: 'is-run', queued: 'is-run', rate_limited: 'is-warn', waiting: 'is-mute', paused: 'is-warn', completed: 'is-ok', partial: 'is-warn', failed: 'is-bad', cancelled: 'is-mute' };

/**
 * Polling ringan dan adaptif: 4 dtk saat ada perubahan, melambat ke 12 dtk bila angka tidak bergerak; berhenti ketika tidak ada run aktif
 * (lalu memuat ulang sekali untuk menyegarkan KPI/riwayat dari server). Tab tersembunyi tidak memanggil server.
 */
function initPolling() {
    if (root.dataset.poll !== '1') {
        return;
    }

    let interval = 4000;
    let still = 0;
    let last = '';

    const tick = async () => {
        if (!document.hidden) {
            try {
                const { runs, active, emergency } = await getJson(root.dataset.statusUrl);
                const fingerprint = JSON.stringify(runs.map((r) => [r.id, r.status, r.processed, r.requests, r.failed]));

                runs.forEach(applyRun);
                still = fingerprint === last ? still + 1 : 0;
                last = fingerprint;
                interval = still >= 4 ? 12000 : 4000;

                if (emergency !== !!$('[data-ext-emergency-banner]')) {
                    setTimeout(() => window.location.reload(), 600);

                    return;
                }
                if (!active) {
                    setTimeout(() => window.location.reload(), 900);

                    return;
                }
            } catch {
                interval = 12000; // jaringan putus sesaat: coba lagi, lebih jarang
            }
        }
        setTimeout(tick, interval);
    };

    setTimeout(tick, interval);
}

function applyRun(run) {
    const row = $(`tr[data-run="${run.id}"]`);

    if (row) {
        $('[data-run-status]', row).textContent = run.label;
        ['found', 'new', 'updated', 'duplicate', 'failed'].forEach((key) => {
            const cell = $(`[data-run-${key}]`, row);

            if (cell) cell.textContent = run[key];
        });
    }

    const card = $(`[data-run-card="${run.id}"]`);

    if (card) {
        card.dataset.status = run.status;
        const chip = $('[data-run-status]', card);

        chip.textContent = run.label;
        chip.className = `bd-ext__chip ${CHIP[run.status] || 'is-mute'}`;

        const pct = run.target ? run.percent : (run.max ? Math.min(100, Math.round(run.processed / run.max * 100)) : 0);

        $('[data-run-bar]', card).style.width = `${pct}%`;
        $('[data-run-line]', card).textContent = `${nf.format(run.processed)} dari ${run.target ? `${nf.format(run.target)} (total penyedia, dibatasi target)` : `maks. ${nf.format(run.max)}`} rekaman diproses`;
        $('[data-run-percent]', card).textContent = run.target ? `${run.percent}%` : 'tanpa total dari penyedia: tidak ada persentase';
        $('[data-run-eta]', card).textContent = run.eta_minutes !== null ? ` · perkiraan sisa ≈ ${run.eta_minutes} menit (laju batch terakhir)` : '';
        [['new', 'new'], ['updated', 'updated'], ['duplicate', 'duplicate'], ['skipped', 'skipped'], ['failed', 'failed'], ['batches', 'batches']].forEach(([attr, key]) => setNum($(`[data-run-${attr}]`, card), run[key]));
        $('[data-run-req]', card).textContent = `${nf.format(run.requests)}/${nf.format(run.max_requests)}`;
        $('[data-run-req-bar]', card).style.width = `${run.max_requests ? Math.min(100, Math.round(run.requests / run.max_requests * 100)) : 0}%`;
        $('[data-run-min]', card).textContent = `${run.minutes}/${run.max_minutes}`;
        $('[data-run-min-bar]', card).style.width = `${run.max_minutes ? Math.min(100, Math.round(run.minutes / run.max_minutes * 100)) : 0}%`;
        $('[data-run-stop]', card).textContent = run.stop_label ? `Alasan berhenti/selesai: ${run.stop_label}` : '';
        $('[data-run-error]', card).textContent = run.error || '';

        const spark = $('[data-run-spark]', card);

        if (spark && run.spark.length) {
            const top = Math.max(1, ...run.spark);

            spark.replaceChildren(...run.spark.map((v) => { const i = document.createElement('i'); i.style.height = `${Math.max(8, Math.round(v / top * 100))}%`; i.title = `${v} rekaman`; return i; }));
        }
    }

    const progress = $(`[data-ext-progress="${run.id}"]`);

    if (progress && run.max) {
        $('.bd-ext__bar span', progress).style.width = `${Math.min(100, Math.round((run.processed / run.max) * 100))}%`;
        $('p', progress).textContent = `#${run.id} · ${nf.format(run.processed)} dari maks. ${nf.format(run.max)} diproses · ${nf.format(run.new)} baru`;
    }
}
