/*
 * Pengaturan (admin/settings). Dimuat sesuai kebutuhan oleh admin.js saat halaman [data-st] terbuka.
 *
 * Peningkatan progresif: semua kontrol adalah elemen form native dan formulir tetap tersimpan lewat PUT biasa tanpa JavaScript. Dengan JS:
 *  - draf dilacak terhadap nilai tersimpan (tidak ada yang berlaku sebelum disimpan, dan tidak ada autosave);
 *  - validasi cepat memakai aturan yang sama dengan server (server tetap yang menentukan);
 *  - perubahan berdampak besar meminta konfirmasi eksplisit sebelum dikirim;
 *  - simpan async (fetch + CSRF); input tidak dibuang saat gagal, konflik dengan admin lain ditolak server (409).
 * Ringkasan efek dihitung di browser dari draf: tidak ada permintaan jaringan, tidak ada tulis, tidak ada panggilan AI.
 * Gerak memakai transform/opacity dan dimatikan oleh prefers-reduced-motion.
 */

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const nf = new Intl.NumberFormat('id-ID');
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const HEADERS = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

// Kunci form => [label, tipe, min, max, dampak]. Dibaca dari atribut DOM, bukan di-hardcode, kecuali aturan lintas-field di bawah.
let root = null;
let form = null;
let baseline = {};
let toastTimer = 0;
let saving = false;
let searchActive = false;
let active = 'riset';

export function initAdminSettings() {
    root = $('[data-st]');
    form = $('[data-st-form]');

    if (!root || !form) {
        return;
    }

    root.classList.add('is-js');
    active = $('[data-st-tab].is-on')?.dataset.stTab ?? 'riset';
    baseline = readValues();
    showPanel(active, false);
    placeIndicator();
    countUp();
    wire();
    refreshAll();
    setTimeout(() => root.classList.remove('is-entering'), 1200);
}

/* --------------------------------------------------------------------------------------------- nilai */

const fields = () => $$('[data-st-input]', form);

function readValues() {
    const values = {};

    fields().forEach((input) => {
        if (input.type === 'radio') {
            if (input.checked) {
                values[input.name] = input.value;
            }
        } else {
            values[input.name] = input.value;
        }
    });

    return values;
}

const same = (a, b) => {
    if (a === undefined || b === undefined) {
        return a === b;
    }

    const na = Number(a);
    const nb = Number(b);

    return String(a).trim() !== '' && Number.isFinite(na) && Number.isFinite(nb) ? na === nb : String(a) === String(b);
};

function setValue(name, value) {
    const inputs = $$(`[name="${name}"][data-st-input]`, form);

    inputs.forEach((input) => {
        if (input.type === 'radio') {
            input.checked = input.value === String(value);
        } else {
            input.value = value;
        }
    });
    syncRange(name);
}

function syncRange(name) {
    const range = $(`[data-st-range="${name}"]`, form);
    const number = $(`[name="${name}"][data-st-input]`, form);

    if (range && number) {
        range.value = number.value;
        const min = Number(range.min);
        const max = Number(range.max);
        const pct = max > min ? ((Number(number.value || min) - min) / (max - min)) * 100 : 0;

        range.style.setProperty('--pct', `${Math.max(0, Math.min(100, pct))}%`);
    }
}

const rowOf = (name) => $(`[data-st-row="${name}"]`, form);
const labelOf = (name) => rowOf(name)?.dataset.stLabel ?? name;

/* --------------------------------------------------------------------------------------- validasi cepat */

const RULES = {
    research_queue: { re: /^[a-zA-Z0-9._-]+$/, max: 80 },
    ingestion_queue: { re: /^[a-zA-Z0-9._-]+$/, max: 80 },
};

