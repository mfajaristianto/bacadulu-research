@php
    $name = fn (string $path) => 'layout['.str_replace('.', '][', $path).']';
    $id = fn (string $path) => 'pf-'.str_replace('.', '-', $path);
    $err = fn (string $path) => '<p class="bd-ix__note" data-error-for="layout.'.$path.'"></p>';

    // Angka biasa (poin, kali, jumlah).
    $num = function (string $path, string $label, array $o = []) use ($layout, $name, $id, $err): \Illuminate\Support\HtmlString {
        $v = data_get($layout, $path);

        return new \Illuminate\Support\HtmlString('<div class="bd-ix__field"><label class="bd-ix__label" for="'.$id($path).'">'.e($label).(isset($o['unit']) ? ' <small>'.e($o['unit']).'</small>' : '').'</label>'
            .'<input class="bd-ix__input" type="number" id="'.$id($path).'" name="'.$name($path).'" value="'.e($v).'" step="'.($o['step'] ?? '0.1').'" min="'.($o['min'] ?? 0).'" max="'.($o['max'] ?? 999).'" inputmode="decimal" data-dp-field data-pf-field>'.$err($path).'</div>');
    };

    // Panjang: ditampilkan dalam satuan terpilih, disimpan mm. data-mm membawa nilai kanonik untuk konversi satuan.
    $length = function (string $path, string $label) use ($layout, $unit, $name, $id, $err): \Illuminate\Support\HtmlString {
        $mmv = (float) data_get($layout, $path);
        $shown = \App\Services\Documents\PrintLayout::fromMm($mmv, $unit);

        return new \Illuminate\Support\HtmlString('<div class="bd-ix__field"><label class="bd-ix__label" for="'.$id($path).'">'.e($label).' <small data-pf-unit>'.e($unit).'</small></label>'
            .'<input class="bd-ix__input" type="number" id="'.$id($path).'" name="'.$name($path).'" value="'.e($shown).'" step="any" min="0" inputmode="decimal" data-dp-field data-pf-field data-pf-len data-mm="'.e($mmv).'">'.$err($path).'</div>');
    };

    $sel = function (string $path, string $label, array $options, ?string $hint = null) use ($layout, $name, $id, $err): \Illuminate\Support\HtmlString {
        $cur = (string) data_get($layout, $path);
        $opts = collect($options)->map(fn ($text, $value) => '<option value="'.e($value).'"'.((string) $value === $cur ? ' selected' : '').'>'.e($text).'</option>')->implode('');

        return new \Illuminate\Support\HtmlString('<div class="bd-ix__field"><label class="bd-ix__label" for="'.$id($path).'">'.e($label).'</label><select class="bd-ix__input bd-ix__select" id="'.$id($path).'" name="'.$name($path).'" data-dp-field data-pf-field'.($hint ? ' aria-describedby="'.$id($path).'-h"' : '').'>'.$opts.'</select>'.($hint ? '<p class="bd-ix__hint" id="'.$id($path).'-h">'.e($hint).'</p>' : '').$err($path).'</div>');
    };

    $chk = function (string $path, string $label, ?string $hint = null) use ($layout, $name, $id): \Illuminate\Support\HtmlString {
        $on = (bool) data_get($layout, $path);

        return new \Illuminate\Support\HtmlString('<label class="bd-dp__check"><input type="hidden" name="'.$name($path).'" value="0"><input type="checkbox" id="'.$id($path).'" name="'.$name($path).'" value="1" data-dp-field data-pf-field'.($on ? ' checked' : '').'> '.e($label).'</label>'.($hint ? '<p class="bd-ix__hint">'.e($hint).'</p>' : ''));
    };

    $txt = function (string $path, string $label, string $placeholder = '') use ($layout, $name, $id, $err): \Illuminate\Support\HtmlString {
        return new \Illuminate\Support\HtmlString('<div class="bd-ix__field"><label class="bd-ix__label" for="'.$id($path).'">'.e($label).'</label><input class="bd-ix__input" id="'.$id($path).'" name="'.$name($path).'" value="'.e((string) data_get($layout, $path)).'" maxlength="120" placeholder="'.e($placeholder).'" data-dp-field data-pf-field>'.$err($path).'</div>');
    };

    $fontOptions = collect($fonts)->map(fn ($f) => $f['label'])->all();
    $sizeOptions = collect($sizes)->map(fn ($s) => $s['label'].($s['w'] ? ' — '.$s['w'].' × '.$s['h'].' mm' : ''))->all();
    $tabs = ['halaman' => 'Halaman', 'tipografi' => 'Tipografi', 'paragraf' => 'Paragraf', 'heading' => 'Heading', 'hf' => 'Header & Footer', 'nomor' => 'Penomoran', 'toc' => 'Daftar Isi', 'tabel' => 'Tabel & Pustaka', 'ekspor' => 'Ekspor', 'periksa' => 'Pemeriksaan'];
    $check = $preview['check'];
    $isCustom = $layout['page']['size'] === 'custom';
    $mirror = $layout['margins']['mirror'];
