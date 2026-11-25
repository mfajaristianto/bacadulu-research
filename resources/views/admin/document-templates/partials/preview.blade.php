@php
    $check = $preview['check'];
    $badge = ['ready' => 'ok', 'review' => 'warn', 'blocked' => 'bad'][$check['status']];
@endphp
<aside class="bd-pf__preview" data-pf-panel="pratinjau" id="pratinjau" aria-labelledby="pf-prev-title" tabindex="-1">
    <div class="bd-pf__preview-head">
        <h2 id="pf-prev-title" class="bd-ix__side-title">Pratinjau tata letak</h2>
        <span class="bd-pf__badge bd-pf__badge--{{ $badge }}" data-pf-badge>{{ $check['label'] }}</span>
    </div>
    <p class="bd-ab__banner" role="note"><strong>CONTOH TAMPILAN.</strong> Isi di bawah adalah teks contoh, bukan hasil penelitian atau sumber nyata. Pratinjau ini digambar dari mesin tata letak PDF yang sama dengan ekspor; DOCX ditata ulang oleh Word dan dapat sedikit berbeda.</p>

    <div class="bd-pf__controls">
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pf-sample">Tampilkan</label>
            <select class="bd-ix__input bd-ix__select" id="pf-sample" data-pf-sample>
                @foreach ($samples as $value => $label)<option value="{{ $value }}" @selected($sample === $value)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="bd-ix__field">
            <label class="bd-ix__label" for="pf-structure">Struktur bagian</label>
            <select class="bd-ix__input bd-ix__select" id="pf-structure" data-pf-structure>
                <option value="">Contoh bawaan</option>
                @foreach ($structures as $s)<option value="{{ $s['id'] }}" @selected((int) $structureId === $s['id'])>{{ $s['name'] }}</option>@endforeach
            </select>
        </div>
    </div>
    @if ($structures !== [])
        <p class="bd-ix__hint">Struktur memakai judul bagian dari Prompt Dokumen. Isinya tetap teks contoh: modul ini hanya mengatur bentuk, bukan menulis konten.</p>
    @endif

    <label class="bd-pf__zoom"><input type="checkbox" data-pf-zoom> Satu halaman per baris (lebih besar)</label>
    <p class="bd-pf__status" data-pf-status role="status" aria-live="polite">{{ $preview['pages'] }} halaman · {{ round($preview['width_mm'], 1) }} × {{ round($preview['height_mm'], 1) }} mm</p>
    <div class="bd-pf__pages" data-pf-pages>{!! $preview['html'] !!}</div>

    <p class="bd-pf__downloads">
        <a class="bd-ix__link" href="{{ route('admin.document-templates.preview-pdf', [$template, $selected['v']]) }}" target="_blank" rel="noopener">Buka PDF contoh (hasil ekspor nyata)</a>
        <a class="bd-ix__link" href="{{ route('admin.document-templates.preview-docx', [$template, $selected['v']]) }}">Unduh DOCX contoh</a>
    </p>
    <p class="bd-ix__hint">PDF dan DOCX contoh dibuat dari versi yang tersimpan (v{{ $selected['v'] }}), bukan dari isian yang belum disimpan.</p>
</aside>
