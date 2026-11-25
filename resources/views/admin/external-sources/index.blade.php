@extends('layouts.admin')

@section('title', 'Sumber Eksternal')
@section('page_title', 'Sumber Eksternal')

@php
    use Illuminate\Support\Str;

    $wib = fn ($date, string $format = 'j M Y, H.i') => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat($format);
    $implemented = collect($directory)->where('implemented', true);
    $catalog = collect($directory)->where('implemented', false);
    $statusChip = ['SUPPORTED' => 'is-ok', 'REQUIRES_CONFIGURATION' => 'is-warn', 'METADATA_ONLY' => 'is-mute', 'PENDING_VERIFICATION' => 'is-mute', 'NOT_SUPPORTED' => 'is-bad'];
    $typeLabels = ['article' => 'Artikel jurnal', 'book' => 'Buku', 'chapter' => 'Bab buku', 'proceedings' => 'Prosiding', 'dataset' => 'Dataset', 'thesis' => 'Tesis/disertasi', 'other' => 'Lainnya'];
    $availLabels = ['METADATA_ONLY' => 'Hanya metadata', 'ABSTRACT_AVAILABLE' => 'Ada abstrak', 'OPEN_ACCESS_LINK' => 'Tautan open access', 'RESTRICTED' => 'Terbatas', 'UNKNOWN' => 'Tidak diketahui'];
    $names = collect($directory)->pluck('name', 'code');
    $mappingMap = $mappings->groupBy('provider')->map(fn ($g) => $g->mapWithKeys(fn ($m) => [$m->category_id => ['terms' => implode(', ', $m->terms_json), 'max' => $m->max_records]]));
    $noData = ! $ready || $kpi['publications'] === 0;
    $max = fn (array $values) => max(1, ...array_map('intval', array_values($values) ?: [0]));
    $isFiltered = collect($filters)->except('history')->filter(fn ($v) => filled($v))->isNotEmpty();
    $pageLink = fn (array $q = []) => route('admin.external-sources.index', array_filter(array_merge($filters, $q), fn ($v) => filled($v)));
@endphp