@endphp

<div class="bd-pf__info bd-ix__fields">
    <div class="bd-ix__grid bd-pf__identity">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pf-name">Nama template <small>wajib</small></label>
            <input class="bd-ix__input" id="pf-name" name="name" maxlength="180" value="{{ $template->name }}" required data-dp-field>
            <p class="bd-ix__note" data-error-for="name"></p>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pf-type">Jenis dokumen</label>
            <select class="bd-ix__input bd-ix__select" id="pf-type" name="document_type" data-dp-field>
                @foreach ($types as $type)<option value="{{ $type }}" @selected($template->document_type === $type)>{{ $type }}</option>@endforeach
            </select>
            <p class="bd-ix__note" data-error-for="document_type"></p>
        </div>
        {!! $sel('category', 'Kategori format', $categories) !!}
    </div>
    <details class="bd-pf__more">
        <summary>Deskripsi template <small>opsional</small></summary>
        <div class="bd-ix__field">
            <label class="bd-ix__label bd-ix__sr" for="pf-desc">Deskripsi</label>
            <textarea class="bd-ix__input bd-ix__textarea" id="pf-desc" name="description" rows="2" maxlength="2000" data-dp-field>{{ $template->description }}</textarea>
            <p class="bd-ix__note" data-error-for="description"></p>
        </div>
    </details>
</div>

<div class="bd-pf__tabs" role="tablist" aria-label="Pengaturan format" data-pf-tabs>
    @foreach ($tabs as $key => $label)
        <button type="button" role="tab" class="bd-pf__tab" id="pf-tab-{{ $key }}" aria-controls="pf-panel-{{ $key }}" aria-selected="false" tabindex="-1" data-pf-tab="{{ $key }}">{{ $label }}</button>
    @endforeach
    <button type="button" role="tab" class="bd-pf__tab bd-pf__tab--preview" id="pf-tab-pratinjau" aria-controls="pratinjau" aria-selected="false" tabindex="-1" data-pf-tab="pratinjau">Pratinjau</button>
</div>

