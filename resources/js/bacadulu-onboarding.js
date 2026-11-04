/** One-time research tour. No animation blocks first paint. */
const ready = (fn) => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', fn, { once: true }) : fn();

ready(() => {
    const tour = document.querySelector('[data-first-tour]');
    if (!tour) return;
    const title = tour.querySelector('[data-tour-title]');
    const copy = tour.querySelector('[data-tour-copy]');
    const count = tour.querySelector('[data-tour-count]');
    const progress = tour.querySelector('[data-tour-progress]');
    const next = tour.querySelector('[data-tour-next]');
    const back = tour.querySelector('[data-tour-back]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    // Cara memulai (topik + Research Tool) diajarkan langsung di halaman New Research;
    // tour ini hanya menunjukkan letak fitur lain.
    const steps = [
        ['Research baru kapan saja', 'Tekan tombol oranye + di sidebar untuk membuka research baru. Riwayatmu tersimpan di sana.'],
        ['Periksa evidence', 'Baca jawaban dan sumber. Jika bukti kurang, perjelas topik terlebih dahulu.'],
        ['Simpan dan buat dokumen', 'Simpan hasil ke Saved, kemudian lanjutkan ke editor dokumen untuk PDF/DOCX.'],
    ];
    let current = 0;
    let closing = false;
    const render = () => {
        title.textContent = steps[current][0];
        copy.textContent = steps[current][1];
        count.textContent = `${String(current + 1).padStart(2, '0')} / ${String(steps.length).padStart(2, '0')}`;
        progress.style.width = `${(current + 1) / steps.length * 100}%`;
        back.disabled = current === 0;
        next.textContent = current === steps.length - 1 ? 'Selesai ✓' : 'Berikutnya →';
    };
    const finish = async () => {
        if (closing) return;
        closing = true;
        next.disabled = true;
        try {
            const response = await fetch(tour.dataset.completeUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error('Gagal menyimpan status panduan');
            tour.remove();
        } catch {
            closing = false;
            next.disabled = false;
            const message = tour.querySelector('[data-tour-copy]');
            if (message) message.textContent = 'Koneksi bermasalah. Coba lagi agar panduan tidak tampil ulang.';
        }
    };
    tour.querySelector('[data-tour-skip]')?.addEventListener('click', finish);
    next?.addEventListener('click', () => {
        if (current === steps.length - 1) { finish(); return; }
        current += 1; render();
    });
    back?.addEventListener('click', () => { current = Math.max(0, current - 1); render(); });
    render();
});