function validate(values) {
    const errors = {};

    fields().forEach((input) => {
        const name = input.name;

        if (input.type === 'radio' && !input.checked) {
            return;
        }

        const value = values[name];
        const row = rowOf(name);
        const label = row?.dataset.stLabel ?? name;

        if (value === undefined || String(value).trim() === '') {
            errors[name] = `${label} wajib diisi.`;

            return;
        }

        if (input.type === 'number') {
            const n = Number(value);
            const min = Number(input.min);
            const max = Number(input.max);
            const intOnly = Number(input.step) >= 1;

            if (!Number.isFinite(n)) {
                errors[name] = `${label} harus berupa angka.`;
            } else if (intOnly && !Number.isInteger(n)) {
                errors[name] = `${label} harus berupa bilangan bulat.`;
            } else if (n < min) {
                errors[name] = `${label} minimal ${nf.format(min)}.`;
            } else if (n > max) {
                errors[name] = `${label} maksimal ${nf.format(max)}.`;
            }
        } else if (RULES[name]) {
            if (!RULES[name].re.test(value)) {
                errors[name] = `${label} hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.`;
            } else if (value.length > RULES[name].max) {
                errors[name] = `${label} maksimal ${RULES[name].max} karakter.`;
            }
        }
    });

    const n = (key) => Number(values[key]);
    const ok = (key) => !errors[key] && Number.isFinite(n(key));

    ok('chunk_overlap_words') && ok('chunk_words') && n('chunk_overlap_words') >= n('chunk_words') && (errors.chunk_overlap_words = 'Tumpang tindih harus lebih kecil daripada kata per potongan.');
    ok('minimum_chunk_words') && ok('chunk_words') && n('minimum_chunk_words') > n('chunk_words') && (errors.minimum_chunk_words = 'Kata minimum tidak boleh lebih besar daripada kata per potongan.');
    ok('minimum_evidence') && ok('evidence_limit') && n('minimum_evidence') > n('evidence_limit') && (errors.minimum_evidence = 'Bukti minimum tidak boleh melebihi bukti maksimum, karena riset tidak akan pernah menghasilkan jawaban.');
    ok('max_per_document') && ok('evidence_limit') && n('max_per_document') > n('evidence_limit') && (errors.max_per_document = 'Bukti per dokumen tidak boleh melebihi bukti maksimum.');

    return errors;
}

function showErrors(errors) {
    $$('[data-st-row]', form).forEach((row) => {
        const name = row.dataset.stRow;
        const box = $('[data-st-error]', row);
        const message = errors[name];

        row.classList.toggle('has-error', Boolean(message));
        $$('[data-st-input]', row).forEach((input) => input.setAttribute('aria-invalid', message ? 'true' : 'false'));

        if (box) {
            box.textContent = message ?? '';
            box.hidden = !message;
        }
    });
}

/* ---------------------------------------------------------------------------------------------- draf */

function dirtyNames(values = readValues()) {
    return Object.keys(baseline).filter((name) => !same(values[name], baseline[name]));
}

function refreshAll() {
    const values = readValues();
    const dirty = dirtyNames(values);
    const errors = validate(values);

    $$('[data-st-row]', form).forEach((row) => {
        const name = row.dataset.stRow;
        const isDirty = dirty.includes(name);
        const input = $(`[name="${name}"][data-st-input]`, row);
        const def = input?.dataset.default ?? '';
        const src = $('[data-st-src]', row);
        const reset = $('[data-st-reset]', row);

        row.classList.toggle('is-dirty', isDirty);

        if (src) {
            src.textContent = isDirty ? 'Draf belum disimpan' : row.classList.contains('is-changed') ? 'Diubah admin' : 'Bawaan server';
        }

        if (reset) {
            reset.hidden = same(values[name], def);
        }
    });

    // Bilah simpan.
    const bar = $('[data-st-savebar]');
    const was = !bar.hidden;

    bar.hidden = dirty.length === 0;
    $('[data-st-count]').textContent = String(dirty.length);
    $('[data-st-count-label]').textContent = dirty.length === 1 ? 'perubahan belum disimpan' : 'perubahan belum disimpan';

    if (!was && dirty.length > 0) {
        bar.setAttribute('aria-live', 'polite');
    }

    // Penanda per kelompok.
    $$('[data-st-tabmeta]').forEach((meta) => {
        const key = meta.dataset.stTabmeta;
        const n = dirty.filter((name) => rowOf(name)?.closest('[data-st-panel]')?.dataset.stPanel === key).length;

        if (!meta.dataset.base) {
            meta.dataset.base = meta.textContent.trim();
        }

        meta.classList.toggle('has-dirty', n > 0);
        meta.textContent = n > 0 ? `${n} diubah` : meta.dataset.base;
    });

    // Galat hanya untuk field yang sudah disentuh/berubah agar halaman awal tidak penuh pesan.
    showErrors(Object.fromEntries(Object.entries(errors).filter(([name]) => dirty.includes(name) || rowOf(name)?.dataset.touched)));
    renderPreviews(values, errors);
    window.onbeforeunload = dirty.length > 0 ? (event) => { event.preventDefault(); return ''; } : null;
}

/* --------------------------------------------------------------------------------------------- preview */