{{-- Halaman --}}
<section class="bd-pf__panel" id="pf-panel-halaman" role="tabpanel" aria-labelledby="pf-tab-halaman" data-pf-panel="halaman">
    <h3 class="bd-pf__panel-title">Halaman</h3>
    <div class="bd-ix__grid">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pf-unit">Satuan panjang</label>
            <select class="bd-ix__input bd-ix__select" id="pf-unit" name="layout[unit]" data-dp-field data-pf-unitselect aria-describedby="pf-unit-h">
                @foreach (['mm' => 'Milimeter (mm)', 'cm' => 'Sentimeter (cm)', 'in' => 'Inci (in)'] as $u => $l)<option value="{{ $u }}" @selected($unit === $u)>{{ $l }}</option>@endforeach
            </select>
            <p class="bd-ix__hint" id="pf-unit-h">Hanya untuk tampilan dan isian. Disimpan dalam milimeter; 1 in = 25,4 mm.</p>
        </div>
        {!! $sel('page.size', 'Ukuran halaman', $sizeOptions, 'B5 ISO (176 × 250 mm) dan B5 JIS (182 × 257 mm) berbeda. Pilih sesuai pedoman penerbit atau percetakan.') !!}
    </div>
    <div class="bd-ix__grid" data-pf-custom @unless ($isCustom) hidden @endunless>
        {!! $length('page.width', 'Lebar halaman kustom') !!}
        {!! $length('page.height', 'Tinggi halaman kustom') !!}
    </div>
    {!! $sel('page.orientation', 'Orientasi', ['portrait' => 'Potret', 'landscape' => 'Lanskap']) !!}

    <h3 class="bd-pf__panel-title">Margin dan area cetak</h3>
    {!! $chk('margins.mirror', 'Margin cermin untuk buku (dalam/luar, halaman ganjil dan genap)', 'Sisi dalam berada di dekat jilid. Aktif di DOCX (mirrorMargins) dan PDF.') !!}
    <div class="bd-ix__grid bd-dp__grid3">
        {!! $length('margins.top', 'Atas') !!}
        {!! $length('margins.bottom', 'Bawah') !!}
        {!! $length('margins.gutter', 'Gutter (jilid)') !!}
    </div>
    <div class="bd-ix__grid" data-pf-plain @if ($mirror) hidden @endif>
        {!! $length('margins.left', 'Kiri') !!}
        {!! $length('margins.right', 'Kanan') !!}
    </div>
    <div class="bd-ix__grid" data-pf-mirrored @unless ($mirror) hidden @endunless>
        {!! $length('margins.inner', 'Dalam') !!}
        {!! $length('margins.outer', 'Luar') !!}
    </div>
    <p class="bd-ix__hint">Gutter menambah ruang jilid pada sisi dalam. Area teks tidak boleh lebih kecil dari 50 × 80 mm.</p>
</section>

{{-- Tipografi --}}
<section class="bd-pf__panel" id="pf-panel-tipografi" role="tabpanel" aria-labelledby="pf-tab-tipografi" data-pf-panel="tipografi">
    <h3 class="bd-pf__panel-title">Tipografi</h3>
    <div class="bd-ix__grid">
        {!! $sel('type.body_font', 'Font isi', $fontOptions) !!}
        {!! $num('type.body_size', 'Ukuran huruf isi', ['unit' => 'pt', 'step' => '0.5', 'min' => 8, 'max' => 16]) !!}
    </div>
    <div class="bd-ix__grid">
        {!! $sel('type.heading_font', 'Font heading', $fontOptions) !!}
        {!! $num('type.caption_size', 'Ukuran keterangan tabel/gambar', ['unit' => 'pt', 'step' => '0.5', 'min' => 7, 'max' => 14]) !!}
    </div>
    <div class="bd-ix__grid">
        {!! $sel('type.reference_font', 'Font daftar pustaka', $fontOptions) !!}
        {!! $num('type.reference_size', 'Ukuran huruf daftar pustaka', ['unit' => 'pt', 'step' => '0.5', 'min' => 8, 'max' => 16]) !!}
    </div>
    <p class="bd-ix__hint">Font tidak disertakan dalam berkas: PDF memakai font dasar (Times, Arial/Helvetica, Courier) dan Word memakai font di komputer pembaca. Pilihan bertanda "PDF memakai…" diganti di PDF dan pratinjau, jadi lebar teksnya dapat berbeda antar keluaran.</p>
</section>

{{-- Paragraf --}}
<section class="bd-pf__panel" id="pf-panel-paragraf" role="tabpanel" aria-labelledby="pf-tab-paragraf" data-pf-panel="paragraf">
    <h3 class="bd-pf__panel-title">Paragraf isi</h3>
    <div class="bd-ix__grid bd-dp__grid3">
        {!! $num('para.line_spacing', 'Spasi baris', ['unit' => '×', 'step' => '0.05', 'min' => 1, 'max' => 3]) !!}
        {!! $num('para.before', 'Spasi sebelum', ['unit' => 'pt', 'step' => '1', 'min' => 0, 'max' => 72]) !!}
        {!! $num('para.after', 'Spasi sesudah', ['unit' => 'pt', 'step' => '1', 'min' => 0, 'max' => 72]) !!}
    </div>
    <div class="bd-ix__grid">
        {!! $length('para.indent', 'Indentasi baris pertama') !!}
        {!! $sel('para.align', 'Perataan', $align) !!}
    </div>
    {!! $chk('para.widow', 'Kontrol janda/yatim (hindari satu baris sendirian di awal atau akhir halaman)', 'DOCX memakai pengaturan Word; PDF menjaga minimal 2 baris pada paragraf 4 baris atau lebih.') !!}
