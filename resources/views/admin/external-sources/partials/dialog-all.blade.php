{{-- Mode "semua kategori terpetakan": satu run per pemetaan valid, BERURUTAN dalam satu grup; total dibatasi batas kampanye. --}}
<dialog class="bd-ext__dialog bd-ext__dialog--wide" data-ext-dialog="sync-all" aria-labelledby="ext-all-title" data-limit="{{ $limits['campaign'] }}">
    <form method="POST" action="{{ route('admin.external-sources.sync-all') }}" data-ext-all-form>
        @csrf
        <header><h2 id="ext-all-title">Semua kategori terpetakan</h2><button type="button" class="bd-ext__x" data-ext-close aria-label="Tutup">×</button></header>
        <p class="bd-ext__hint">Satu run per pemetaan yang valid, dijalankan <strong>berurutan</strong> (bukan serentak), masing-masing dengan batas dan anggarannya sendiri. Bukan mengambil seluruh internet: hanya penyedia aktif dengan pemetaan. Total rekaman seluruh kampanye dibatasi {{ number_format($limits['campaign'], 0, ',', '.') }}.</p>
        <div class="bd-ext__grid">
            <label>Penyedia
                <select name="provider" data-ext-all-provider><option value="all">Semua penyedia aktif</option>@foreach ($implemented as $c)<option value="{{ $c['code'] }}">{{ $c['name'] }}</option>@endforeach</select>
            </label>
            <label>Kategori
                <select name="category_id" data-ext-all-cat><option value="">Semua kategori yang punya pemetaan</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select>
            </label>
            <label>Tahun dari <input type="number" name="year_from" min="1000" max="2200"></label>
            <label>Tahun sampai <input type="number" name="year_to" min="1000" max="2200"></label>
            <label>Batas per run (menimpa pemetaan) <input type="number" name="max_records" min="1" max="{{ $limits['campaign'] }}" placeholder="ikut pemetaan" data-ext-all-max></label>
            <label>Batas total kampanye (wajib; dipaksakan server) <input type="number" name="max_total" min="1" max="{{ $limits['campaign'] }}" required placeholder="mis. 30" data-ext-all-total></label>
            <label>Maks. permintaan per run <input type="number" name="max_requests" min="1" max="{{ $limits['requests'] }}" placeholder="otomatis dari target" data-ext-all-req></label>
            <label>Maks. durasi per run (menit) <input type="number" name="max_minutes" min="1" max="{{ $limits['minutes'] }}" placeholder="{{ $limits['minutes'] }}" data-ext-all-minutes></label>
        </div>
        <ol class="bd-ext__plan" data-ext-plan aria-live="polite"></ol>
        <p class="bd-ext__summary" data-ext-all-summary aria-live="polite"></p>
        <p class="bd-ext__hint">Biaya: tidak ada API berbayar yang dipanggil. OpenAlex tanpa key memakai anggaran gratis harian yang kecil dan dibagi per IP.</p>
        <footer><button type="button" class="bd-ext__btn bd-ext__btn--quiet" data-ext-close>Batal</button><button type="submit" class="bd-ext__btn bd-ext__btn--primary" data-ext-all-submit>Mulai kampanye</button></footer>
    </form>
</dialog>
