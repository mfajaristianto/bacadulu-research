@extends('layouts.admin')

@section('title', 'Full-Text Sumber Eksternal')
@section('page_title', 'Full-Text Sumber Eksternal')

@php
    use App\Services\FullText\FullTextState as S;

    $bytes = fn (int $n) => $n >= 1073741824 ? number_format($n / 1073741824, 2, ',', '.').' GB' : ($n >= 1048576 ? number_format($n / 1048576, 1, ',', '.').' MB' : number_format($n / 1024, 0, ',', '.').' KB');
    $chip = [S::INDEXED => 'is-ok', S::ELIGIBLE => 'is-ok', S::STORED => 'is-ok', S::REVIEW_REQUIRED => 'is-warn', S::EXTRACTION_FAILED => 'is-bad', S::DOWNLOAD_FAILED => 'is-bad', S::QUEUED => 'is-run', S::DOWNLOADING => 'is-run', S::LINK_FOUND => 'is-mute'];
    $perm = ['allow' => ['Diizinkan', 'is-ok'], 'review' => ['Perlu tinjauan', 'is-warn'], 'deny' => ['Ditolak', 'is-bad']];
    $kinds = ['pdf' => 'PDF langsung', 'xml' => 'XML (TEI/JATS)', 'landing' => 'Halaman arahan', 'tdm' => 'Tautan text-mining'];
    $isFiltered = collect($f)->filter(fn ($v) => filled($v))->isNotEmpty();
@endphp

