{{-- Dialog Preview/Sync berbasis MODE. Mode yang tidak didukung penyedia dinonaktifkan dengan alasan jujur (dari deklarasi adapter). --}}
<dialog class="bd-ext__dialog bd-ext__dialog--wide" data-ext-dialog="sync" aria-labelledby="ext-sync-title">
    <form method="POST" data-ext-sync-form data-limits='@json($limits)'>
        @csrf
        <header><h2 id="ext-sync-title"><span data-ext-mode-title>Sinkronisasi</span> · <span data-ext-name></span></h2><button type="button" class="bd-ext__x" data-ext-close aria-label="Tutup">×</button></header>

        <fieldset class="bd-ext__modes" data-ext-modes>
            <legend>Mode sinkronisasi</legend>
            @foreach ([
                ['quick', 'Quick', 'Verifikasi awal, maks '.$limits['quick'].' rekaman.'],
                ['category', 'Per kategori', 'Pencarian berbasis istilah untuk satu kategori (urutan relevansi).'],
                ['corpus', 'Korpus penyedia', 'Korpus per subjek lewat jalur bulk resmi penyedia.'],
                ['incremental', 'Incremental', 'Perubahan sejak tanggal tertentu lewat fitur resmi penyedia.'],
            ] as [$val, $label, $desc])
                <label class="bd-ext__mode" data-ext-mode="{{ $val }}">
                    <input type="radio" name="mode" value="{{ $val }}" @checked($val === 'category')>
                    <span><b>{{ $label }}</b><small>{{ $desc }}</small></span>
                </label>
            @endforeach
        </fieldset>
        <p class="bd-ext__hint" data-ext-mode-note aria-live="polite"></p>

        <div class="bd-ext__grid">
            <label data-ext-cat-row>Kategori BacaDulu <span class="bd-ext__muted" data-ext-cat-hint></span>
                <select name="category_id" data-ext-cat><option value="">Pilih kategori</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select>
            </label>
            <label data-ext-type-row>Jenis dokumen
                <select name="type" data-ext-type><option value="">Semua yang didukung</option></select>
            </label>
            <label class="bd-ext__wide" data-ext-terms-row>Istilah pencarian
                <textarea name="terms" rows="2" maxlength="1000" data-ext-terms placeholder="Istilah dipisah koma atau baris baru. Diisi otomatis dari pemetaan bila ada."></textarea>
            </label>

            <div class="bd-ext__wide bd-ext__setpick" data-ext-set-row hidden>
                <label>Subjek (set LCC DOAJ)
                    <span class="bd-ext__inline"><input type="search" data-ext-set-q placeholder="mis. accounting" maxlength="60" autocomplete="off"><button type="button" class="bd-ext__btn bd-ext__btn--small" data-ext-set-search>Cari subjek</button></span>
                </label>
                <select name="set" data-ext-set aria-label="Subjek terpilih"><option value="">Belum ada subjek dipilih</option></select>
                <input type="hidden" name="set_label" data-ext-set-label>
                <small class="bd-ext__hint" data-ext-set-note>Seluruh DOAJ tidak diambil otomatis: korpus selalu per subjek dan dibatasi batas kampanye.</small>
            </div>

            <label data-ext-since-row hidden>Perubahan sejak (tanggal)
                <input type="date" name="since" data-ext-since max="{{ now()->toDateString() }}">
                <small data-ext-since-note></small>
            </label>

            <label data-ext-yf-row>Tahun dari <input type="number" name="year_from" min="1000" max="2200" data-ext-yf></label>
            <label data-ext-yt-row>Tahun sampai <input type="number" name="year_to" min="1000" max="2200" data-ext-yt></label>
            <label data-ext-lang-row>Bahasa (kode ISO, mis. id, en) <input type="text" name="language" maxlength="3" data-ext-lang></label>
            <label class="bd-ext__check" data-ext-oa-row><input type="checkbox" name="open_access_only" value="1" data-ext-oa> Hanya open access</label>

            <label>Maks. rekaman <span class="bd-ext__muted" data-ext-max-hint></span>
                <input type="number" name="max_records" min="1" max="{{ $limits['campaign'] }}" value="{{ config('external-sources.limits.default_max_records') }}" data-ext-max>
                <span class="bd-ext__tiers" role="group" aria-label="Jenjang jumlah rekaman">
                    @foreach ([100, 1000, $limits['campaign']] as $tier)<button type="button" class="bd-ext__tier" data-ext-tier="{{ $tier }}">{{ number_format($tier, 0, ',', '.') }}</button>@endforeach
                </span>
            </label>
            <details class="bd-ext__wide bd-ext__budget">
                <summary>Anggaran & batas (opsional, ada bawaan konservatif)</summary>
                <div class="bd-ext__grid">
                    <label>Maks. permintaan ke penyedia <input type="number" name="max_requests" min="1" max="{{ $limits['requests'] }}" placeholder="otomatis dari target" data-ext-req></label>
                    <label>Maks. durasi aktif (menit) <input type="number" name="max_minutes" min="1" max="{{ $limits['minutes'] }}" placeholder="{{ $limits['minutes'] }}" data-ext-minutes></label>
                </div>
                <p class="bd-ext__hint">Anggaran habis = berhenti “sebagian” dan bisa dilanjutkan (jendela anggaran baru); jumlah rekaman total tidak direset. Batas keras server: {{ number_format($limits['requests'], 0, ',', '.') }} permintaan, {{ $limits['minutes'] }} menit, {{ number_format($limits['campaign'], 0, ',', '.') }} rekaman. Tidak ada mode tanpa batas.</p>
            </details>
            <label class="bd-ext__check bd-ext__wide" data-ext-save-row><input type="checkbox" name="save_mapping" value="1"> Simpan cakupan ini sebagai pemetaan kategori</label>
        </div>

        <p class="bd-ext__summary" data-ext-summary aria-live="polite"></p>
        <p class="bd-ext__hint" data-ext-unsupported></p>
        <div class="bd-ext__preview" data-ext-preview aria-live="polite" hidden></div>
        <footer>
            <button type="button" class="bd-ext__btn bd-ext__btn--quiet" data-ext-close>Tutup</button>
            <button type="button" class="bd-ext__btn" data-ext-do-preview>Preview data</button>
            <button type="submit" class="bd-ext__btn bd-ext__btn--primary" data-ext-do-sync>Mulai Sinkronisasi</button>
        </footer>
    </form>
</dialog>
