@extends('layouts.admin')

@section('title', 'Detail Full-Text #'.$ft->id)
@section('page_title', 'Detail Full-Text')

@php
    use App\Services\FullText\FullTextState as S;

    $bytes = fn (?int $n) => $n === null ? '-' : ($n >= 1048576 ? number_format($n / 1048576, 2, ',', '.').' MB' : number_format($n / 1024, 0, ',', '.').' KB');
    $wib = fn ($d) => $d?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j M Y, H.i') ?? '-';
    $kinds = ['pdf' => 'PDF langsung', 'xml' => 'XML (TEI/JATS)', 'landing' => 'Halaman arahan', 'tdm' => 'Tautan text-mining'];
    $sectionNames = ['abstract' => 'Abstrak', 'introduction' => 'Pendahuluan', 'literature_review' => 'Tinjauan pustaka', 'methodology' => 'Metodologi', 'results' => 'Hasil', 'results_discussion' => 'Hasil dan pembahasan', 'discussion' => 'Pembahasan', 'conclusion' => 'Kesimpulan', 'references' => 'Daftar pustaka'];
@endphp

@section('content')
<section class="admin-page bd-ext bd-ft" data-ft>
    <header class="bd-ext__head">
        <div>
            <h1>{{ \Illuminate\Support\Str::limit($ft->publication?->title ?? 'Publikasi dihapus', 140) }}</h1>
            <p>Lokasi teks lengkap #{{ $ft->id }} · publikasi #{{ $ft->publication_id }}@if ($ft->publication?->doi) · DOI {{ $ft->publication->doi }}@endif</p>
        </div>
        <div class="bd-ext__head-actions"><a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.external-sources.fulltext.index') }}">Kembali ke daftar</a></div>
    </header>

    <section class="bd-ext__section" aria-labelledby="ft-facts">
        <div class="bd-ext__section-head"><h2 id="ft-facts">Status dan izin</h2></div>
        <dl class="bd-ft__facts">
            <div><dt>Status</dt><dd>{{ S::LABELS[$ft->state] ?? $ft->state }}</dd></div>
            <div><dt>Izin</dt><dd>{{ ['allow' => 'Diizinkan', 'review' => 'Perlu tinjauan', 'deny' => 'Ditolak'][$ft->permission] ?? $ft->permission }} — {{ $ft->permission_reason }}</dd></div>
            <div><dt>Jenis lokasi</dt><dd>{{ $kinds[$ft->kind] ?? $ft->kind }}</dd></div>
            <div><dt>Penyedia</dt><dd>{{ $ft->provider }} @if ($ft->license_source)<span class="bd-ext__muted">(bukti: {{ $ft->license_source }})</span>@endif</dd></div>
            <div><dt>Lisensi</dt><dd>{{ $ft->license_code ? strtoupper($ft->license_code) : 'Tidak diketahui' }} @if ($ft->license)<span class="bd-ext__muted">(mentah: {{ $ft->license }})</span>@endif @if ($ft->license_url)· <a class="bd-ext__link" href="{{ $ft->license_url }}" target="_blank" rel="noopener noreferrer">URL lisensi</a>@endif</dd></div>
            <div><dt>Cakupan lisensi / versi</dt><dd>{{ $ft->license_scope === 'journal' ? 'Tingkat jurnal' : 'Tingkat artikel' }} · {{ $ft->version ?? 'versi tidak diketahui' }}</dd></div>
            <div><dt>URL asli</dt><dd class="bd-ft__url">{{ $ft->url }}</dd></div>
            <div><dt>Atribusi</dt><dd>{{ $ft->attribution ?? '-' }}</dd></div>
            @if ($ft->reviewed_at)<div><dt>Tinjauan admin</dt><dd>{{ $wib($ft->reviewed_at) }} — {{ $ft->review_note ?? 'tanpa catatan' }}</dd></div>@endif
            @if ($ft->error_code)<div><dt>Galat terakhir</dt><dd>{{ $ft->error_code }} — {{ $ft->error_message }}</dd></div>@endif
            @if ($ft->duplicate_of_id)<div><dt>Duplikat dari</dt><dd><a class="bd-ext__link" href="{{ route('admin.external-sources.fulltext.show', $ft->duplicate_of_id) }}">Lokasi #{{ $ft->duplicate_of_id }}</a></dd></div>@endif
            <div><dt>Percobaan unduh</dt><dd>{{ $ft->attempts }}@if ($ft->next_attempt_at && $ft->next_attempt_at->isFuture()) · berikutnya {{ $wib($ft->next_attempt_at) }}@endif</dd></div>
        </dl>
    </section>

    @if (S::hasLocalCopy($ft->state))
        <section class="bd-ext__section" aria-labelledby="ft-file">
            <div class="bd-ext__section-head"><h2 id="ft-file">Berkas lokal</h2><p>Disimpan di penyimpanan privat. Tidak ada tautan unduh publik.</p></div>
            <dl class="bd-ft__facts">
                <div><dt>Ukuran</dt><dd>{{ $bytes($ft->size_bytes) }} · {{ $ft->mime }}</dd></div>
                <div><dt>SHA-256</dt><dd class="bd-ft__url">{{ $ft->sha256 }}</dd></div>
                <div><dt>Diunduh</dt><dd>{{ $wib($ft->downloaded_at) }}</dd></div>
                <div><dt>Ekstraksi</dt><dd>{{ $ft->extraction_method ?? '-' }}@if ($ft->pages_total) · {{ $ft->pages_with_text }} dari {{ $ft->pages_total }} halaman memiliki teks @if ($ft->pages_with_text < $ft->pages_total)<strong>(parsial)</strong>@endif @endif · {{ number_format((int) $ft->text_chars, 0, ',', '.') }} karakter</dd></div>
                <div><dt>Chunk</dt><dd>{{ $ft->chunk_count }}</dd></div>
            </dl>
        </section>
    @endif

    @if ($ft->evidence_json)
        <section class="bd-ext__section" aria-labelledby="ft-evidence">
            <div class="bd-ext__section-head"><h2 id="ft-evidence">Bukti izin dari penyedia</h2><p>Data mentah yang dipakai untuk keputusan izin. Tidak memuat rahasia.</p></div>
            <pre class="bd-ft__json">{{ json_encode($ft->evidence_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </section>
    @endif

    <section class="bd-ext__section" aria-labelledby="ft-chunks">
        <div class="bd-ext__section-head"><h2 id="ft-chunks">Evidence chunks</h2><p>{{ $ft->state === S::INDEXED ? number_format($chunks->total(), 0, ',', '.').' potongan teks tersimpan. Bagian dan halaman hanya tampil bila dikenali.' : 'Belum ada chunk: teks baru diindeks setelah berkas diunduh dan diekstrak.' }}</p></div>
        @php
            $total = array_sum($sectionCounts);
            $top = collect($sectionCounts)->except(['', 'references'])->sortDesc();
            $wide = $total >= 8 && $top->isNotEmpty() && $top->first() / $total >= 0.6;
        @endphp
        @if ($total > 0)
            <p class="bd-ext__muted">Sebaran bagian: @foreach ($sectionCounts as $name => $n){{ $name !== '' ? ($sectionNames[$name] ?? $name) : 'tidak diketahui' }} {{ $n }}@if (! $loop->last), @endif @endforeach</p>
        @endif
        @if ($wide)
            <p class="bd-ext__notice" role="note"><strong>Label bagian mungkin terlalu luas.</strong> {{ number_format($top->first(), 0, ',', '.') }} dari {{ $total }} chunk berlabel "{{ $top->keys()->first() }}" karena heading lain di artikel ini tidak berbentuk baku dan tidak dikenali. Jangan menganggap isi chunk-chunk itu benar-benar berasal dari bagian tersebut; gunakan nomor halaman.</p>
        @endif
        @if ($chunks->isEmpty())
            <p class="bd-ext__empty">Tidak ada chunk.</p>
        @else
            <div class="bd-ext__table-wrap">
                <table class="bd-ext__table">
                    <thead><tr><th scope="col">#</th><th scope="col">Bagian</th><th scope="col">Halaman</th><th scope="col">Isi</th></tr></thead>
                    <tbody>
                        @foreach ($chunks as $c)
                            <tr>
                                <td>{{ $c->chunk_index }}</td>
                                <td>{{ $c->section ? ($sectionNames[$c->section] ?? $c->section) : 'tidak diketahui' }}@if ($c->is_references) <span class="bd-ext__chip is-mute">referensi</span>@endif</td>
                                <td>@if ($c->page_start){{ $c->page_start }}@if ($c->page_end && $c->page_end !== $c->page_start)–{{ $c->page_end }}@endif @else - @endif</td>
                                <td>
                                    <details><summary>{{ \Illuminate\Support\Str::limit($c->content, 140) }}</summary><p class="bd-ft__chunk">{{ $c->content }}</p><p class="bd-ext__muted">{{ $c->word_count }} kata · sha1 {{ substr($c->content_sha, 0, 10) }}</p></details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('admin.external-sources.partials.pager', ['paginator' => $chunks, 'fragment' => 'ft-chunks'])
        @endif
    </section>
</section>
@endsection
