/*
 * Full-Text Sumber Eksternal (admin/external-sources/fulltext). Dimuat sesuai kebutuhan oleh admin.js bila ada [data-ft].
 * Tanpa JS semua form tetap bekerja; JS hanya meminta konfirmasi untuk tindakan yang menghapus data (CSP melarang onsubmit inline).
 * Semua aturan (CSRF, otorisasi, izin, batas) diputuskan server.
 */
export function initAdminExternalFulltext() {
    const root = document.querySelector('[data-ft]');
    if (!root) {
        return;
    }

    root.addEventListener('submit', (event) => {
        const form = event.target.closest?.('form');
        const message = form?.dataset.ftConfirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }

        // Cegah klik ganda pada tombol kirim.
        form?.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = true; });
    });
}