function compose(node, parts) {
    node.replaceChildren(...parts.map((part) => {
        if (typeof part === 'string') {
            return document.createTextNode(part);
        }

        const strong = document.createElement('span');

        strong.dataset.stV = '1';
        strong.textContent = part.v;

        return strong;
    }));
}

function renderPreviews(v, errors) {
    const num = (key) => (Number.isFinite(Number(v[key])) && String(v[key]).trim() !== '' ? nf.format(Number(v[key])) : '—');
    const text = {
        riset: () => [
            ['Sistem memeriksa hingga ', { v: `${num('candidate_limit')} potongan` }, ' dokumen untuk satu pertanyaan, lalu menyerahkan maksimal ', { v: `${num('evidence_limit')} bukti` }, ' (paling banyak ', { v: num('max_per_document') }, ' dari satu dokumen). Jawaban dibuat hanya bila ditemukan sedikitnya ', { v: `${num('minimum_evidence')} bukti` }, ' dengan skor relevansi minimal ', { v: String(v.minimum_score ?? '—').replace('.', ',') }, '. Pertanyaan lanjutan membawa ', { v: `${num('context_messages')} pesan` }, ' terakhir sebagai konteks.'],
            errors.minimum_evidence ? { bad: true, t: errors.minimum_evidence } : Number(v.minimum_score) >= 0.5 ? { bad: false, t: 'Skor minimum 0,5 atau lebih tinggi cukup ketat: sebagian riset bisa berhenti karena kekurangan bukti.' } : null,
        ],
        sumber: () => {
            const step = Number(v.chunk_words) - Number(v.chunk_overlap_words);
            const chunks = step > 0 ? Math.max(1, Math.ceil((10000 - Number(v.chunk_overlap_words)) / step)) : null;

            return [
                ['Berkas hingga ', { v: `${nf.format(Number(v.upload_limit_kb) / 1024)} MB` }, ' dapat diunggah. Dokumen dipotong ± ', { v: `${num('chunk_words')} kata` }, ' per potongan dengan tumpang tindih ', { v: num('chunk_overlap_words') }, ' kata; sebagai ilustrasi, dokumen 10.000 kata menjadi sekitar ', { v: chunks === null ? '—' : String(chunks) }, ' potongan (perkiraan). Potongan di bawah ', { v: num('minimum_chunk_words') }, ' kata dibuang. Hanya berlaku untuk sumber yang diproses setelah disimpan.'],
                errors.chunk_overlap_words || errors.minimum_chunk_words ? { bad: true, t: errors.chunk_overlap_words ?? errors.minimum_chunk_words } : null,
            ];
        },
        dokumen: () => [['Formulir dokumen baru akan memilih jenis ', { v: v.default_document_type ?? '—' }, ' dengan gaya sitasi ', { v: v.default_citation_style ?? '—' }, '. Pengguna tetap dapat menggantinya; dokumen yang sudah dibuat tidak berubah.'], null],
        operasional: () => [
            Number(v.profile_gate) === 0 ? ['Syarat kelengkapan profil dinonaktifkan: semua pengguna aktif langsung dapat memakai Workspace.'] : ['Pengguna dengan kelengkapan profil di bawah ', { v: `${num('profile_gate')}%` }, ' diminta melengkapi profil sebelum memakai Workspace. Riset masuk antrean ', { v: v.research_queue ?? '—' }, ' dan pemrosesan sumber ke antrean ', { v: v.ingestion_queue ?? '—' }, '; worker harus mendengarkan kedua nama itu.'],
            Number(v.profile_gate) >= 95 ? { bad: false, t: 'Syarat profil hampir penuh: pengguna yang profilnya belum lengkap akan tertahan.' } : v.research_queue === v.ingestion_queue ? { bad: false, t: 'Kedua pekerjaan memakai antrean yang sama; pemrosesan sumber yang panjang dapat menunda riset.' } : null,
        ],
    };

    $$('[data-st-preview]').forEach((box) => {
        const [parts, note] = text[box.dataset.stPreview]?.() ?? [[], null];
        const warn = $('[data-st-preview-warn]', box);

        compose($('[data-st-preview-text]', box), parts);

        if (warn) {
            warn.hidden = !note;
            warn.classList.toggle('is-bad', Boolean(note?.bad));
            warn.textContent = note?.t ?? '';
        }
    });
}

/* ------------------------------------------------------------------------------------------ navigasi */