@section('content')
<section class="admin-page bd-ext bd-ft" data-ft>

    <header class="bd-ext__head">
        <div>
            <h1>Full-Text Sumber Eksternal</h1>
            <p>Temukan lokasi teks lengkap, periksa lisensi, lalu unduh dan indeks isi artikel yang sah sebagai evidence. Tidak ada yang diunduh otomatis.</p>
        </div>
        <div class="bd-ext__head-actions">
            <a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.external-sources.index') }}">Kembali ke Sumber Eksternal</a>
        </div>
    </header>

    @unless ($ready)
        <div class="bd-ext__notice" role="note">
            <strong>Penyimpanan full-text belum disiapkan.</strong>
            Tabel full-text belum dimigrasi (menunggu persetujuan). Halaman ini tidak menampilkan angka apa pun sampai migration dijalankan; semua tindakan dinonaktifkan.
        </div>
    @else
        <div class="bd-ext__notice" role="note">
            <strong>Aturan izin: tolak bila tidak jelas.</strong>
            Hanya lisensi CC0 dan CC BY dengan versi artikel yang jelas (terbit atau diterima) yang otomatis boleh diunduh. Status open access saja bukan izin menyimpan atau mengindeks.
            Unduhan berjalan di antrean <code>{{ $queue }}</code> (<code>php artisan queue:work --queue={{ $queue }}</code>), maksimum {{ $limits['queue_limits']['max_pending'] }} unduhan antre dan {{ $limits['queue_limits']['max_concurrent'] }} sekaligus.
        </div>

        <dl class="bd-ext__kpis" aria-label="Ringkasan full-text">
            @foreach ([
                ['Total publikasi', number_format($stats['publications'], 0, ',', '.')],
                ['Punya abstrak', number_format($stats['abstract_available'], 0, ',', '.')],
                ['Lokasi teks lengkap tercatat', number_format($stats['locations'], 0, ',', '.')],
                ['Perlu tinjauan lisensi', number_format($stats['review_required'], 0, ',', '.')],
                ['Boleh diunduh', number_format($stats['eligible'], 0, ',', '.')],
                ['Terindeks (lokasi / publikasi)', number_format($stats['indexed'], 0, ',', '.').' / '.number_format($stats['indexed_publications'], 0, ',', '.')],
                ['Unduhan gagal / ekstraksi gagal', number_format($stats['download_failed'], 0, ',', '.').' / '.number_format($stats['extraction_failed'], 0, ',', '.')],
                ['Penyimpanan terpakai', $bytes($stats['storage_bytes']).' dari '.$bytes($stats['quota_bytes'])],
            ] as [$label, $value])
                <div class="bd-ext__kpi"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>

        <section class="bd-ext__section" aria-labelledby="ft-check">
            <div class="bd-ext__section-head">
                <h2 id="ft-check">Periksa Full Text</h2>
                <p>Masukkan ID publikasi atau DOI. Pemeriksaan hanya mencatat lokasi dan keputusan izin; tidak mengunduh apa pun.</p>
            </div>
            <form method="POST" action="{{ route('admin.external-sources.fulltext.check') }}" class="bd-ft__check">
                @csrf
                <label class="bd-ext__sr" for="ft-target">ID publikasi atau DOI</label>
                <input id="ft-target" type="text" name="target" required maxlength="255" placeholder="ID publikasi atau DOI, mis. 10.1234/abcd" value="{{ old('target') }}">
                <label class="bd-ft__remote"><input type="checkbox" name="remote" value="1"> Tanya OpenAlex dan Crossref (1 permintaan per penyedia)</label>
                <button type="submit" class="bd-ext__btn bd-ext__btn--primary">Periksa Full Text</button>
            </form>
            @error('fulltext')<p class="bd-ext__fail" role="alert">{{ $message }}</p>@enderror
        </section>

        <section class="bd-ext__section" aria-labelledby="ft-list">
            <div class="bd-ext__section-head">
                <h2 id="ft-list">Lokasi teks lengkap</h2>
                <p>{{ number_format($rows->total(), 0, ',', '.') }} lokasi{{ $isFiltered ? ' sesuai filter' : '' }}.</p>
            </div>

            <form method="GET" action="{{ route('admin.external-sources.fulltext.index') }}" class="bd-ext__filters" role="search">
                <label class="bd-ext__sr" for="ft-q">Cari judul atau DOI</label>
                <input id="ft-q" type="search" name="q" value="{{ $f['q'] }}" placeholder="Cari judul atau DOI" maxlength="160">
                <label class="bd-ext__sr" for="ft-state">Status</label>
                <select id="ft-state" name="state"><option value="">Semua status</option>@foreach (S::rowStates() as $st)<option value="{{ $st }}" @selected($f['state'] === $st)>{{ S::LABELS[$st] }}</option>@endforeach</select>
                <label class="bd-ext__sr" for="ft-perm">Izin</label>
                <select id="ft-perm" name="permission"><option value="">Semua izin</option>@foreach ($perm as $k => [$l])<option value="{{ $k }}" @selected($f['permission'] === $k)>{{ $l }}</option>@endforeach</select>
                <label class="bd-ext__sr" for="ft-provider">Penyedia</label>
                <select id="ft-provider" name="provider"><option value="">Semua penyedia</option>@foreach (['openalex' => 'OpenAlex', 'crossref' => 'Crossref', 'doaj' => 'DOAJ'] as $k => $l)<option value="{{ $k }}" @selected($f['provider'] === $k)>{{ $l }}</option>@endforeach</select>
                <button type="submit" class="bd-ext__btn bd-ext__btn--primary">Terapkan</button>
                @if ($isFiltered)<a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.external-sources.fulltext.index') }}">Reset</a>@endif
            </form>

            @if ($rows->isEmpty())
                <p class="bd-ext__empty">{{ $isFiltered ? 'Tidak ada lokasi yang cocok dengan filter.' : 'Belum ada lokasi teks lengkap tercatat. Gunakan "Periksa Full Text" pada sebuah publikasi.' }}</p>
            @else
                <div class="bd-ext__table-wrap">
                    <table class="bd-ext__table">
                        <thead><tr><th scope="col">Publikasi</th><th scope="col">Lokasi</th><th scope="col">Lisensi</th><th scope="col">Status</th><th scope="col"><span class="bd-ext__sr">Aksi</span></th></tr></thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>
                                        <p class="bd-ext__rec-title">{{ \Illuminate\Support\Str::limit($r->publication?->title ?? '(publikasi dihapus)', 120) }}</p>
                                        <p class="bd-ext__rec-meta">#{{ $r->publication_id }}@if ($r->publication?->doi) · {{ $r->publication->doi }}@endif</p>
                                    </td>
                                    <td>
                                        <p>{{ $kinds[$r->kind] ?? $r->kind }}</p>
                                        <p class="bd-ext__muted">{{ ['openalex' => 'OpenAlex', 'crossref' => 'Crossref', 'doaj' => 'DOAJ'][$r->provider] ?? $r->provider }} · {{ parse_url($r->url, PHP_URL_HOST) }}</p>
                                    </td>
                                    <td>
                                        <p>{{ $r->license_code ? strtoupper($r->license_code) : 'Tidak diketahui' }}</p>
                                        <p class="bd-ext__muted">{{ $r->license_scope === 'journal' ? 'tingkat jurnal' : 'tingkat artikel' }} · versi {{ $r->version ?? 'tidak diketahui' }}</p>
                                    </td>
                                    <td>
                                        <span class="bd-ext__chip {{ $chip[$r->state] ?? 'is-mute' }}">{{ S::LABELS[$r->state] ?? $r->state }}</span>
                                        <span class="bd-ext__chip {{ $perm[$r->permission][1] ?? 'is-mute' }}">{{ $perm[$r->permission][0] ?? $r->permission }}</span>
                                        @if ($r->error_message && in_array($r->state, [S::DOWNLOAD_FAILED, S::EXTRACTION_FAILED, S::QUEUED], true))<p class="bd-ext__fail">{{ $r->error_message }}</p>@endif
                                        @if ($r->state === S::INDEXED)<p class="bd-ext__muted">{{ $r->chunk_count }} chunk @if ($r->pages_total) · {{ $r->pages_with_text }}/{{ $r->pages_total }} halaman bertext @endif</p>@endif
                                    </td>
                                    <td class="bd-ext__row-actions bd-ft__actions">
                                        <a class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" href="{{ route('admin.external-sources.fulltext.show', $r) }}">Lihat Detail</a>

                                        @if (in_array($r->state, [S::REVIEW_REQUIRED, S::LINK_FOUND], true) && in_array($r->kind, ['pdf', 'xml'], true))
                                            <details class="bd-ft__review">
                                                <summary class="bd-ext__btn bd-ext__btn--small">Tinjau Lisensi</summary>
                                                <form method="POST" action="{{ route('admin.external-sources.fulltext.review', $r) }}">
                                                    @csrf
                                                    <p class="bd-ext__muted">{{ $r->permission_reason }}</p>
                                                    <label for="note-{{ $r->id }}">Catatan bukti lisensi (min. 10 karakter untuk menyetujui)</label>
                                                    <textarea id="note-{{ $r->id }}" name="note" rows="3" maxlength="500" placeholder="Mis. lisensi CC BY tertulis di halaman 1 PDF; diperiksa manual."></textarea>
                                                    <div class="bd-ft__review-actions">
                                                        <button type="submit" name="decision" value="approve" class="bd-ext__btn bd-ext__btn--small bd-ext__btn--primary">Setujui</button>
                                                        <button type="submit" name="decision" value="reject" class="bd-ext__btn bd-ext__btn--small">Tolak</button>
                                                    </div>
                                                </form>
                                            </details>
                                        @endif

                                        @if ($r->state === S::ELIGIBLE)
                                            <form method="POST" action="{{ route('admin.external-sources.fulltext.queue', $r) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--primary" type="submit">Unduh &amp; Indeks</button></form>
                                        @endif
                                        @if ($r->state === S::QUEUED)
                                            <form method="POST" action="{{ route('admin.external-sources.fulltext.cancel', $r) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small" type="submit">Batalkan</button></form>
                                        @endif
                                        @if (in_array($r->state, [S::DOWNLOAD_FAILED, S::EXTRACTION_FAILED, S::STORED], true))
                                            <form method="POST" action="{{ route('admin.external-sources.fulltext.retry', $r) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small" type="submit">Retry</button></form>
                                        @endif
                                        @if (S::hasLocalCopy($r->state))
                                            <form method="POST" action="{{ route('admin.external-sources.fulltext.copy.delete', $r) }}" data-ft-confirm="Hapus salinan lokal dan seluruh chunk lokasi ini? Metadata dan keputusan izin tetap tersimpan.">@csrf @method('DELETE')<input type="hidden" name="confirm" value="1"><button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" type="submit">Hapus Salinan Lokal</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('admin.external-sources.partials.pager', ['paginator' => $rows, 'fragment' => 'ft-list'])
            @endif
        </section>
    @endunless
</section>
@endsection
