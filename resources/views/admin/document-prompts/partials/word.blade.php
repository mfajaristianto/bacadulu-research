@php
    $p = 'wi';
    $t = null;
    $maxBytes = \App\Services\Research\WordDocumentReader::MAX_FILE_BYTES;
    $maxMb = rtrim(rtrim(number_format($maxBytes / 1048576, 1, ',', '.'), '0'), ',');
@endphp
<div class="bd-wi" data-dp-word
    data-parse-url="{{ route('admin.prompt-documents.word.parse') }}"
    data-save-url="{{ route('admin.prompt-documents.word.save') }}"
    data-check-url="{{ route('admin.prompt-documents.word.check-name') }}"
    data-max-bytes="{{ $maxBytes }}">

    {{-- Langkah 1: unggah --}}
    <section class="bd-wi__upload" data-wi-step="upload" aria-labelledby="wi-upload-title">
        <h2 id="wi-upload-title" class="bd-ix__sr">Unggah dokumen Word</h2>

        <div class="bd-wi__drop" data-wi-drop tabindex="0" role="button" aria-describedby="wi-drop-hint" aria-label="Area unggah. Tekan Enter atau Spasi untuk memilih berkas Word.">
            @include('admin.tools.partials.icon', ['name' => 'upload'])
            <p class="bd-wi__drop-title"><strong>Seret berkas .docx ke sini</strong> <span>atau</span> <button type="button" class="bd-ix__btn bd-ix__btn--small" data-wi-browse>Telusuri file</button></p>
            <p class="bd-ix__muted" id="wi-drop-hint">Hanya .docx, maksimal {{ $maxMb }} MB. Berkas dibaca di server untuk menyusun usulan dan <strong>tidak disimpan</strong>.</p>
            <input type="file" class="bd-ix__sr" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" data-wi-file tabindex="-1" aria-hidden="true">
        </div>

        <div class="bd-wi__picked" data-wi-picked hidden>
            <div class="bd-wi__file">
                <span class="bd-wi__file-icon" aria-hidden="true">@include('admin.tools.partials.icon', ['name' => 'check'])</span>
                <div><strong data-wi-name></strong><span class="bd-ix__sub" data-wi-size></span></div>
            </div>
            <div class="bd-wi__picked-actions">
                <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-wi-cancel>Batal</button>
                <button type="button" class="bd-ix__btn bd-ix__btn--primary" data-wi-read>Baca dokumen</button>
            </div>
        </div>

        <div class="bd-wi__progress" data-wi-progress hidden role="status" aria-live="polite">
            <p data-wi-progress-text>Mengunggah…</p>
            <progress max="100" value="0" data-wi-bar aria-label="Kemajuan unggah"></progress>
            <button type="button" class="bd-ix__btn bd-ix__btn--quiet bd-ix__btn--small" data-wi-abort>Batalkan unggahan</button>
        </div>

        <p class="bd-ix__alert" data-wi-error role="alert" hidden></p>

        <div class="bd-wi__notes">
            <div>
                <h3 class="bd-ix__side-title">Yang dibaca</h3>
                <p class="bd-ix__muted">Judul, heading, paragraf, daftar bernomor dan bullet, serta tabel sederhana, sesuai urutan. Redaksi asli dipertahankan, tidak diringkas.</p>
            </div>
            <div>
                <h3 class="bd-ix__side-title">Yang tidak dibaca atau ditolak</h3>
                <p class="bd-ix__muted">Gambar, kotak teks, catatan kaki, header/footer, dan tautan tidak diikuti. Berkas .doc lama, PDF, dan dokumen berisi makro ditolak.</p>
            </div>
            <div>
                <h3 class="bd-ix__side-title">Setelah dibaca</h3>
                <p class="bd-ix__muted">Anda meninjau dan mengubah hasilnya lebih dulu. Tidak ada yang tersimpan atau aktif sampai Anda menekan Simpan sebagai Draf.</p>
            </div>
        </div>
    </section>

    {{-- Langkah 2: pratinjau dan editor (diisi JS dari hasil parse) --}}
    <section class="bd-wi__preview" data-wi-step="preview" hidden aria-labelledby="wi-preview-title">
        <header class="bd-wi__preview-head">
            <div>
                <h2 id="wi-preview-title" class="bd-wi__h2">Hasil pembacaan</h2>
                <p class="bd-ix__muted" data-wi-summary></p>
            </div>
        </header>

        <p class="bd-ix__alert" data-wi-save-error role="alert" hidden></p>

        <details class="bd-dp__issues" data-wi-warnings open>
            <summary><span class="bd-ix__warn" data-wi-warn-count></span> <span class="bd-ix__sub">hasil pembacaan yang perlu Anda ketahui</span></summary>
            <ul data-wi-warn-list></ul>
        </details>

        <p class="bd-ix__lock" data-wi-duplicate hidden>@include('admin.tools.partials.icon', ['name' => 'lock']) <span data-wi-duplicate-text></span> <a class="bd-ix__link" data-wi-duplicate-link target="_blank" rel="noopener">Buka template yang ada</a></p>

        <section aria-labelledby="wi-meta-title" class="bd-wi__meta">
            <h3 id="wi-meta-title" class="bd-ix__side-title">Informasi template</h3>
            <div data-wi-meta>
                @include('admin.document-prompts.partials.template-fields')
            </div>
        </section>

        <section class="bd-wi__edit" aria-labelledby="wi-edit-title">
            <h3 id="wi-edit-title" class="bd-ix__side-title">Bagian terdeteksi <span class="bd-ix__sub" data-wi-count></span></h3>
            <div class="bd-dp__workspace bd-wi__workspace">
                <nav class="bd-dp__sections" aria-label="Bagian hasil deteksi">
                    <ol class="bd-dp__section-list" data-wi-list></ol>
                    <button type="button" class="bd-ix__btn bd-ix__btn--small bd-wi__add" data-wi-add>@include('admin.tools.partials.icon', ['name' => 'plus']) Tambah bagian kosong</button>
                </nav>
                <div class="bd-dp__pane bd-wi__pane" data-wi-pane tabindex="-1"></div>
            </div>
        </section>

        <section class="bd-wi__specifics" data-wi-specifics hidden aria-labelledby="wi-spec-title">
            <h3 id="wi-spec-title" class="bd-ix__side-title">Frasa yang mungkin khusus topik</h3>
            <p class="bd-ix__muted">Frasa tebal ini muncul di dokumen. Bila itu judul atau topik contoh, ganti dengan variabel agar template bisa dipakai untuk topik lain. Tidak ada yang diganti otomatis.</p>
            <ul data-wi-specifics-list></ul>
        </section>

        <section class="bd-wi__unassigned" data-wi-unassigned hidden aria-labelledby="wi-un-title">
            <h3 id="wi-un-title" class="bd-ix__side-title">Teks yang belum masuk bagian mana pun</h3>
            <ul data-wi-unassigned-list></ul>
        </section>

        <section class="bd-wi__citation" data-wi-citation hidden aria-labelledby="wi-cit-title">
            <h3 id="wi-cit-title" class="bd-ix__side-title">Ketentuan sitasi yang ditemukan</h3>
            <ul data-wi-citation-list></ul>
        </section>

        <details class="bd-wi__source" data-wi-source>
            <summary class="bd-ix__group-title">Teks asli hasil ekstraksi <span class="bd-ix__sub" data-wi-source-info></span></summary>
            <p class="bd-ix__muted">Urutan dan isinya persis seperti terbaca dari dokumen (heading diberi #, butir daftar diberi penanda). Bandingkan dengan pembagian bagian di atas.</p>
            <pre class="bd-ix__prompt bd-wi__source-text" tabindex="0" data-wi-source-text></pre>
        </details>

        <section class="bd-wi__ai" aria-labelledby="wi-ai-title">
            <h3 id="wi-ai-title" class="bd-ix__side-title">Bantu kelompokkan dengan AI</h3>
            <button type="button" class="bd-ix__btn" disabled aria-describedby="wi-ai-note">Bantu Kelompokkan dengan AI</button>
            <p class="bd-ix__muted" id="wi-ai-note">Belum tersedia pada versi ini. Pengelompokan di atas lokal dan deterministik: dokumen tidak dikirim ke penyedia luar dan AI tidak pernah dipanggil otomatis.</p>
        </section>

        <div class="bd-ix__actions bd-wi__bottom">
            <button type="button" class="bd-ix__btn bd-ix__btn--quiet" data-wi-discard>Batal impor</button>
            <button type="button" class="bd-ix__btn bd-ix__btn--primary" data-wi-save>Simpan sebagai Draf</button>
        </div>
    </section>
</div>