function showPanel(key, animate = true) {
    active = key;

    $$('[data-st-panel]').forEach((panel) => {
        const on = panel.dataset.stPanel === key;

        panel.hidden = !on;

        if (on && animate && !reduced()) {
            panel.classList.remove('is-entering-panel');
            void panel.offsetWidth;
            panel.classList.add('is-entering-panel');
        }
    });
    $$('[data-st-tab]').forEach((tab) => {
        const on = tab.dataset.stTab === key;

        tab.classList.toggle('is-on', on);
        tab.setAttribute('aria-selected', String(on));
        tab.tabIndex = on ? 0 : -1;
    });
    placeIndicator();
}

function placeIndicator() {
    const tab = $('[data-st-tab].is-on');
    const ind = $('[data-st-ind]');

    if (tab && ind) {
        ind.style.setProperty('--y', `${tab.offsetTop}px`);
        ind.style.setProperty('--h', `${tab.offsetHeight}px`);
    }
}

function selectTab(key, focus = false) {
    if (searchActive) {
        clearSearch();
    }

    showPanel(key);

    const url = new URL(location.href);

    url.searchParams.set('kelompok', key);
    history.replaceState(null, '', url);

    if (focus) {
        $(`[data-st-tab="${key}"]`)?.focus();
    }
}

/* ---------------------------------------------------------------------------------------------- cari */

function runSearch(query) {
    const q = query.trim().toLowerCase();
    const tokens = q.split(/\s+/).filter(Boolean);
    const found = $('[data-st-found]');

    if (tokens.length === 0) {
        clearSearch();

        return;
    }

    searchActive = true;

    let total = 0;

    $$('[data-st-panel]').forEach((panel) => {
        const rows = $$('[data-st-row]', panel);
        let hits = 0;

        rows.forEach((row) => {
            const match = tokens.every((token) => row.dataset.stKeywords.includes(token));

            row.hidden = !match;
            row.classList.toggle('is-hit', false);
            match && hits++;
        });

        $$('[data-st-adv]', panel).forEach((adv) => {
            const any = $$('[data-st-row]', adv).some((row) => !row.hidden);

            adv.hidden = !any;
            any && (adv.open = true);
        });

        const key = panel.dataset.stPanel;
        const panelText = (panel.textContent ?? '').toLowerCase();
        const sysHit = rows.length === 0 && tokens.every((token) => panelText.includes(token));

        panel.hidden = hits === 0 && !sysHit;
        $$('.bd-st__preview, .bd-st__util, .bd-st__foreign', panel).forEach((el) => { el.hidden = true; });
        $(`[data-st-tab="${key}"]`)?.classList.toggle('has-match', hits > 0 || sysHit);
        total += hits;
    });

    found.hidden = false;
    found.textContent = total === 0 ? 'Tidak ada pengaturan yang cocok.' : `${total} pengaturan cocok. Menampilkan semua kelompok yang memuatnya.`;
}

function clearSearch() {
    searchActive = false;
    $$('[data-st-row], [data-st-adv]').forEach((el) => { el.hidden = false; el.classList.remove('is-hit'); });
    $$('.bd-st__preview, .bd-st__util, .bd-st__foreign').forEach((el) => { el.hidden = false; });
    $$('[data-st-tab]').forEach((tab) => tab.classList.remove('has-match'));
    $('[data-st-found]').hidden = true;
    $('[data-st-search]').value = '';
    showPanel(active, false);
}

/* ---------------------------------------------------------------------------------------------- simpan */

const IMPACT = {
    profile_gate: 'Pengguna dengan kelengkapan profil di bawah nilai ini tidak dapat memakai Workspace sampai melengkapi profil.',
    minimum_evidence: 'Riset berhenti tanpa jawaban bila bukti yang ditemukan kurang dari nilai ini.',
    minimum_score: 'Potongan dengan skor di bawah nilai ini dibuang; nilai tinggi dapat membuat banyak riset tidak menemukan bukti.',
};

function toast(message, bad = false) {
    const box = $('[data-st-toast]');

    clearTimeout(toastTimer);
    box.hidden = true;
    box.classList.toggle('is-bad', bad);
    box.textContent = message;
    requestAnimationFrame(() => { box.hidden = false; });
    toastTimer = setTimeout(() => { box.hidden = true; }, bad ? 7000 : 4000);
}

