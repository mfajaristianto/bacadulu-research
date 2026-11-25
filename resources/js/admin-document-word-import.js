/*
 * Impor dari Word (admin/prompt-documents?word=1). Dimuat oleh admin-document-prompts.js saat [data-dp-word] ada.
 *
 * Alur: pilih/seret .docx -> unggah (XHR, kemajuan unggah yang nyata, bisa dibatalkan) -> server membaca dan mengusulkan struktur ->
 * admin meninjau dan mengubah di editor ini (semua perubahan hanya di memori halaman) -> "Simpan sebagai Draf" mengirim hasil
 * tinjauan. Server memvalidasi ulang semuanya; tidak ada yang tersimpan sebelum tombol itu dan tidak ada yang diaktifkan.
 *
 * Isi dokumen adalah data tak tepercaya: semua teks dirender lewat textContent / nilai input, tidak pernah innerHTML.
 */

const $ = (selector, scope = document) => scope.querySelector(selector);
const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
const WORD = /[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*/gu; // sama dengan DocumentPrompts::wordCount

const ROLES = { body: 'Isi naskah', references: 'Daftar pustaka', qa: 'Pertanyaan dan jawaban' };
const needsCheck = (s) => s.flags.length > 0 || s.confidence === 'rendah';
let headText = null;

let root = null;
let file = null;
let xhr = null;
let data = null; // hasil parse + suntingan admin
let sel = 0;
let nameTimer = 0;

export function initWordImport() {
    root = $('[data-dp-word]');

    if (!root || root.dataset.ready === '1') {
        return;
    }

    root.dataset.ready = '1';
    file = xhr = data = null;
    sel = 0;

    wireUpload();
    wireEditor();

    // Berkas yang dilepas di luar area unggah tidak boleh membuat browser membuka berkas dan membuang pekerjaan.
    ['dragover', 'drop'].forEach((type) => window.addEventListener(type, (event) => {
        if ($('[data-dp-word]') && !event.target.closest?.('[data-wi-drop]')) {
            event.preventDefault();
        }
    }));
}

/* ------------------------------------------------------------------------------------------------- util */

function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);

    Object.entries(attrs).forEach(([key, value]) => {
        if (value === false || value === null || value === undefined) {
            return;
        }

        if (key === 'class') {
            el.className = value;
        } else if (key.startsWith('on')) {
            el.addEventListener(key.slice(2), value);
        } else if (key === 'value') {
            el.value = value;
        } else {
            el.setAttribute(key, value === true ? '' : value);
        }
    });

    children.flat().forEach((child) => {
        if (child !== null && child !== undefined && child !== false) {
            el.append(child.nodeType ? child : document.createTextNode(String(child)));
        }
    });

    return el;
}

const words = (text) => (text.match(WORD) ?? []).length;
const fmt = (n) => Number(n).toLocaleString('id-ID');
const size = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const notify = (message, ok = true) => root.dispatchEvent(new CustomEvent('dp:notify', { bubbles: true, detail: { message, ok } }));

function confirmDialog({ title, lines, ok, cancel = 'Batal', danger = false }) {
    const dialog = $('[data-dp-confirm]');

    $('[data-dp-confirm-title]', dialog).textContent = title;
    $('[data-dp-confirm-text]', dialog).replaceChildren(...lines.map((line) => h('p', {}, line)));

    const okButton = $('[data-dp-confirm-ok]', dialog);

    okButton.textContent = ok;
    okButton.classList.toggle('bd-ix__btn--danger', danger);
    okButton.classList.toggle('bd-ix__btn--primary', !danger);
    $('[data-dp-confirm-cancel]', dialog).textContent = cancel;

    return new Promise((resolve) => {
        dialog.returnValue = '';
        dialog.addEventListener('close', () => resolve(dialog.returnValue === 'ok'), { once: true });
        okButton.onclick = () => dialog.close('ok');
        $('[data-dp-confirm-cancel]', dialog).onclick = () => dialog.close('cancel');
        dialog.showModal();
    });
}

/* ------------------------------------------------------------------------------------------ unggah */