@section('content')
<section class="admin-page bd-ext" data-ext data-base="{{ route('admin.external-sources.index') }}" data-status-url="{{ route('admin.external-sources.status') }}" data-poll="{{ $kpi['active'] > 0 ? '1' : '0' }}">

    <header class="bd-ext__head">
        <div>
            <h1>Sumber Eksternal</h1>
            <p>Hubungkan dan sinkronkan jurnal, buku, serta publikasi penelitian dari berbagai sumber akademik.</p>
        </div>
        <div class="bd-ext__head-actions">
            <a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.library.index') }}">Library Internal</a>
            <a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.source-sync.index') }}">Impor Sumber</a>
            <a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.external-sources.fulltext.index') }}">Full-Text</a>
            @if ($emergency)
                <form method="POST" action="{{ route('admin.external-sources.emergency.release') }}">@csrf<button type="submit" class="bd-ext__btn" data-ext-emergency="release">Lepas henti darurat</button></form>
            @else
                <form method="POST" action="{{ route('admin.external-sources.emergency.stop') }}" data-ext-confirm="Henti darurat: SEMUA sinkronisasi dijeda dan tidak ada yang bisa dimulai sampai dilepas. Lanjutkan?">@csrf<button type="submit" class="bd-ext__btn bd-ext__btn--danger" @disabled(! $ready) data-ext-emergency="stop">Henti darurat</button></form>
            @endif
            <button type="button" class="bd-ext__btn bd-ext__btn--primary" data-ext-open="sync-all" @disabled(! $ready || $emergency) @if (! $ready) title="Tabel penyimpanan belum dimigrasi" @elseif ($emergency) title="Henti darurat aktif" @endif>Semua kategori terpetakan</button>
        </div>
    </header>

    <div class="bd-ext__toast" data-ext-toast role="status" aria-live="polite" hidden>
        <p data-ext-toast-text></p>
        <button type="button" class="bd-ext__x" data-ext-toast-close aria-label="Tutup pesan">×</button>
    </div>

    {{-- Pesan sukses/galat ditampilkan oleh layout admin (bd-admin__flash); tidak diduplikasi di sini. --}}
    @if ($emergency)
        <div class="bd-ext__notice is-bad" role="alert" data-ext-emergency-banner><strong>Henti darurat aktif.</strong> Semua sinkronisasi dijeda; tidak ada yang bisa dimulai atau dilanjutkan sampai Anda melepasnya. Run yang dijeda menunggu Lanjutkan manual.</div>
    @endif

    @unless ($ready)
        <div class="bd-ext__notice" role="note">
            <strong>Penyimpanan belum disiapkan.</strong>
            Tabel sumber eksternal belum dimigrasi (menunggu persetujuan). Anda tetap bisa mengatur konektor, tes koneksi, dan pratinjau; Sync dinonaktifkan sampai migration dijalankan.
        </div>
    @endunless

    {{-- B. Statistik --}}
    <dl class="bd-ext__kpis" aria-label="Ringkasan sumber eksternal">
        @foreach ([['publications', 'Publikasi unik'], ['sources', 'Rekaman dari penyedia'], ['with_doi', 'Punya DOI'], ['categories', 'Kategori terisi'], ['runs', 'Total sinkronisasi'], ['active', 'Sedang berjalan']] as [$key, $label])
            <div class="bd-ext__kpi"><dt>{{ $label }}</dt><dd data-count="{{ $kpi[$key] }}" data-ext-kpi="{{ $key }}">{{ number_format($kpi[$key], 0, ',', '.') }}</dd></div>
        @endforeach
    </dl>

    {{-- C. Direktori konektor --}}
    <section class="bd-ext__section" aria-labelledby="ext-connectors">
        <div class="bd-ext__section-head">
            <h2 id="ext-connectors">Konektor sumber</h2>
            <p>Setiap penyedia berjalan sendiri. Gagal di satu penyedia tidak memengaruhi yang lain.</p>
        </div>

        <ul class="bd-ext__connectors">
            @foreach ($implemented as $c)
                @php
                    $conn = $c['connection'];
                    $cfg = ['provider' => $c['code'], 'name' => $c['name'], 'types' => $c['types'], 'filters' => $c['filters'], 'maps' => $mappingMap[$c['code']] ?? [], 'key' => $c['key'], 'keyNote' => $c['key_note'], 'keySet' => $c['settings']['has_key'] ?? false, 'keySource' => $c['settings']['key_source'] ?? null, 'enabled' => $c['settings']['enabled'] ?? false, 'contact' => $c['settings']['contact_own'] ? $c['settings']['contact'] : '', 'envContact' => (bool) ($c['settings']['contact'] && ! $c['settings']['contact_own']), 'modes' => $c['modes'], 'modeBlockers' => $c['mode_blockers'], 'modeNotes' => $c['mode_notes'] ?? [], 'bulk' => $c['bulk'] ?? null, 'searchCap' => $c['search_cap'] ?? null, 'hasKey' => (bool) ($c['settings']['has_key'] ?? false)];
                @endphp
                <li class="bd-ext__connector {{ $c['settings']['enabled'] ? '' : 'is-off' }}" data-connector="{{ $c['code'] }}" data-ext-cfg="{{ json_encode($cfg) }}">
                    <div class="bd-ext__connector-main">
                        <div class="bd-ext__connector-title">
                            <h3>{{ $c['name'] }}</h3>
                            <span class="bd-ext__chip {{ $statusChip[$c['status']] }}" title="Status konektor">{{ $c['status'] }}</span>
                            <span class="bd-ext__chip {{ $c['settings']['enabled'] ? 'is-ok' : 'is-mute' }}" data-ext-enabled-chip>{{ $c['settings']['enabled'] ? 'Aktif' : 'Nonaktif' }}</span>
                            @if ($c['active']) <span class="bd-ext__chip {{ $c['active']->isStale() ? 'is-warn' : 'is-run' }}">{{ $c['active']->isStale() ? 'Tampak macet' : ($statusLabels[$c['active']->status] ?? $c['active']->status) }}</span> @endif
                        </div>
                        <p class="bd-ext__connector-desc">{{ $c['description'] }}</p>
                        <p class="bd-ext__connector-meta">
                            <span>{{ $c['kinds'] }}</span>
                            <span>API key: {{ ['optional' => 'opsional'.($c['settings']['has_key'] ? ' (terpasang)' : ' (belum dipasang)'), 'none' => 'tidak perlu', 'required' => 'wajib'][$c['key']] ?? $c['key'] }}</span>
                            <span>{{ number_format($c['records'], 0, ',', '.') }} rekaman</span>
                            <span>Sync terakhir: {{ $c['last_ok'] ? $wib($c['last_ok']->finished_at, 'j M Y') : 'belum pernah' }}</span>
                        </p>
                        @if ($c['key'] === 'optional' && ! ($c['settings']['has_key'] ?? false))
                            <p class="bd-ext__hint">{{ $c['key_note'] }}</p>
                        @endif
                        @if ($c['last_failed'] && $c['last_failed']->errors_json)
                            <p class="bd-ext__fail">Gagal terakhir: {{ collect($c['last_failed']->errors_json)->last()['message'] ?? 'tidak diketahui' }}</p>
                        @endif
                        <p class="bd-ext__test" data-ext-test-result aria-live="polite">
                            @if ($conn)
                                <span class="bd-ext__dot {{ $conn['ok'] ? 'is-ok' : 'is-bad' }}"></span>
                                {{ $conn['ok'] ? 'Koneksi baik' : 'Koneksi gagal' }} · {{ $conn['message'] }} · {{ $wib(\Illuminate\Support\Carbon::parse($conn['at']), 'j M, H.i') }} WIB
                            @else
                                <span class="bd-ext__dot"></span> Belum pernah dites
                            @endif
                        </p>
                        @if ($c['active'])
                            @php $a = $c['active']; $pct = ($a->scope_json['max_records'] ?? 0) ? min(100, (int) round($a->processed_count / $a->scope_json['max_records'] * 100)) : 0; @endphp
                            <div class="bd-ext__progress" data-ext-progress="{{ $a->id }}">
                                <div class="bd-ext__bar"><span style="width: {{ $pct }}%"></span></div>
                                <p>#{{ $a->id }} · {{ $a->processed_count }} dari maks. {{ $a->scope_json['max_records'] ?? '?' }} diproses · {{ $a->new_count }} baru</p>
                            </div>
                        @endif
                    </div>
                    <div class="bd-ext__actions">
                        <button type="button" class="bd-ext__btn bd-ext__btn--small" data-ext-open="settings">Pengaturan</button>
                        <button type="button" class="bd-ext__btn bd-ext__btn--small" data-ext-test="{{ route('admin.external-sources.test', $c['code']) }}">Tes koneksi</button>
                        <button type="button" class="bd-ext__btn bd-ext__btn--small" data-ext-open="sync" data-mode="preview" @disabled((bool) $c['preview_blocker']) @if ($c['preview_blocker']) title="{{ $c['preview_blocker'] }}" @endif>Preview</button>
                        <button type="button" class="bd-ext__btn bd-ext__btn--small bd-ext__btn--primary" data-ext-open="sync" data-mode="sync" @disabled((bool) $c['blocker']) @if ($c['blocker']) title="{{ $c['blocker'] }}" @endif>Sync</button>
                        <a class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" href="{{ $pageLink(['history' => $c['code']]) }}#ext-history">Riwayat</a>
                    </div>
                    @if ($c['blocker'])<p class="bd-ext__blocker">{{ $c['blocker'] }}</p>@endif
                </li>
            @endforeach
        </ul>

        <details class="bd-ext__catalog">
            <summary>Sumber lain ({{ $catalog->count() }}): belum didukung, status apa adanya</summary>
            <ul>
                @foreach ($catalog as $c)
                    <li>
                        <div>
                            <strong>{{ $c['name'] }}</strong>
                            <span class="bd-ext__chip {{ $statusChip[$c['status']] ?? 'is-mute' }}">{{ $c['status'] }}</span>
                            <span class="bd-ext__muted">{{ $c['kinds'] }}</span>
                            <p>{{ $c['note'] }} @if ($c['docs_url'])<a class="bd-ext__link" href="{{ $c['docs_url'] }}" target="_blank" rel="noopener noreferrer">Dokumentasi</a>@endif</p>
                        </div>
                        <button type="button" class="bd-ext__btn bd-ext__btn--small" disabled title="Adapter belum dibangun">Sync</button>
                    </li>
                @endforeach
            </ul>
        </details>
    </section>

    {{-- Kampanye --}}
    <section class="bd-ext__section" aria-labelledby="ext-campaigns">
        <div class="bd-ext__section-head">
            <h2 id="ext-campaigns">Kampanye sinkronisasi</h2>
            <p>Satu kampanye terdiri dari banyak batch dengan checkpoint. Persentase dan perkiraan sisa waktu hanya tampil bila total dari penyedia benar-benar diketahui. Jeda/lanjut kapan saja; kehabisan anggaran berarti berhenti “sebagian”, bukan gagal.</p>
        </div>
        @include('admin.external-sources.partials.campaigns')
    </section>

    {{-- G. Pemetaan kategori --}}
    <section class="bd-ext__section" aria-labelledby="ext-mapping">
        <div class="bd-ext__section-head">
            <h2 id="ext-mapping">Pemetaan kategori</h2>
            <p>Istilah pencarian per kategori dan per penyedia. Tidak ada daftar bawaan: kategori tanpa pemetaan dilewati oleh “Sinkronkan semua”.</p>
        </div>
        <div class="bd-ext__map-grid">
            <div>
                @forelse ($mappings as $m)
                    <div class="bd-ext__map-row">
                        <div>
                            <strong>{{ $m->category?->name ?? 'Kategori dihapus' }}</strong> <span class="bd-ext__chip is-mute">{{ $names[$m->provider] ?? $m->provider }}</span>
                            <p class="bd-ext__muted">{{ implode(' · ', $m->terms_json) }} · maks {{ $m->max_records }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.external-sources.mappings.delete', $m) }}" data-ext-confirm="Hapus pemetaan ini?">@csrf @method('DELETE')<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" type="submit">Hapus</button></form>
                    </div>
                @empty
                    <p class="bd-ext__empty">Belum ada pemetaan. Tambahkan di samping atau centang “simpan sebagai pemetaan” saat Sync.</p>
                @endforelse
            </div>
            <form method="POST" action="{{ route('admin.external-sources.mappings.save') }}" class="bd-ext__map-form">
                @csrf @method('PUT')
                <label>Kategori
                    <select name="category_id" required><option value="">Pilih kategori</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}">{{ $cat->name }}</option>@endforeach</select>
                </label>
                <label>Penyedia
                    <select name="provider" required>@foreach ($implemented as $c)<option value="{{ $c['code'] }}">{{ $c['name'] }}</option>@endforeach</select>
                </label>
                <label>Istilah (pisahkan dengan koma atau baris baru)
                    <textarea name="terms" rows="3" maxlength="1000" required placeholder="mis. istilah pencarian dalam bahasa penyedia"></textarea>
                </label>
                <label>Maks. rekaman per run
                    <input type="number" name="max_records" min="1" max="{{ config('external-sources.limits.hard_max_records') }}" value="{{ config('external-sources.limits.default_max_records') }}">
                </label>
                <button type="submit" class="bd-ext__btn bd-ext__btn--primary" @disabled(! $ready)>Simpan pemetaan</button>
            </form>
        </div>
    </section>

    {{-- Klasifikasi, kualitas, kapasitas --}}
    <section class="bd-ext__section" aria-labelledby="ext-insights">
        <div class="bd-ext__section-head">
            <h2 id="ext-insights">Klasifikasi, kualitas, dan kapasitas</h2>
            <p>Laporan dari data tersimpan. Tidak ada klasifikasi AI; kategori disarankan lewat aturan istilah/subjek yang transparan dan ditinjau admin.</p>
        </div>
        @if ($ready)
            @include('admin.external-sources.partials.insights')
        @else
            <p class="bd-ext__empty">Tersedia setelah tabel penyimpanan dimigrasi.</p>
        @endif
    </section>

    {{-- Visualisasi --}}
    <section class="bd-ext__section" aria-labelledby="ext-charts" data-ext-charts>
        <div class="bd-ext__section-head">
            <h2 id="ext-charts">Visualisasi</h2>
            <p>Semua angka berasal dari data tersimpan dan riwayat sync nyata.</p>
        </div>
        @if ($noData)
            <p class="bd-ext__empty">Belum ada data untuk divisualisasikan. Aktifkan konektor, jalankan pratinjau, lalu sinkronisasi pertama.</p>
        @else
            @include('admin.external-sources.partials.charts')
        @endif
    </section>

    {{-- H. Rekaman --}}
    <section class="bd-ext__section" aria-labelledby="ext-records-title" id="ext-records">
        <div class="bd-ext__section-head">
            <h2 id="ext-records-title">Rekaman sumber</h2>
            <p>Hasil sinkronisasi. Berupa metadata bibliografi; tidak dipakai sebagai bukti teks penuh dan terpisah dari Pustaka internal.</p>
        </div>

        <form method="GET" action="{{ route('admin.external-sources.index') }}#ext-records" class="bd-ext__filters">
            <label class="bd-ext__sr" for="f-q">Cari judul, abstrak, atau DOI</label>
            <input id="f-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari judul, abstrak, DOI" maxlength="160">
            <label class="bd-ext__sr" for="f-provider">Penyedia</label>
            <select id="f-provider" name="provider"><option value="">Semua penyedia</option>@foreach ($implemented as $c)<option value="{{ $c['code'] }}" @selected(($filters['provider'] ?? '') === $c['code'])>{{ $c['name'] }}</option>@endforeach</select>
            <label class="bd-ext__sr" for="f-category">Kategori</label>
            <select id="f-category" name="category"><option value="">Semua kategori</option>@foreach ($categories as $cat)<option value="{{ $cat->id }}" @selected((string) ($filters['category'] ?? '') === (string) $cat->id)>{{ $cat->name }}</option>@endforeach</select>
            <label class="bd-ext__sr" for="f-year">Tahun</label>
            <input id="f-year" type="number" name="year" value="{{ $filters['year'] ?? '' }}" placeholder="Tahun" min="1000" max="2200">
            <label class="bd-ext__sr" for="f-type">Tipe</label>
            <select id="f-type" name="type"><option value="">Semua tipe</option>@foreach ($typeLabels as $k => $l)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
            <label class="bd-ext__sr" for="f-oa">Open access</label>
            <select id="f-oa" name="oa"><option value="">Semua akses</option><option value="open" @selected(($filters['oa'] ?? '') === 'open')>Open access</option><option value="closed" @selected(($filters['oa'] ?? '') === 'closed')>Tertutup</option><option value="unknown" @selected(($filters['oa'] ?? '') === 'unknown')>Tidak diketahui</option></select>
            <label class="bd-ext__sr" for="f-av">Status metadata</label>
            <select id="f-av" name="availability"><option value="">Semua ketersediaan</option>@foreach ($availLabels as $k => $l)<option value="{{ $k }}" @selected(($filters['availability'] ?? '') === $k)>{{ $l }}</option>@endforeach</select>
            <label class="bd-ext__sr" for="f-review">Peninjauan kategori</label>
            <select id="f-review" name="review"><option value="">Semua peninjauan</option><option value="suggested" @selected(($filters['review'] ?? '') === 'suggested')>Disarankan</option><option value="verified" @selected(($filters['review'] ?? '') === 'verified')>Diverifikasi admin</option><option value="none" @selected(($filters['review'] ?? '') === 'none')>Belum diklasifikasi</option></select>
            <label class="bd-ext__sr" for="f-quality">Kelengkapan metadata</label>
            <select id="f-quality" name="quality"><option value="">Semua kelengkapan</option><option value="missing_authors" @selected(($filters['quality'] ?? '') === 'missing_authors')>Tanpa penulis</option><option value="missing_year" @selected(($filters['quality'] ?? '') === 'missing_year')>Tanpa tahun</option><option value="missing_doi" @selected(($filters['quality'] ?? '') === 'missing_doi')>Tanpa DOI</option><option value="missing_abstract" @selected(($filters['quality'] ?? '') === 'missing_abstract')>Tanpa abstrak</option></select>
            <button type="submit" class="bd-ext__btn bd-ext__btn--primary">Terapkan</button>
            @if ($isFiltered)<a class="bd-ext__btn bd-ext__btn--quiet" href="{{ route('admin.external-sources.index') }}#ext-records">Reset</a>@endif
        </form>

        @if (! $records || $records->isEmpty())
            <p class="bd-ext__empty">{{ $isFiltered ? 'Tidak ada rekaman yang cocok dengan filter.' : 'Belum ada rekaman. Jalankan sinkronisasi pertama dari konektor di atas.' }}</p>
        @else
            <div class="bd-ext__table-wrap">
                <table class="bd-ext__table">
                    <thead><tr><th scope="col">Judul</th><th scope="col">Tahun</th><th scope="col">Penyedia</th><th scope="col">Kategori</th><th scope="col">Ketersediaan</th><th scope="col"><span class="bd-ext__sr">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($records as $r)
                            <tr>
                                <td><p class="bd-ext__rec-title">{{ Str::limit($r->title, 140) }}</p><p class="bd-ext__rec-meta">{{ collect($r->authors_json ?? [])->take(2)->implode(', ') ?: 'Penulis tidak tersedia' }}@if ($r->doi) · {{ $r->doi }}@endif</p>
                                    <p class="bd-ext__rec-tags">@include('admin.external-sources.partials.quality', ['q' => \App\Services\ExternalSources\QualitySignals::forModel($r), 'only' => 'warn'])</p></td>
                                <td>{{ $r->publication_year ?? '-' }}</td>
                                <td>{{ $r->sources->pluck('provider')->unique()->map(fn ($p) => $names[$p] ?? $p)->implode(', ') }}</td>
                                <td>@forelse ($r->categoryLinks as $l)<span class="bd-ext__chip {{ $l->status === 'verified' ? 'is-ok' : 'is-warn' }}">{{ $l->category?->name }}</span>@empty<span class="bd-ext__muted">Belum diklasifikasi</span>@endforelse</td>
                                <td><span class="bd-ext__chip {{ $r->availability === 'OPEN_ACCESS_LINK' ? 'is-ok' : 'is-mute' }}">{{ $availLabels[$r->availability] ?? $r->availability }}</span></td>
                                <td><button type="button" class="bd-ext__btn bd-ext__btn--small" data-ext-record="{{ route('admin.external-sources.publications.show', $r) }}">Detail</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('admin.external-sources.partials.pager', ['paginator' => $records, 'fragment' => 'ext-records'])
        @endif
    </section>

    {{-- Riwayat --}}
    <section class="bd-ext__section" aria-labelledby="ext-history-title" id="ext-history">
        <div class="bd-ext__section-head">
            <h2 id="ext-history-title">Riwayat sinkronisasi</h2>
            <p>@if ($filters['history'] ?? null) Penyedia: {{ $names[$filters['history']] ?? $filters['history'] }} · <a class="bd-ext__link" href="{{ route('admin.external-sources.index') }}#ext-history">Semua penyedia</a>@else Semua penyedia @endif</p>
        </div>
        @if (! $runs || $runs->isEmpty())
            <p class="bd-ext__empty">Belum ada riwayat sinkronisasi.</p>
        @else
            <div class="bd-ext__table-wrap">
                <table class="bd-ext__table bd-ext__table--runs" data-ext-runs>
                    <thead><tr><th scope="col">Run</th><th scope="col">Cakupan</th><th scope="col">Status</th><th scope="col">Ditemukan</th><th scope="col">Baru</th><th scope="col">Diperbarui</th><th scope="col">Duplikat</th><th scope="col">Gagal</th><th scope="col">Waktu</th><th scope="col"><span class="bd-ext__sr">Aksi</span></th></tr></thead>
                    <tbody>
                        @foreach ($runs as $run)
                            @php $dur = $run->started_at && $run->finished_at ? $run->started_at->diffInSeconds($run->finished_at) : null; $sc = $run->scope_json; @endphp
                            <tr data-run="{{ $run->id }}">
                                <td>#{{ $run->id }}<br><span class="bd-ext__muted">{{ $names[$run->provider] ?? $run->provider }}</span></td>
                                <td>{{ $run->category?->name ?? '-' }}<br><span class="bd-ext__muted">{{ implode(', ', $sc['terms'] ?? []) }}@if (! empty($sc['year_from']) || ! empty($sc['year_to'])) · {{ $sc['year_from'] ?? '…' }}–{{ $sc['year_to'] ?? '…' }}@endif · maks {{ $sc['max_records'] ?? '?' }}</span></td>
                                <td><span class="bd-ext__chip {{ ['completed' => 'is-ok', 'partial' => 'is-warn', 'failed' => 'is-bad', 'rate_limited' => 'is-warn', 'cancelled' => 'is-mute'][$run->status] ?? 'is-run' }}" data-run-status>{{ $statusLabels[$run->status] ?? $run->status }}</span>
                                    @if ($run->errors_json && $run->status !== 'completed')<p class="bd-ext__fail">{{ collect($run->errors_json)->last()['message'] }}</p>@endif</td>
                                <td data-run-found>{{ $run->found_count }}</td><td data-run-new>{{ $run->new_count }}</td><td data-run-updated>{{ $run->updated_count }}</td><td data-run-duplicate>{{ $run->duplicate_count }}</td><td data-run-failed>{{ $run->failed_count }}</td>
                                <td>{{ $wib($run->started_at ?? $run->created_at) }}<br><span class="bd-ext__muted">{{ $dur !== null ? ($dur < 60 ? $dur.' dtk' : intdiv($dur, 60).' mnt') : '-' }} · {{ $run->initiator?->name ?? 'Sistem' }}</span></td>
                                <td class="bd-ext__row-actions">
                                    @if (in_array($run->status, ['queued', 'running', 'rate_limited', 'waiting'], true))
                                        <form method="POST" action="{{ route('admin.external-sources.runs.pause', $run) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small" type="submit">Jeda</button></form>
                                    @endif
                                    @if ((in_array($run->status, ['paused', 'rate_limited', 'partial', 'failed'], true) || $run->isStale()) && $run->checkpoint_json)
                                        <form method="POST" action="{{ route('admin.external-sources.runs.resume', $run) }}">@csrf<button class="bd-ext__btn bd-ext__btn--small" type="submit">Lanjutkan</button></form>
                                    @endif
                                    @if ($run->isOpen())
                                        <form method="POST" action="{{ route('admin.external-sources.runs.cancel', $run) }}" data-ext-confirm="Batalkan sinkronisasi ini? Data yang sudah tersimpan tetap ada.">@csrf<button class="bd-ext__btn bd-ext__btn--small bd-ext__btn--quiet" type="submit">Batalkan</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('admin.external-sources.partials.pager', ['paginator' => $runs, 'fragment' => 'ext-history'])
        @endif
    </section>

    {{-- I. Dokumentasi --}}
    <section class="bd-ext__section" aria-labelledby="ext-docs">
        <div class="bd-ext__section-head"><h2 id="ext-docs">Dokumentasi konektor</h2></div>
        <ul class="bd-ext__docs">
            @foreach ($implemented as $c)
                <li><strong>{{ $c['name'] }}</strong> <span class="bd-ext__muted">{{ $c['host'] }}</span> <a class="bd-ext__link" href="{{ $c['docs_url'] }}" target="_blank" rel="noopener noreferrer">Dokumentasi resmi</a>
                    <p>Mode tersedia: {{ implode(', ', array_map(fn ($m) => $modeLabels[$m] ?? $m, $c['modes'])) }}. Filter: {{ implode(', ', array_map(fn ($f) => ['year' => 'tahun', 'language' => 'bahasa', 'type' => 'tipe', 'oa' => 'open access'][$f] ?? $f, $c['filters'])) }}. Ukuran halaman {{ $c['page_size'] }}; satu permintaan per {{ $c['min_interval_ms'] }} ms (gerbang laju bersama).</p>
                    <p><strong>Strategi bulk:</strong> {{ $c['bulk'] }}</p>
                    @foreach ($c['mode_blockers'] as $mode => $reason)<p class="bd-ext__muted">{{ $modeLabels[$mode] ?? $mode }}: {{ $reason }}</p>@endforeach
                </li>
            @endforeach
        </ul>
        <p class="bd-ext__hint">Sinkronisasi hanya memakai jalur API gratis. Incremental hanya ditawarkan bila penyedia punya fitur resmi gratis (DOAJ OAI `from`, Crossref `from-index-date`); tidak ada incremental tiruan. “Lanjutkan” (Resume) meneruskan run yang berhenti dari checkpoint.</p>
    </section>

    {{-- Dialog: pengaturan --}}
    <dialog class="bd-ext__dialog" data-ext-dialog="settings" aria-labelledby="ext-set-title">
        <form method="POST" data-ext-settings-form>
            @csrf
            <header><h2 id="ext-set-title">Pengaturan <span data-ext-name></span></h2><button type="button" class="bd-ext__x" data-ext-close aria-label="Tutup">×</button></header>
            <label class="bd-ext__check"><input type="checkbox" name="enabled" value="1" data-ext-enabled> Aktifkan konektor ini</label>
            <label>Alamat kontak (opsional)
                <input type="email" name="contact" maxlength="120" data-ext-contact placeholder="kontak@lembaga.example">
                <small>Dikirim ke penyedia sebagai identitas sopan. Gunakan alamat lembaga, bukan email pribadi. Kosong = tidak dikirim.</small>
            </label>
            <div data-ext-key-block>
                <label>API key
                    <input type="password" name="api_key" autocomplete="off" maxlength="200" data-ext-key placeholder="Tempel key baru untuk mengganti">
                    <small data-ext-key-note></small>
                </label>
                <label class="bd-ext__check" data-ext-clear-row hidden><input type="checkbox" name="clear_api_key" value="1"> Hapus key yang tersimpan</label>
            </div>
            <footer><button type="button" class="bd-ext__btn bd-ext__btn--quiet" data-ext-close>Batal</button><button type="submit" class="bd-ext__btn bd-ext__btn--primary">Simpan</button></footer>
        </form>
    </dialog>

    @include('admin.external-sources.partials.dialog-sync')
    @include('admin.external-sources.partials.dialog-all')

    {{-- Dialog: detail rekaman --}}
    <dialog class="bd-ext__dialog bd-ext__dialog--wide" data-ext-dialog="record" aria-labelledby="ext-record-title">
        <button type="button" class="bd-ext__x bd-ext__x--float" data-ext-close aria-label="Tutup">×</button>
        <div data-ext-record-body></div>
    </dialog>
</section>
<script type="application/json" data-ext-plan-data>@json($mappingMap)</script>
@endsection