</section>

{{-- Heading --}}
<section class="bd-pf__panel" id="pf-panel-heading" role="tabpanel" aria-labelledby="pf-tab-heading" data-pf-panel="heading">
    <h3 class="bd-pf__panel-title">Heading dan penomoran</h3>
    <div class="bd-ix__grid">
        {!! $sel('headings.numbering', 'Penomoran heading', $numbering, 'Satu sumber penomoran: nomor manual di awal judul dari Prompt Dokumen (mis. "BAB I", "1.") dibuang agar tidak ganda. Nomor ditulis sebagai teks, bukan penomoran otomatis Word.') !!}
        {!! $sel('headings.chapter_start', 'Awal bab', $chapterStart, 'Halaman ganjil menyisipkan halaman kosong bila perlu (disengaja untuk cetak buku).') !!}
    </div>
    @foreach (['h1' => 'Heading 1 (bab)', 'h2' => 'Heading 2 (subbab)', 'h3' => 'Heading 3 (sub-subbab)'] as $h => $hl)
        <fieldset class="bd-dp__fieldset bd-pf__hrow">
            <legend>{{ $hl }}</legend>
            <div class="bd-ix__grid bd-pf__grid4">
                {!! $num("headings.$h.size", 'Ukuran', ['unit' => 'pt', 'step' => '0.5', 'min' => 8, 'max' => 36]) !!}
                {!! $sel("headings.$h.align", 'Perataan', ['left' => 'Kiri', 'center' => 'Tengah']) !!}
                {!! $num("headings.$h.before", 'Spasi sebelum', ['unit' => 'pt', 'step' => '1', 'max' => 72]) !!}
                {!! $num("headings.$h.after", 'Spasi sesudah', ['unit' => 'pt', 'step' => '1', 'max' => 72]) !!}
            </div>
            <div class="bd-dp__checks">
                {!! $chk("headings.$h.bold", 'Tebal') !!}
                {!! $chk("headings.$h.uppercase", 'Huruf kapital') !!}
                {!! $chk("headings.$h.keep_next", 'Satu halaman dengan paragraf berikutnya') !!}
            </div>
        </fieldset>
    @endforeach
    <p class="bd-ix__hint">DOCX memakai style bernama "Heading 1–3" sehingga muncul di panel navigasi Word dan dapat menjadi sumber daftar isi.</p>
</section>

{{-- Header & Footer --}}
<section class="bd-pf__panel" id="pf-panel-hf" role="tabpanel" aria-labelledby="pf-tab-hf" data-pf-panel="hf">
    <h3 class="bd-pf__panel-title">Header dan footer</h3>
    <p class="bd-ix__hint">Token: <code>{title}</code> judul dokumen, <code>{chapter}</code> judul bab berjalan, <code>{page}</code> nomor halaman. Dikosongkan = tidak ada. Tidak ada merek BacaDulu yang ditambahkan.</p>
    <fieldset class="bd-dp__fieldset"><legend>Header</legend>
        <div class="bd-ix__grid bd-dp__grid3">{!! $txt('header_footer.header.left', 'Kiri', '{title}') !!}{!! $txt('header_footer.header.center', 'Tengah') !!}{!! $txt('header_footer.header.right', 'Kanan', '{chapter}') !!}</div>
    </fieldset>
    <fieldset class="bd-dp__fieldset"><legend>Footer</legend>
        <div class="bd-ix__grid bd-dp__grid3">{!! $txt('header_footer.footer.left', 'Kiri') !!}{!! $txt('header_footer.footer.center', 'Tengah') !!}{!! $txt('header_footer.footer.right', 'Kanan') !!}</div>
    </fieldset>
    <div class="bd-dp__checks">
        {!! $chk('header_footer.odd_even', 'Header/footer ganjil-genap (kiri dan kanan bertukar di halaman genap)') !!}
        {!! $chk('header_footer.skip_chapter_header', 'Tanpa header pada halaman pertama bab', 'Berlaku bila bab dimulai di halaman baru atau ganjil. Di DOCX dengan header ganjil-genap, hanya bila bab dimulai di halaman ganjil.') !!}
    </div>
    <p class="bd-ix__hint">Halaman judul dan halaman kosong tidak memiliki header, footer, maupun nomor halaman. Header hanya tampil di halaman isi.</p>