function wireUpload() {
    const drop = $('[data-wi-drop]', root);
    const input = $('[data-wi-file]', root);
    const open = () => input.click();
    const over = (on) => drop.classList.toggle('is-over', on);

    drop.addEventListener('click', (event) => {
        if (!event.target.closest('[data-wi-browse]')) {
            open();
        }
    });
    $('[data-wi-browse]', root).addEventListener('click', (event) => {
        event.stopPropagation();
        open();
    });
    drop.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            open();
        }
    });
    ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
        over(true);
    }));
    drop.addEventListener('dragleave', (event) => {
        if (!drop.contains(event.relatedTarget)) {
            over(false);
        }
    });
    drop.addEventListener('drop', (event) => {
        event.preventDefault();
        over(false);

        const files = [...(event.dataTransfer?.files ?? [])];

        if (files.length !== 1) {
            return showError(files.length === 0 ? 'Tidak ada berkas yang terbaca dari seretan.' : 'Unggah satu berkas per impor. Impor berkas berikutnya setelah yang ini selesai.');
        }

        pick(files[0]);
    });
    input.addEventListener('change', () => {
        if (input.files[0]) {
            pick(input.files[0]);
        }

        input.value = ''; // berkas yang sama bisa dipilih ulang setelah dibatalkan
    });

    $('[data-wi-cancel]', root).addEventListener('click', clearPick);
    $('[data-wi-read]', root).addEventListener('click', upload);
    $('[data-wi-abort]', root).addEventListener('click', () => xhr?.abort());
}

function showError(message) {
    const box = $('[data-wi-error]', root);

    box.hidden = !message;
    box.textContent = message ?? '';
}

function pick(f) {
    showError(null);

    const max = Number(root.dataset.maxBytes);
    const ext = f.name.split('.').pop().toLowerCase();

    if (ext !== 'docx') {
        return showError(ext === 'doc' ? 'Format .doc (Word lama) belum didukung. Buka di Word lalu simpan sebagai .docx.' : ext === 'pdf' ? 'PDF tidak diterima oleh importer ini. Gunakan berkas Word (.docx).' : 'Hanya berkas .docx yang didukung.');
    }

    if (f.size === 0) {
        return showError('Berkas kosong.');
    }

    if (f.size > max) {
        return showError(`Ukuran berkas ${fmt(Math.ceil(f.size / 1024))} KB melebihi batas ${fmt(Math.floor(max / 1024))} KB.`);
    }

    file = f;
    $('[data-wi-name]', root).textContent = f.name;
    $('[data-wi-size]', root).textContent = ` · ${size(f.size)}`;
    $('[data-wi-picked]', root).hidden = false;
    $('[data-wi-read]', root).focus();
}

function clearPick() {
    file = null;
    $('[data-wi-picked]', root).hidden = true;
    showError(null);
    $('[data-wi-drop]', root).focus();
}

function upload() {
    if (!file || xhr) {
        return;
    }

    showError(null);

    const body = new FormData();

    body.append('file', file);
    body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

    const picked = $('[data-wi-picked]', root);
    const progress = $('[data-wi-progress]', root);
    const bar = $('[data-wi-bar]', root);
    const label = $('[data-wi-progress-text]', root);
    const finish = () => {
        xhr = null;
        progress.hidden = true;
        progress.removeAttribute('aria-busy');
        picked.hidden = !file;
        $('[data-wi-drop]', root).classList.remove('is-busy');
    };

    xhr = new XMLHttpRequest();
    xhr.open('POST', root.dataset.parseUrl);
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.timeout = 90000;

    // Kemajuan unggah adalah kemajuan nyata (byte terkirim). Setelah unggah selesai, pembacaan di server tidak punya persen: tampil tak tentu.
    xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
            bar.value = Math.round((event.loaded / event.total) * 100);
            label.textContent = `Mengunggah… ${bar.value}%`;
        }
    };
    xhr.upload.onload = () => {
        bar.removeAttribute('value');
        label.textContent = 'Membaca isi dokumen di server…';
    };
    xhr.onload = () => {
        let json = null;

        try {
            json = JSON.parse(xhr.responseText);
        } catch {
            json = null;
        }

        const status = xhr.status;

        finish();

        if (status === 200 && json?.ok) {
            show(json.preview);

            return;
        }

        showError(status === 419 ? 'Sesi berakhir. Muat ulang halaman lalu masuk lagi.' : status === 429 ? 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.' : status === 413 ? (json?.message ?? 'Berkas terlalu besar.') : (json?.message ?? 'Dokumen tidak dapat diproses. Coba lagi.'));
    };
    xhr.onerror = () => {
        finish();
        showError('Koneksi terputus. Periksa jaringan lalu coba lagi.');
    };
    xhr.ontimeout = () => {
        finish();
        showError('Pembacaan dokumen terlalu lama. Coba dokumen yang lebih pendek.');
    };
    xhr.onabort = () => {
        finish();
        showError('Unggahan dibatalkan. Tidak ada yang tersimpan.');
    };

    bar.value = 0;
    label.textContent = 'Mengunggah… 0%';
    picked.hidden = true;
    progress.hidden = false;
    progress.setAttribute('aria-busy', 'true');
    $('[data-wi-drop]', root).classList.add('is-busy');
    xhr.send(body);
}