function onSubmit(event) {
    event.preventDefault();

    if (saving) {
        return;
    }

    const values = readValues();
    const errors = validate(values);
    const dirty = dirtyNames(values);

    $$('[data-st-row]', form).forEach((row) => { row.dataset.touched = '1'; });
    showErrors(errors);

    const bad = Object.keys(errors)[0];

    if (bad) {
        const row = rowOf(bad);

        selectTab(row.closest('[data-st-panel]').dataset.stPanel);
        row.closest('details')?.setAttribute('open', '');
        requestAnimationFrame(() => $(`[name="${bad}"][data-st-input]`, row)?.focus());
        toast('Periksa kolom yang ditandai sebelum menyimpan.', true);

        return;
    }

    const impacted = dirty.filter((name) => IMPACT[name]);

    if (impacted.length === 0) {
        send();

        return;
    }

    const list = $('[data-st-dialog-list]');

    list.replaceChildren(...impacted.map((name) => {
        const li = document.createElement('li');
        const strong = document.createElement('strong');
        const diff = document.createElement('span');
        const small = document.createElement('small');

        strong.textContent = labelOf(name);
        diff.textContent = `${baseline[name]} → ${values[name]}`;
        small.textContent = IMPACT[name];
        li.append(strong, diff, small);

        return li;
    }));
    $('[data-st-dialog]').showModal();
    $('[data-st-confirm]').focus();
}

async function send() {
    const button = $('[data-st-save]');

    saving = true;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.textContent = 'Menyimpan…';

    try {
        const response = await fetch(form.action, { method: 'POST', headers: { ...HEADERS, 'Content-Type': 'application/x-www-form-urlencoded' }, credentials: 'same-origin', body: new URLSearchParams(new FormData(form)) });
        const json = await response.json().catch(() => null);

        if (response.status === 422 && json?.errors) {
            const errors = Object.fromEntries(Object.entries(json.errors).map(([name, messages]) => [name, messages[0]]));

            $$('[data-st-row]', form).forEach((row) => { row.dataset.touched = '1'; });
            showErrors(errors);

            const first = Object.keys(errors)[0];

            if (first && rowOf(first)) {
                selectTab(rowOf(first).closest('[data-st-panel]').dataset.stPanel);
                rowOf(first).closest('details')?.setAttribute('open', '');
                $(`[name="${first}"][data-st-input]`, rowOf(first))?.focus();
            }

            toast('Server menolak sebagian nilai. Perubahan Anda tidak dibuang.', true);

            return;
        }

        if (response.status === 409 && json?.conflict) {
            const box = $('[data-st-conflict]');

            $('[data-st-conflict-text]').textContent = json.message;
            box.hidden = false;
            box.scrollIntoView({ block: 'nearest', behavior: reduced() ? 'auto' : 'smooth' });
            toast('Penyimpanan dibatalkan: ada perubahan dari admin lain.', true);

            return;
        }

        if (!response.ok || !json?.ok) {
            throw new Error(response.status === 419 ? 'Sesi berakhir. Muat ulang halaman lalu coba lagi.' : (json?.message ?? 'Pengaturan tidak dapat disimpan.'));
        }

        // Berhasil: nilai tersimpan menjadi dasar baru; draf kosong.
        baseline = readValues();
        $('[data-st-revision-input]').value = json.revision;
        root.dataset.stRevision = json.revision;
        $$('[data-st-row]', form).forEach((row) => {
            const input = $(`[name="${row.dataset.stRow}"][data-st-input]`, row);

            row.classList.toggle('is-changed', !same(baseline[row.dataset.stRow], input?.dataset.default));
            delete row.dataset.touched;
        });
        refreshAll();
        toast(json.message);
        refreshRegions();
    } catch (error) {
        // TypeError dari fetch = jaringan putus (pesan bawaan browser berbahasa Inggris, jadi tidak ditampilkan).
        toast(error instanceof TypeError || !error.message ? 'Koneksi terputus. Perubahan belum disimpan dan tidak dibuang; coba lagi.' : error.message, true);
    } finally {
        saving = false;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        button.textContent = 'Simpan perubahan';
    }
}

// Ringkasan dan riwayat diambil ulang dari server; formulir tidak diganti agar fokus dan posisi gulir tetap.
async function refreshRegions() {
    try {
        const response = await fetch(location.pathname, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });

        if (!response.ok) {
            return;
        }

        const next = new DOMParser().parseFromString(await response.text(), 'text/html');

        ['overview', 'history', 'headmeta'].forEach((name) => {
            const fresh = next.querySelector(`[data-st-swap="${name}"]`);
            const old = $(`[data-st-swap="${name}"]`);

            fresh && old && old.replaceWith(document.importNode(fresh, true));
        });
    } catch {
        // Ringkasan akan segar pada muat berikutnya; penyimpanan sudah berhasil.
    }
}