</section>

{{-- Penomoran --}}
<section class="bd-pf__panel" id="pf-panel-nomor" role="tabpanel" aria-labelledby="pf-tab-nomor" data-pf-panel="nomor">
    <h3 class="bd-pf__panel-title">Nomor halaman</h3>
    <div class="bd-ix__grid">
        {!! $sel('page_numbers.position', 'Posisi', $pnPositions) !!}
        {!! $sel('page_numbers.format', 'Format', $pnFormats) !!}
    </div>
    <div class="bd-ix__grid">
        {!! $num('page_numbers.start', 'Nomor awal', ['step' => '1', 'min' => 1, 'max' => 9999]) !!}
    </div>
    {!! $chk('page_numbers.front_roman', 'Halaman awal (daftar isi) berangka Romawi kecil, isi mulai dari nomor awal', 'Halaman judul tidak diberi nomor. Berpengaruh bila halaman judul atau daftar isi aktif.') !!}
</section>

{{-- Daftar isi --}}
<section class="bd-pf__panel" id="pf-panel-toc" role="tabpanel" aria-labelledby="pf-tab-toc" data-pf-panel="toc">
    <h3 class="bd-pf__panel-title">Halaman judul dan daftar isi</h3>
    {!! $chk('front.title_page', 'Halaman judul terpisah (judul, jenis dokumen, penulis bila ada)', 'Tidak membuat identitas penerbit, ISBN, atau halaman hak cipta.') !!}
    {!! $chk('toc.enabled', 'Daftar isi otomatis dari heading') !!}
    <div class="bd-ix__grid">
        {!! $txt('toc.title', 'Judul daftar isi') !!}
        {!! $num('toc.levels', 'Level heading', ['unit' => '1–3', 'step' => '1', 'min' => 1, 'max' => 3]) !!}
    </div>
    <div class="bd-dp__checks">
        {!! $chk('toc.dot_leaders', 'Titik penghubung') !!}
        {!! $chk('toc.page_numbers', 'Nomor halaman') !!}
    </div>
    <p class="bd-ix__hint">PDF: nomor halaman dari paginasi nyata. DOCX: daftar isi adalah field Word yang diisi saat dokumen dibuka; Word dapat meminta izin memperbarui field. Isi daftar isi di DOCX tidak ada sebelum itu.</p>
</section>