/* ------------------------------------------------------------------------------------ pratinjau */

function show(preview) {
    data = preview;
    data.sections = preview.sections.map((s) => ({ ...s, requirements: '', errors: {} }));
    sel = 0;
    root.dataset.wiPending = '1';
    $('[data-wi-step="upload"]', root).hidden = true;
    $('[data-wi-step="preview"]', root).hidden = false;

    const head = $('[data-dp-swap="head"] h1');
    const headP = $('[data-dp-swap="head"] header p');

    if (head) {
        head.textContent = 'Pratinjau Impor Word';
    }

    if (headP) {
        headText ??= headP.textContent;
        headP.textContent = 'Tinjau hasil pembacaan dan ubah bagian bila perlu. Belum ada yang tersimpan: template baru dibuat sebagai draf hanya setelah Anda menekan Simpan sebagai Draf.';
    }

    const t = preview.template;
    const meta = $('[data-wi-meta]', root);

    Object.entries({ name: t.name, document_type: t.document_type, type_label: t.type_label ?? '', description: t.description ?? '', language: t.language, citation_style: t.citation_style, qa_mode: t.qa_mode }).forEach(([key, value]) => {
        const field = meta.querySelector(`[name="${key}"]`);

        if (field) {
            field.value = value;
        }
    });
    meta.querySelector('[data-dp-type]')?.dispatchEvent(new Event('change', { bubbles: true }));

    const s = preview.stats;

    $('[data-wi-summary]', root).textContent = `${preview.source.filename} · ${size(preview.source.size)} · ${fmt(s.words)} kata terbaca · ${s.sections} bagian terdeteksi (${preview.strategy === 'heading' ? 'dari heading' : preview.strategy === 'numbered' ? 'dari daftar bernomor' : preview.strategy === 'pattern' ? 'dari baris bergaya judul' : 'satu prompt'}).`;
    $('[data-wi-warn-count]', root).textContent = `${preview.warnings.length} catatan`;
    $('[data-wi-warn-list]', root).replaceChildren(...preview.warnings.map((w) => h('li', {}, w)));
    $('[data-wi-source-text]', root).textContent = preview.source_text;
    $('[data-wi-source-info]', root).textContent = `· ${fmt(preview.source_text.length)} karakter`;

    list($('[data-wi-unassigned-list]', root), preview.unassigned, $('[data-wi-unassigned]', root));
    list($('[data-wi-citation-list]', root), preview.citation_notes, $('[data-wi-citation]', root));
    renderSpecifics();
    duplicate(preview.duplicate);
    renderAll();
    window.scrollTo({ top: 0 });
    $('#wi-preview-title', root).setAttribute('tabindex', '-1');
    $('#wi-preview-title', root).focus({ preventScroll: true });
}

function list(ul, items, wrapper) {
    wrapper.hidden = items.length === 0;
    ul.replaceChildren(...items.map((i) => h('li', {}, i)));
}

