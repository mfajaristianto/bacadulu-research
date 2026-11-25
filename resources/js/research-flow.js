/**
 * Alur produk: Mulai Riset -> Hasil -> Generate Document -> Dokumen Saya.
 * Semua halaman tetap berfungsi tanpa JavaScript; skrip ini hanya penyempurna.
 */
const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn, { once: true }) : fn());
const reduce = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function initForm() {
    const form = document.querySelector('[data-rf-form]');
    if (!form) return;

    // Jenis input dibaca dari alat riset terpilih di menu Workspace (app.js initPromptPicker memperbarui aria-selected).
    const menu = form.querySelector('[data-prompt-menu]');
    const book = form.querySelector('[data-rf-book]');
    const count = form.querySelector('[data-rf-count]');
    const rows = [...form.querySelectorAll('[data-rf-q]')];

    const syncRows = () => {
        const n = Number(count?.value || 1);
        const bookOn = book && !book.hidden;
        rows.forEach((row, i) => {
            const on = bookOn && i < n;
            row.hidden = !on;
            const input = row.querySelector('input');
            input.disabled = !on;
            input.required = on; // semua Rumusan Masalah yang ditampilkan wajib diisi
        });
    };

    const syncTool = () => {
        const selected = menu?.querySelector('[data-prompt-id][aria-selected="true"]');
        if (book) book.hidden = selected?.dataset.promptMode !== 'book';
        syncRows();
    };

    if (menu) {
        new MutationObserver(syncTool).observe(menu, { subtree: true, attributes: true, attributeFilter: ['aria-selected'] });
    }
    count?.addEventListener('change', syncRows);
    syncTool();
}

function initBusy() {
    document.querySelectorAll('[data-rf-form], [data-rf-generate]').forEach((form) => {
        form.addEventListener('submit', () => {
            // Cegah kirim ganda: pencarian luas dan Generate Document bisa memakan beberapa detik.
            const btn = form.querySelector('[data-rf-submit]');
            const busy = form.querySelector('[data-rf-busy]');
            if (!form.checkValidity?.()) return;
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('aria-disabled', 'true');
            }
            if (busy) busy.hidden = false;
        });
    });
}

function initPoll() {
    const el = document.querySelector('[data-rf-poll]');
    if (el) setTimeout(() => window.location.reload(), Number(el.dataset.rfPoll) || 3000);
}

function initAutoDownload() {
    const link = document.querySelector('[data-rf-auto]');
    if (!link) return;
    // Sekali per dokumen per sesi tab. Server hanya tahu endpoint unduh dipanggil, bukan berkas tersimpan di perangkat.
    const key = `rf-auto-${link.dataset.rfAuto}`;
    try {
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
    } catch { /* penyimpanan diblokir: tetap satu kali per muat halaman */ }
    window.location.assign(link.href);
}

function initFocus() {
    const target = document.querySelector('[data-doc-focus]');
    if (!target) return;
    target.setAttribute('tabindex', '-1');
    target.scrollIntoView({ block: 'center', behavior: reduce() ? 'auto' : 'smooth' });
    target.focus({ preventScroll: true });
    target.classList.add('is-focused');
    setTimeout(() => target.classList.remove('is-focused'), 2600);
}

// Form riset memakai GET: saat kembali dari halaman hasil (bfcache), tombol kirim tidak boleh tetap nonaktif.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('[data-rf-form]').forEach((form) => {
        delete form.dataset.submitting;
        form.classList.remove('is-submitting');
        form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); });
    });
});

ready(() => {
    initForm();
    initBusy();
    initPoll();
    initAutoDownload();
    initFocus();
});