{{-- Tabel & Pustaka --}}
<section class="bd-pf__panel" id="pf-panel-tabel" role="tabpanel" aria-labelledby="pf-tab-tabel" data-pf-panel="tabel">
    <h3 class="bd-pf__panel-title">Tabel</h3>
    <div class="bd-ix__grid bd-dp__grid3">
        {!! $sel('tables.align', 'Perataan tabel', ['left' => 'Kiri', 'center' => 'Tengah']) !!}
        {!! $sel('tables.border', 'Garis', $tableBorders) !!}
        {!! $num('tables.padding', 'Padding sel', ['unit' => 'pt', 'step' => '0.5', 'min' => 0, 'max' => 10]) !!}
    </div>
    <div class="bd-dp__checks">
        {!! $chk('tables.header_bold', 'Baris judul tebal') !!}
        {!! $chk('tables.header_shade', 'Baris judul berlatar abu-abu muda') !!}
    </div>
    <p class="bd-ix__hint">Tabel dibaca dari baris bergaya <code>| kolom | kolom |</code> dan dibuat selebar area teks dengan kolom sama lebar. Konten dokumen yang dihasilkan saat ini belum memuat gambar, jadi tidak ada pengaturan gambar.</p>

    <h3 class="bd-pf__panel-title">Daftar pustaka</h3>
    {!! $txt('refs.heading', 'Judul daftar pustaka') !!}
    <div class="bd-ix__grid">
        {!! $length('refs.hanging', 'Indentasi gantung') !!}
        {!! $num('refs.after', 'Spasi antar referensi', ['unit' => 'pt', 'step' => '1', 'max' => 72]) !!}
    </div>
    {!! $chk('refs.new_page', 'Daftar pustaka di halaman baru') !!}
    <p class="bd-ix__hint">Modul ini hanya mengatur tampilan. Isi dan gaya sitasi (APA 7, IEEE, Harvard) berasal dari sistem referensi yang ada; tidak ada mesin sitasi kedua.</p>
</section>

{{-- Ekspor --}}
<section class="bd-pf__panel" id="pf-panel-ekspor" role="tabpanel" aria-labelledby="pf-tab-ekspor" data-pf-panel="ekspor">
    <h3 class="bd-pf__panel-title">Ekspor</h3>
    <p class="bd-ix__muted">Keluaran yang didukung: <strong>DOCX</strong> dan <strong>PDF</strong> akademik, dihasilkan dari aturan yang sama. PDF/X, bleed, crop marks, dan CMYK tidak didukung.</p>
    <table class="bd-pf__support">
        <caption class="bd-ix__sr">Dukungan pengaturan per keluaran</caption>
        <thead><tr><th scope="col">Pengaturan</th><th scope="col">DOCX</th><th scope="col">PDF dan pratinjau</th></tr></thead>
        <tbody>
            @foreach ($support as $label => [$docx, $pdf, $note])
                <tr>
                    <th scope="row">{{ $label }}@if ($note)<small>{{ $note }}</small>@endif</th>
                    <td><span class="{{ $docx ? 'is-yes' : 'is-no' }}">{{ $docx ? 'Ya' : 'Tidak' }}</span></td>
                    <td><span class="{{ $pdf ? 'is-yes' : 'is-no' }}">{{ $pdf ? 'Ya' : 'Tidak' }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="bd-ix__hint">Struktur bagian (pemetaan hasil riset → bagian) tidak diatur di sini dan tetap dipakai generator: {{ count($sections) }} bagian. Isi dokumen diatur di Prompt Dokumen.</p>
</section>

{{-- Pemeriksaan --}}
<section class="bd-pf__panel" id="pf-panel-periksa" role="tabpanel" aria-labelledby="pf-tab-periksa" data-pf-panel="periksa">
    <h3 class="bd-pf__panel-title">Pemeriksaan cetak</h3>
    <p class="bd-pf__status-line"><span class="bd-pf__badge bd-pf__badge--{{ ['ready' => 'ok', 'review' => 'warn', 'blocked' => 'bad'][$check['status']] }}" data-pf-check-badge>{{ $check['label'] }}</span> <span class="bd-ix__muted">Pemeriksaan teknis tata letak; bukan penilaian mutu akademik dan bukan jaminan siap cetak profesional.</span></p>
    <ul class="bd-pf__checks" data-pf-checks>
        @foreach ($check['items'] as $item)
            <li class="is-{{ $item['level'] }}"><span class="bd-ix__sr">{{ ['ok' => 'Baik', 'warn' => 'Perlu diperiksa', 'error' => 'Masalah'][$item['level']] }}: </span>{{ $item['text'] }}</li>
        @endforeach
    </ul>
    <p class="bd-ix__hint">"Siap diekspor" berarti DOCX/PDF dapat dibuat dengan benar. "Siap cetak profesional" memerlukan standar percetakan atau penerbit yang diverifikasi di luar sistem ini.</p>
</section>