function duplicate(found) {
    const box = $('[data-wi-duplicate]', root);

    box.hidden = !found;

    if (found) {
        $('[data-wi-duplicate-text]', root).textContent = `Sudah ada template bernama "${found.name}". Impor ini tidak menimpanya: ganti nama di bawah, atau batalkan impor.`;
        $('[data-wi-duplicate-link]', root).setAttribute('href', found.url);
    }
}

function renderSpecifics() {
    const wrap = $('[data-wi-specifics]', root);
    const ul = $('[data-wi-specifics-list]', root);
    const vars = data.placeholders;

    wrap.hidden = data.specifics.length === 0;
    ul.replaceChildren(...data.specifics.map((phrase) => {
        const select = h('select', { class: 'bd-ix__select bd-ix__select--filter', 'aria-label': `Variabel pengganti untuk "${phrase}"` }, vars.map((v) => h('option', { value: v, selected: v === 'chapter_title' }, `{${v}}`)));
        const out = h('span', { class: 'bd-ix__sub', role: 'status' });

        return h('li', { class: 'bd-wi__spec' },
            h('q', {}, phrase),
            select,
            h('button', {
                type: 'button',
                class: 'bd-ix__btn bd-ix__btn--small',
                onclick: () => {
                    let n = 0;

                    data.sections.forEach((s) => {
                        const parts = s.prompt.split(phrase);

                        n += parts.length - 1;
                        s.prompt = parts.join(`{${select.value}}`);
                    });
                    out.textContent = n ? `${n} kemunculan diganti.` : 'Tidak ditemukan di prompt.';
                    renderPane();
                },
            }, 'Ganti di semua bagian'),
            out);
    }));
}

/* ----------------------------------------------------------------------------------------- editor */

function renderAll() {
    renderList();
    renderPane();
}

function renderList() {
    const ol = $('[data-wi-list]', root);

    $('[data-wi-count]', root).textContent = `· ${data.sections.length}`;
    ol.replaceChildren(...data.sections.map((s, i) => {
        const bad = Object.keys(s.errors).length > 0;

        return h('li', { class: `bd-dp__section ${i === sel ? 'is-selected' : ''}` },
            h('button', { type: 'button', class: 'bd-wi__pick', 'aria-current': i === sel ? 'true' : null, onclick: () => { sel = i; renderAll(); focusPane(); } },
                h('span', { class: 'bd-dp__num' }, String(i + 1).padStart(2, '0')),
                h('span', { class: 'bd-dp__section-text' },
                    h('strong', {}, s.title || '(tanpa nama)'),
                    h('span', { class: 'bd-ix__sub' }, [s.is_required ? 'Wajib' : 'Opsional', s.min_words ? `min ${fmt(s.min_words)}` : null, s.max_words ? `maks ${fmt(s.max_words)}` : null].filter(Boolean).join(' · ') + (s.min_words || s.max_words ? ' kata' : '')),
                    bad ? h('span', { class: 'bd-dp__ver is-none' }, 'Ada isian yang salah') : needsCheck(s) ? h('span', { class: 'bd-dp__ver is-draft' }, 'Perlu diperiksa') : h('span', { class: 'bd-dp__ver is-active' }, s.confidence === 'tinggi' ? 'Yakin' : 'Cukup yakin'))),
            h('div', { class: 'bd-dp__move' },
                h('button', { type: 'button', class: 'bd-ix__icon-btn bd-dp__move-btn bd-dp__move-btn--up', 'aria-label': `Naikkan ${s.title}`, disabled: i === 0, onclick: () => move(i, -1) }, chevron()),
                h('button', { type: 'button', class: 'bd-ix__icon-btn bd-dp__move-btn bd-dp__move-btn--down', 'aria-label': `Turunkan ${s.title}`, disabled: i === data.sections.length - 1, onclick: () => move(i, 1) }, chevron())));
    }));
}

function chevron() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');

    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    path.setAttribute('d', 'm6 9 6 6 6-6');
    svg.append(path);

    return svg;
}

function focusPane() {
    if (window.innerWidth < 1024) {
        $('[data-wi-pane]', root).scrollIntoView({ block: 'start' });
    }
}

