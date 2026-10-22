/** Registration handoff and one-time research tour. No animation blocks first paint. */
const ready = (fn) => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', fn, { once: true }) : fn();

ready(() => {
    const success = document.querySelector('[data-register-success]');
    if (success) {
        const destination = success.dataset.target;
        const counter = success.querySelector('[data-welcome-seconds]');
        let remaining = 5;
        const timer = window.setInterval(() => {
            remaining -= 1;
            if (counter) counter.textContent = String(Math.max(0, remaining));
            if (remaining <= 0) {
                window.clearInterval(timer);
                if (destination) window.location.assign(destination);
            }
        }, 1000);
        success.querySelector('a[href]')?.addEventListener('click', () => window.clearInterval(timer));
    }

    const tour = document.querySelector('[data-first-tour]');
    if (!tour) return;
    const title = tour.querySelector('[data-tour-title]');
    const copy = tour.querySelector('[data-tour-copy]');
    const count = tour.querySelector('[data-tour-count]');
    const progress = tour.querySelector('[data-tour-progress]');
    const next = tour.querySelector('[data-tour-next]');
    const back = tour.querySelector('[data-tour-back]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const steps = [
        ['Mulai New Research', 'Tekan tombol oranye + untuk memulai penelitian baru.'],
        ['Pilih Research Tool', 'Pilih prompt yang paling sesuai agar pencarian terarah.'],
        ['Periksa evidence', 'Baca jawaban dan sumber. Jika bukti kurang, perjelas topik terlebih dahulu.'],
        ['Simpan dan buat dokumen', 'Simpan hasil ke Saved, kemudian lanjutkan ke editor dokumen untuk PDF/DOCX.'],
    ];
    let current = 0;
    let closing = false;
    const render = () => {
        title.textContent = steps[current][0];
        copy.textContent = steps[current][1];
        count.textContent = `${String(current + 1).padStart(2, '0')} / 04`;
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
