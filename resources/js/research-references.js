// Referensi: pilihan sumber untuk analisis, dan pengungkapan halus. Semua fungsi inti bekerja tanpa JS (form GET/POST biasa).
(() => {
    const root = document.querySelector('[data-refs]');
    if (!root) return;

    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Pengungkapan: sembunyikan hanya bila JS aktif dan gerak diizinkan; tanpa observer, konten tetap terlihat.
    // Elemen yang sudah berada di layar awal (atau di atasnya) ditampilkan langsung SEBELUM kelas animasi dipasang:
    // tidak ada celah kosong di layar pertama dan tidak ada kedipan. Hanya yang di bawah lipatan yang diungkap saat discroll.
    const reveal = [...root.querySelectorAll('[data-refs-reveal]')];
    if (!reduce && 'IntersectionObserver' in window && reveal.length) {
        const fold = window.innerHeight;
        const below = reveal.filter((el) => {
            if (el.getBoundingClientRect().top < fold) {
                el.classList.add('is-in');
                return false;
            }
            return true;
        });
        root.classList.add('refs--anim');
        // threshold 0: item yang tinggi tampil begitu bagian atasnya masuk (rasio persentase membuatnya tertahan di layar ponsel).
        const io = new IntersectionObserver((entries) => {
            entries.forEach((e, i) => {
                if (!e.isIntersecting) return;
                e.target.style.transitionDelay = `${Math.min(i, 4) * 60}ms`;
                e.target.classList.add('is-in');
                io.unobserve(e.target);
            });
        }, { rootMargin: '0px 0px -4% 0px', threshold: 0 });
        below.forEach((el) => io.observe(el));
    }

    // Pencarian yang luas bisa beberapa detik: tampilkan keadaan "sedang mencari" yang jujur (tanpa persentase atau hasil sementara palsu).
    const search = root.querySelector('[data-refs-search]');
    if (search) {
        const go = search.querySelector('button[type="submit"]');
        const busy = search.querySelector('[data-refs-busy]');
        const label = go ? go.textContent : '';
        const reset = () => {
            root.removeAttribute('aria-busy');
            if (go) { go.disabled = false; go.textContent = label; }
            if (busy) busy.hidden = true;
        };
        search.addEventListener('submit', () => {
            root.setAttribute('aria-busy', 'true');
            if (go) { go.textContent = 'Mencari…'; go.disabled = true; }
            if (busy) busy.hidden = false;
        });
        window.addEventListener('pageshow', reset); // kembali lewat riwayat peramban
    }

    // Pilihan sumber: maksimum 10 (sama dengan batas server), tombol AI aktif hanya bila ada pilihan dan layanan tersedia.
    const form = root.querySelector('[data-refs-ai]');
    if (!form) return;
    const boxes = [...root.querySelectorAll('[data-refs-select]')];
    const submit = form.querySelector('[data-refs-ai-submit]');
    const count = form.querySelector('[data-refs-count]');
    const hint = form.querySelector('[data-refs-hint]');
    const available = form.dataset.aiAvailable === '1';
    const MAX = 10;

    const sync = () => {
        const n = boxes.filter((b) => b.checked).length;
        boxes.forEach((b) => { b.disabled = !b.checked && n >= MAX; });
        if (count) count.textContent = n ? `(${n})` : '';
        if (available && submit) submit.disabled = n === 0;
        if (hint) hint.textContent = n ? `${n} referensi dipilih. Anda akan diminta konfirmasi sebelum AI berjalan.` : 'Pilih minimal satu referensi.';
    };
    boxes.forEach((b) => b.addEventListener('change', sync));
    sync();
})();

// Konfirmasi analisis AI: tampilkan keadaan "memproses" yang jujur dan cegah kirim ganda (tanpa JS formulir tetap berfungsi).
(() => {
    const form = document.querySelector('[data-synth-form]');
    if (!form) return;
    const btn = form.querySelector('[data-synth-submit]');
    const busy = document.querySelector('[data-synth-busy]');
    const label = btn ? btn.textContent : '';
    form.addEventListener('submit', () => {
        if (btn) { btn.disabled = true; btn.textContent = 'Memproses…'; }
        if (busy) busy.hidden = false;
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        if (btn) { btn.disabled = false; btn.textContent = label; }
        if (busy) busy.hidden = true;
        form.removeAttribute('aria-busy');
    });
})();