function move(i, step) {
    const j = i + step;

    if (j < 0 || j >= data.sections.length) {
        return;
    }

    [data.sections[i], data.sections[j]] = [data.sections[j], data.sections[i]];
    sel = j;
    renderAll();
    $$(`[aria-label^="${step < 0 ? 'Naikkan' : 'Turunkan'} "]`, $('[data-wi-list]', root))[j]?.focus();
}

function field(label, id, input, error, hint, after) {
    return h('div', { class: 'bd-ix__field' },
        h('label', { class: 'bd-ix__label', for: id }, label),
        hint ? h('p', { class: 'bd-ix__hint' }, hint) : null,
        input,
        after ? h('p', { class: 'bd-ix__hint' }, after) : null,
        h('p', { class: 'bd-ix__note', 'data-error-for': id, 'aria-live': 'polite' }, error ?? ''));
}

function renderPane() {
    const pane = $('[data-wi-pane]', root);
    const s = data.sections[sel];

    if (!s) {
        pane.replaceChildren(h('div', { class: 'bd-ix__empty' }, h('h2', {}, 'Tidak ada bagian'), h('p', {}, 'Semua bagian sudah dihapus. Tambahkan bagian kosong atau batalkan impor.')));

        return;
    }

    const num = (key, id, label) => field(label, id, h('input', {
        class: 'bd-ix__input', id, type: 'number', min: '0', max: '200000', step: '1', inputmode: 'numeric', placeholder: 'Tanpa batas', value: s[key] ?? '',
        oninput: (event) => { s[key] = event.target.value === '' ? null : Number.parseInt(event.target.value, 10); delete s.errors[key]; renderList(); },
    }), s.errors[key], null, s.evidence?.[key] ? `Ditemukan: "${s.evidence[key]}"` : null);

    const area = h('textarea', {
        class: 'bd-ix__input bd-ix__textarea is-mono', id: 'wi-s-prompt', rows: '14', spellcheck: 'false', maxlength: String(data.limits.max_prompt), value: s.prompt,
        oninput: (event) => { s.prompt = event.target.value; delete s.errors.prompt; check(); },
    });
    const counter = h('span', { class: 'bd-ix__count', 'aria-hidden': 'true' });
    const note = h('p', { class: 'bd-ix__note', role: 'alert' }, s.errors.prompt ?? '');
    const check = () => {
        counter.textContent = `${fmt(words(s.prompt))} kata · ${fmt(s.prompt.length)} / ${fmt(data.limits.max_prompt)} karakter`;

        const known = new Set(data.placeholders);
        const unknown = [...new Set([...s.prompt.matchAll(/\{([A-Za-z_][A-Za-z0-9_]*)\}/g)].map((m) => m[1]))].filter((name) => !known.has(name));

        note.textContent = unknown.length ? `Placeholder tidak dikenal: {${unknown.join('}, {')}}. Ubah atau hapus sebelum menyimpan.` : (s.errors.prompt ?? '');
        area.setAttribute('aria-invalid', unknown.length || s.errors.prompt ? 'true' : 'false');
    };

    pane.replaceChildren(
        h('header', { class: 'bd-dp__pane-head' },
            h('h2', {}, s.title || '(tanpa nama)'),
            h('p', { class: 'bd-ix__muted' }, `Bagian ${sel + 1} dari ${data.sections.length}`)),

        needsCheck(s)
            ? h('div', { class: 'bd-wi__flags' },
                h('strong', {}, 'Perlu diperiksa'),
                s.flags.length ? h('ul', {}, s.flags.map((f) => h('li', {}, f))) : h('p', { class: 'bd-ix__muted' }, 'Bagian ini ditentukan dari struktur dokumen yang tidak sepenuhnya jelas.'))
            : null,

        h('div', { class: 'bd-ix__fields' },
            field('Nama bagian', 'wi-s-title', h('input', { class: 'bd-ix__input', id: 'wi-s-title', maxlength: '180', value: s.title, oninput: (event) => { s.title = event.target.value; delete s.errors.title; renderList(); } }), s.errors.title),

            h('div', { class: 'bd-ix__grid' },
                field('Peran bagian', 'wi-s-role', h('select', { class: 'bd-ix__input bd-ix__select', id: 'wi-s-role', onchange: (event) => { s.role = event.target.value; } }, Object.entries(ROLES).map(([v, l]) => h('option', { value: v, selected: s.role === v }, l)))),
                h('div', { class: 'bd-dp__toggles' },
                    h('label', { class: 'bd-dp__check' }, h('input', { type: 'checkbox', checked: s.is_required, onchange: (event) => { s.is_required = event.target.checked; renderList(); } }), ' Wajib'))),

            h('fieldset', { class: 'bd-dp__fieldset' },
                h('legend', {}, 'Jumlah kata'),
                h('div', { class: 'bd-dp__grid3' }, num('min_words', 'wi-s-min', 'Minimum'), num('max_words', 'wi-s-max', 'Maksimum'), num('target_words', 'wi-s-target', 'Target')),
                s.evidence?.item_count ? h('p', { class: 'bd-ix__hint' }, `Jumlah butir ditemukan: "${s.evidence.item_count}" (${s.item_count}).`) : null),

            field('Isi prompt', 'wi-s-prompt', h('div', { class: 'bd-wi__area' },
                h('div', { class: 'bd-dp__chips', role: 'group', 'aria-label': 'Variabel dinamis' }, data.placeholders.map((v) => h('button', {
                    type: 'button',
                    class: 'bd-dp__chip',
                    onclick: () => { area.focus(); area.setRangeText(`{${v}}`, area.selectionStart, area.selectionEnd, 'end'); area.dispatchEvent(new Event('input')); },
                }, `{${v}}`))),
                area,
                h('div', { class: 'bd-ix__under' }, note, counter)), null, 'Teks asli dari dokumen. Ubah seperlunya; variabel dinamis dikenali di dalam kurung kurawal.'),

            field('Persyaratan khusus (opsional, satu per baris)', 'wi-s-req', h('textarea', { class: 'bd-ix__input bd-ix__textarea', id: 'wi-s-req', rows: '3', maxlength: '3000', value: s.requirements, oninput: (event) => { s.requirements = event.target.value; } })),

            h('div', { class: 'bd-wi__ops' },
                h('button', { type: 'button', class: 'bd-ix__btn', onclick: split }, 'Pisahkan di kursor'),
                h('button', { type: 'button', class: 'bd-ix__btn', disabled: sel === data.sections.length - 1, onclick: merge }, 'Gabungkan dengan bagian berikutnya'),
                h('button', { type: 'button', class: 'bd-ix__btn bd-ix__btn--danger', disabled: data.sections.length === 1, onclick: remove }, 'Hapus bagian ini'))));

    check();
    // fokus kursor di textarea tetap milik admin; pembagian memakai posisi kursor terakhir
    area.addEventListener('keyup', () => { s.__caret = area.selectionStart; });
    area.addEventListener('click', () => { s.__caret = area.selectionStart; });
    area.addEventListener('blur', () => { s.__caret = area.selectionStart; });
}