/* ------------------------------------------------------------------------------------- hitung naik KPI */

function countUp() {
    $$('[data-st-count-up]').forEach((node) => {
        const to = Number(node.dataset.stCountUp);

        if (reduced() || !Number.isFinite(to) || to === 0) {
            return;
        }

        const start = performance.now();
        const step = (now) => {
            const t = Math.min(1, Math.max(0, (now - start) / 600));

            node.textContent = nf.format(Math.round(to * (1 - (1 - t) ** 3)));

            if (t < 1 && node.isConnected) {
                requestAnimationFrame(step);
            } else {
                node.textContent = nf.format(to);
            }
        };

        node.textContent = '0';
        requestAnimationFrame(step);
    });
}

/* --------------------------------------------------------------------------------------------- event */

function wire() {
    form.addEventListener('submit', onSubmit);

    form.addEventListener('input', (event) => {
        const input = event.target.closest('[data-st-input], [data-st-range]');

        if (!input) {
            return;
        }

        if (input.matches('[data-st-range]')) {
            const number = $(`[name="${input.dataset.stRange}"][data-st-input]`, form);

            number.value = input.value;
            syncRange(input.dataset.stRange);
        } else if (input.type === 'number') {
            syncRange(input.name);
        }

        const hint = $('[data-st-kb-hint]', form);

        if (hint && input.name === 'upload_limit_kb') {
            hint.textContent = `≈ ${nf.format(Number(input.value) / 1024)} MB`;
        }

        refreshAll();
    });

    form.addEventListener('change', (event) => {
        const input = event.target.closest('[data-st-input]');

        if (input) {
            rowOf(input.name)?.setAttribute('data-touched', '1');
            refreshAll();
        }
    });

    form.addEventListener('click', (event) => {
        const reset = event.target.closest('[data-st-reset]');

        if (reset) {
            const name = reset.dataset.stReset;
            const input = $(`[name="${name}"][data-st-input]`, form);

            setValue(name, input.dataset.default);
            refreshAll();
            $(`[name="${name}"][data-st-input]:checked, [name="${name}"][data-st-input]`, form)?.focus();

            return;
        }

    });

    // Bilah simpan berada di luar <form> (agar menempel sampai akhir halaman), jadi Batalkan dipasang pada root.
    root.addEventListener('click', (event) => {
        if (event.target.closest('[data-st-cancel]')) {
            Object.entries(baseline).forEach(([name, value]) => setValue(name, value));
            $$('[data-st-row]', form).forEach((row) => { delete row.dataset.touched; });
            refreshAll();
            toast('Perubahan dibatalkan. Nilai kembali ke yang tersimpan.');
        }
    });

    $('[data-st-tabs]').addEventListener('click', (event) => {
        const tab = event.target.closest('[data-st-tab]');

        tab && selectTab(tab.dataset.stTab);
    });

    $('[data-st-tabs]').addEventListener('keydown', (event) => {
        const tabs = $$('[data-st-tab]');
        const at = tabs.findIndex((tab) => tab.dataset.stTab === active);
        const move = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];

        if (move || event.key === 'Home' || event.key === 'End') {
            event.preventDefault();
            const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs.at(-1) : tabs[(at + move + tabs.length) % tabs.length];

            selectTab(next.dataset.stTab, true);
        }
    });

    const search = $('[data-st-search]');

    search.addEventListener('input', () => runSearch(search.value));
    search.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();

            const hit = $$('[data-st-row]', form).find((row) => !row.hidden && !row.closest('[hidden]'));

            if (hit) {
                hit.classList.add('is-hit');
                $('[data-st-input], [data-st-range]', hit)?.focus({ preventScroll: false });
            }
        } else if (event.key === 'Escape' && search.value) {
            event.stopPropagation();
            clearSearch();
        }
    });

    $('[data-st-dialog]').addEventListener('close', (event) => {
        if (event.target.returnValue === 'confirm') {
            send();
        } else {
            $('[data-st-save]').focus();
        }

        event.target.returnValue = '';
    });

    $('[data-st-reload]').addEventListener('click', () => {
        window.onbeforeunload = null;
        location.reload();
    });

    window.addEventListener('resize', () => {
        clearTimeout(window.__stResize);
        window.__stResize = setTimeout(placeIndicator, 120);
    });
}