function split() {
    const s = data.sections[sel];
    const caret = s.__caret ?? 0;
    const before = s.prompt.slice(0, caret).replace(/\s+$/, '');
    const after = s.prompt.slice(caret).replace(/^\s+/, '');

    if (before === '' || after === '') {
        notify('Letakkan kursor di tengah teks prompt (klik di antara dua kalimat) lalu tekan Pisahkan di kursor.', false);

        return;
    }

    s.prompt = before;
    s.__caret = 0;
    data.sections.splice(sel + 1, 0, { title: 'Bagian baru', prompt: after, role: 'body', is_required: true, min_words: null, max_words: null, target_words: null, item_count: null, requirements: '', confidence: 'rendah', flags: ['Hasil pemisahan: beri nama bagian ini dan periksa batas katanya.'], evidence: {}, errors: {} });
    sel += 1;
    renderAll();
    notify('Bagian dipisah. Beri nama bagian baru di bawah.');
    $('#wi-s-title', root)?.focus();
}

async function merge() {
    const a = data.sections[sel];
    const b = data.sections[sel + 1];

    if (!b) {
        return;
    }

    const ok = await confirmDialog({ title: 'Gabungkan dua bagian?', lines: [`"${b.title}" digabung ke bawah "${a.title}". Judulnya ditulis sebagai baris pembuka agar tidak ada teks yang hilang. Batas kata bagian kedua tidak ikut terbawa.`], ok: 'Gabungkan' });

    if (!ok) {
        return;
    }

    a.prompt = `${a.prompt}\n\n${b.title}:\n${b.prompt}`.trim();
    a.flags = [...a.flags, `Digabung dengan "${b.title}": periksa batas kata dan isinya.`];
    a.confidence = 'rendah';
    data.sections.splice(sel + 1, 1);
    renderAll();
}

async function remove() {
    const s = data.sections[sel];
    const ok = await confirmDialog({ title: 'Hapus bagian hasil deteksi?', lines: [`"${s.title}" dibuang dari hasil impor. Teks aslinya tetap ada di bagian "Teks asli hasil ekstraksi" dan dokumen Word Anda tidak berubah.`], ok: 'Hapus bagian', danger: true });

    if (!ok) {
        return;
    }

    data.sections.splice(sel, 1);
    sel = Math.min(sel, data.sections.length - 1);
    renderAll();
}

/* --------------------------------------------------------------------------------- simpan / batal */

function wireEditor() {
    $$('[data-wi-save]', root).forEach((button) => button.addEventListener('click', save));
    $$('[data-wi-discard]', root).forEach((button) => button.addEventListener('click', discard));
    $('[data-wi-add]', root).addEventListener('click', () => {
        data.sections.push({ title: 'Bagian baru', prompt: '', role: 'body', is_required: true, min_words: null, max_words: null, target_words: null, item_count: null, requirements: '', confidence: 'rendah', flags: ['Bagian kosong yang ditambahkan manual: tulis isi prompt-nya.'], evidence: {}, errors: {} });
        sel = data.sections.length - 1;
        renderAll();
        $('#wi-s-title', root)?.focus();
    });

    // Peringatan duplikat nama saat admin mengubah nama (debounce).
    $('[data-wi-meta]', root).addEventListener('input', (event) => {
        if (event.target.name !== 'name') {
            return;
        }

        clearTimeout(nameTimer);
        nameTimer = setTimeout(async () => {
            try {
                const response = await fetch(`${root.dataset.checkUrl}?name=${encodeURIComponent(event.target.value)}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const json = await response.json();

                duplicate(json.exists ? { name: json.name, url: json.url } : null);
            } catch {
                // pemeriksaan hanya bantuan; server tetap menolak nama ganda saat menyimpan
            }
        }, 400);
    });
}

function collect() {
    const meta = $('[data-wi-meta]', root);
    const value = (name) => meta.querySelector(`[name="${name}"]`)?.value ?? '';
    const int = (v) => (v === null || v === undefined || v === '' || Number.isNaN(v) ? null : v);

    return {
        template: { name: value('name'), document_type: value('document_type'), type_label: value('type_label') || null, description: value('description') || null, language: value('language'), citation_style: value('citation_style'), qa_mode: value('qa_mode') },
        sections: data.sections.map((s) => ({ title: s.title, prompt: s.prompt, role: s.role, is_required: s.is_required, min_words: int(s.min_words), max_words: int(s.max_words), target_words: int(s.target_words), item_count: int(s.item_count), requirements: s.requirements || null })),
        source: data.source,
    };
}

async function save() {
    const box = $('[data-wi-save-error]', root);
    const buttons = $$('[data-wi-save]', root);

    box.hidden = true;
    data.sections.forEach((s) => { s.errors = {}; });
    $$('[data-error-for]', $('[data-wi-meta]', root)).forEach((n) => { n.textContent = ''; });

    const payload = collect();
    const problems = [];

    if (!payload.template.name.trim()) {
        problems.push('Nama template wajib diisi.');
    }

    payload.sections.forEach((s, i) => {
        if (!s.title.trim()) {
            data.sections[i].errors.title = 'Nama bagian wajib diisi.';
        }

        if (!s.prompt.trim()) {
            data.sections[i].errors.prompt = 'Isi prompt bagian ini wajib diisi.';
        }
    });

    if (problems.length || data.sections.some((s) => Object.keys(s.errors).length)) {
        const first = data.sections.findIndex((s) => Object.keys(s.errors).length);

        box.hidden = false;
        box.textContent = problems[0] ?? 'Ada bagian yang belum lengkap. Periksa bagian bertanda merah.';

        if (first >= 0) {
            sel = first;
        }

        renderAll();

        return;
    }

    const flagged = data.sections.filter(needsCheck).length;
    const ok = await confirmDialog({
        title: 'Simpan sebagai draf?',
        lines: [`Template "${payload.template.name.trim()}" dibuat dengan ${payload.sections.length} bagian. Semuanya berstatus draf: tidak ada template, bagian, atau versi prompt yang diaktifkan.`, flagged ? `${flagged} bagian masih berlabel "Perlu diperiksa". Anda tetap bisa mengeditnya di editor setelah disimpan.` : 'Setelah disimpan Anda diarahkan ke editor template.'],
        ok: 'Simpan sebagai Draf',
    });

    if (!ok) {
        return;
    }

    buttons.forEach((b) => { b.disabled = true; });

    const result = await post(root.dataset.saveUrl, payload);

    buttons.forEach((b) => { b.disabled = false; });

    if (result.ok) {
        delete root.dataset.wiPending;
        root.dispatchEvent(new CustomEvent('dp:navigate', { bubbles: true, detail: { url: result.json.url, message: result.json.message } }));

        return;
    }

    box.hidden = false;
    box.textContent = result.message;

    Object.entries(result.json?.errors ?? {}).forEach(([key, messages]) => {
        const m = key.match(/^sections\.(\d+)\.(\w+)$/);
        const text = [].concat(messages)[0];

        if (m && data.sections[Number(m[1])]) {
            data.sections[Number(m[1])].errors[m[2]] = text;
        } else if (key.startsWith('template.')) {
            const note = $(`[data-wi-meta] [data-error-for="${key.slice(9)}"]`, root);

            if (note) {
                note.textContent = text;
            }
        }
    });

    if (result.json?.existing) {
        duplicate(result.json.existing);
    }

    const bad = data.sections.findIndex((s) => Object.keys(s.errors).length);

    if (bad >= 0) {
        sel = bad;
    }

    renderAll();
    box.scrollIntoView({ block: 'nearest' });
}

async function post(url, body) {
    try {
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            body: JSON.stringify(body),
        });
        const json = await response.json().catch(() => null);

        if (response.status === 419) {
            return { ok: false, json, message: 'Sesi berakhir. Muat ulang halaman lalu masuk lagi. Hasil tinjauan di halaman ini akan hilang, jadi salin teks penting lebih dulu.' };
        }

        return { ok: response.ok && json?.ok !== false, json, message: json?.message ?? (response.ok ? '' : 'Penyimpanan gagal. Coba lagi.') };
    } catch {
        return { ok: false, json: null, message: 'Koneksi terputus. Hasil tinjauan Anda masih ada di halaman ini; periksa jaringan lalu tekan Simpan lagi.' };
    }
}

async function discard() {
    const ok = await confirmDialog({ title: 'Batalkan impor?', lines: ['Hasil pembacaan dan semua perubahan yang Anda buat di halaman ini dibuang. Tidak ada yang tersimpan di server dan dokumen Word Anda tidak berubah.'], ok: 'Batalkan impor', cancel: 'Kembali ke peninjauan', danger: true });

    if (!ok) {
        return;
    }

    data = null;
    file = null;
    delete root.dataset.wiPending;
    $('[data-wi-step="preview"]', root).hidden = true;
    $('[data-wi-step="upload"]', root).hidden = false;
    $('[data-wi-picked]', root).hidden = true;
    showError(null);

    const head = $('[data-dp-swap="head"] h1');
    const headP = $('[data-dp-swap="head"] header p');

    if (head) {
        head.textContent = 'Impor dari Word';
    }

    if (headP && headText) {
        headP.textContent = headText;
    }

    $('[data-wi-drop]', root).focus();
}
