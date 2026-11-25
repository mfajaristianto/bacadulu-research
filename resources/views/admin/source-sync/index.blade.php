@extends('layouts.admin')

@section('title', 'Impor Sumber')
@section('page_title', 'Impor Sumber')

@php
    $itemLabels = ['new' => 'Baru', 'changed' => 'Berubah', 'unchanged' => 'Sudah ada', 'imported' => 'Diimpor', 'ignored' => 'Diabaikan', 'failed' => 'Gagal'];
    $runLabels = ['running' => 'Berjalan', 'review_ready' => 'Perlu ditinjau', 'completed' => 'Selesai', 'completed_with_errors' => 'Selesai dengan galat', 'failed' => 'Gagal'];
    $tabs = ['pending' => 'Perlu ditinjau', 'imported' => 'Diimpor', 'ignored' => 'Diabaikan', 'failed' => 'Gagal', 'all' => 'Semua'];
    $metrics = ['all' => 'Ditemukan', 'new' => 'Baru', 'changed' => 'Berubah', 'unchanged' => 'Sudah ada'];
    $wib = fn ($date, string $format = 'j M Y, H.i') => $date?->copy()->timezone('Asia/Jakarta')->locale('id')->translatedFormat($format);
    $link = fn (array $query = []) => route('admin.source-sync.index', array_filter(
        array_merge(['run' => $selectedRun?->id, 'status' => $status, 'q' => $search], $query),
        fn ($value) => filled($value)
    ));
    $isMock = $provider === 'mock';
@endphp

@section('content')
<section class="admin-page bd-sync" data-sync data-index-url="{{ route('admin.source-sync.index') }}">

    <header class="bd-sync__head">
        <div>
            <h1>Impor Sumber</h1>
            <p>Sinkronisasi, periksa, dan impor sumber akademik ke Pustaka.</p>
        </div>
        <div class="bd-sync__head-actions">
            <a href="{{ route('admin.library.index') }}" class="bd-sync__btn bd-sync__btn--quiet">Pustaka</a>
            <form method="POST" action="{{ route('admin.source-sync.scan') }}" data-sync-scan>
                @csrf
                <button type="submit" class="bd-sync__btn bd-sync__btn--primary" data-sync-scan-button>
                    @include('admin.source-sync.partials.icon', ['name' => 'refresh'])
                    <span data-sync-scan-label>Scan Sumber</span>
                </button>
            </form>
        </div>
    </header>

    <p class="bd-sync__connector" data-sync-swap="connector">
        <span class="bd-sync__mode {{ $isMock ? 'is-mock' : '' }}">{{ strtoupper($provider) }}</span>
        @if ($isMock)
            <span>Data uji lokal, bukan koneksi produksi.</span>
        @elseif ($host)
            <span>{{ $host }}</span>
        @endif
        <span>{{ $lastRun ? 'Scan terakhir '.$wib($lastRun->finished_at ?? $lastRun->created_at).' WIB' : 'Belum pernah scan' }}</span>
        <span>Auto-publish {{ $autoPublish ? 'aktif' : 'nonaktif' }}</span>
    </p>

    <div class="bd-sync__toast" data-sync-toast role="status" aria-live="polite" hidden>
        <p data-sync-toast-text></p>
        <a class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small" data-sync-toast-link href="#" hidden>Lihat</a>
        <button type="button" class="bd-sync__icon-btn" data-sync-toast-close aria-label="Tutup pesan">@include('admin.source-sync.partials.icon', ['name' => 'x'])</button>
    </div>

    @if ($errors->has('source_sync'))
        <p class="bd-sync__alert" role="alert">{{ $errors->first('source_sync') }}</p>
    @endif

    @if ($selectedRun)
        <dl class="bd-sync__metrics" aria-label="Ringkasan scan #{{ $selectedRun->id }}" data-sync-swap="metrics">
            @foreach ($metrics as $key => $label)
                <a class="bd-sync__metric {{ $status === $key ? 'is-active' : '' }}" href="{{ $link(['status' => $key, 'page' => null]) }}" data-sync-nav @if ($status === $key) aria-current="true" @endif>
                    <dt>{{ $label }}</dt>
                    <dd>{{ number_format($counts[$key], 0, ',', '.') }}</dd>
                </a>
            @endforeach
        </dl>

        <section class="bd-sync__queue" aria-labelledby="sync-queue-title">
            <div class="bd-sync__queue-head">
                <h2 id="sync-queue-title">Antrean tinjauan <small data-sync-swap="run-title">Scan #{{ $selectedRun->id }}</small></h2>

                <form method="GET" action="{{ route('admin.source-sync.index') }}" role="search" class="bd-sync__search" data-sync-filters>
                    <input type="hidden" name="run" value="{{ $selectedRun->id }}">
                    <input type="hidden" name="status" value="{{ $status }}" data-sync-status>
                    <label>
                        <span class="bd-sync__sr">Cari judul, DOI, atau ID sumber</span>
                        @include('admin.source-sync.partials.icon', ['name' => 'search'])
                        <input class="bd-sync__input" type="search" name="q" value="{{ $search }}" maxlength="160" placeholder="Cari judul, DOI, atau ID…" data-sync-search autocomplete="off">
                    </label>
                </form>
            </div>

            <nav class="bd-sync__tabs" aria-label="Filter antrean" data-sync-swap="tabs">
                @foreach ($tabs as $key => $label)
                    @continue($key === 'failed' && $counts['failed'] === 0 && $status !== 'failed')
                    <a href="{{ $link(['status' => $key, 'page' => null]) }}" data-sync-nav @if ($status === $key) aria-current="page" @endif>{{ $label }} <span>{{ number_format($counts[$key], 0, ',', '.') }}</span></a>
                @endforeach
                <i class="bd-sync__ink" aria-hidden="true"></i>
            </nav>

            <div class="bd-sync__bulk" data-sync-bulk hidden>
                <p><strong data-sync-bulk-count>0</strong> dipilih</p>
                <select class="bd-sync__select" aria-label="Kategori untuk semua yang dipilih" data-sync-bulk-category>
                    <option value="">Pilih kategori</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="bd-sync__btn bd-sync__btn--primary bd-sync__btn--small" data-sync-bulk-import>Impor terpilih</button>
                <button type="button" class="bd-sync__btn bd-sync__btn--small" data-sync-bulk-ignore>Abaikan terpilih</button>
                <button type="button" class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small" data-sync-bulk-clear>Batal</button>
            </div>

            <div data-sync-swap="list" aria-live="polite">
                @if ($items->isEmpty())
                    <div class="bd-sync__empty">
                        @if ($search !== '' || $counts['all'] > 0)
                            <h3>{{ $search !== '' ? 'Tidak ada sumber yang cocok' : 'Tidak ada item di tab ini' }}</h3>
                            <p>{{ $search !== '' ? 'Coba kata kunci lain atau tampilkan semua item scan ini.' : 'Item lain ada di tab berbeda.' }}</p>
                            <a class="bd-sync__btn" href="{{ $link(['status' => 'all', 'q' => null, 'page' => null]) }}" data-sync-nav>Tampilkan semua</a>
                        @else
                            <h3>Scan ini tidak menemukan sumber</h3>
                            <p>Jalankan scan baru atau periksa konfigurasi connector.</p>
                        @endif
                    </div>
                @else
                    <div class="bd-sync__list-head" aria-hidden="false">
                        <label class="bd-sync__check" title="Pilih semua yang bisa ditinjau di halaman ini">
                            <input type="checkbox" data-sync-all aria-label="Pilih semua item yang menunggu tinjauan di halaman ini">
                            <i></i>
                        </label>
                        <span>Sumber</span><span>Kecocokan</span><span>Tindakan</span>
                    </div>

                    <ul class="bd-sync__list">
                        @foreach ($items as $item)
                            @php
                                $meta = (array) data_get($item->payload_json, 'metadata', []);
                                $pending = in_array($item->status, ['new', 'changed'], true);
                                $dup = $duplicates[$item->id] ?? ['exact' => null, 'doi' => null];
                                $dupDoc = $dup['exact'] ?? $dup['doi'];
                                $existing = $item->importedDocument ?? $item->matchedDocument;
                                $authors = is_array($meta['authors'] ?? null) ? implode(', ', $meta['authors']) : ($meta['authors'] ?? null);
                                $line = array_filter([
                                    $authors,
                                    $meta['publication_year'] ?? null,
                                    $meta['type'] ?? null,
                                    ! empty($meta['doi']) ? 'DOI '.$meta['doi'] : $item->external_id,
                                ], fn ($part) => filled($part));
                                $panel = 'sync-detail-'.$item->id;
                                $diff = [];

                                if ($item->status === 'changed' && $item->matchedDocument) {
                                    $doc = $item->matchedDocument;
                                    foreach ([
                                        'Judul' => [$doc->title, $item->title],
                                        'Tahun' => [$doc->publication_year, $meta['publication_year'] ?? null],
                                        'Bahasa' => [$doc->language, $meta['language'] ?? null],
                                        'DOI' => [$doc->doi, $meta['doi'] ?? null],
                                        'Penerbit' => [$doc->publisher, $meta['publisher'] ?? null],
                                        'Abstrak' => [$doc->abstract, $meta['abstract'] ?? null],
                                    ] as $field => [$old, $new]) {
                                        if (filled($new) && (string) $old !== (string) $new) {
                                            $diff[$field] = [$old, $new];
                                        }
                                    }
                                }
                            @endphp
                            <li class="bd-sync__item" data-sync-item data-id="{{ $item->id }}" data-status="{{ $item->status }}" data-title="{{ $item->title }}">
                                <div class="bd-sync__row">
                                    <div class="bd-sync__select-cell">
                                        @if ($pending && ! $dupDoc)
                                            <label class="bd-sync__check">
                                                <input type="checkbox" data-sync-pick aria-label="Pilih {{ $item->title }}">
                                                <i></i>
                                            </label>
                                        @endif
                                    </div>

                                    <div class="bd-sync__main">
                                        <button type="button" class="bd-sync__toggle" aria-expanded="false" aria-controls="{{ $panel }}" data-sync-toggle>
                                            <span class="bd-sync__title">{{ $item->title }}</span>
                                            @include('admin.source-sync.partials.icon', ['name' => 'chevron-down'])
                                        </button>
                                        <p class="bd-sync__meta">{{ implode(' · ', $line) }}</p>
                                    </div>

                                    <div class="bd-sync__match">
                                        <span class="bd-sync__state bd-sync__state--{{ $item->status }}">{{ $itemLabels[$item->status] ?? $item->status }}</span>
                                        @if ($dupDoc)
                                            <span class="bd-sync__warn">{{ $dup['exact'] ? 'Isi identik' : 'DOI sama' }} dengan sumber di Pustaka</span>
                                        @elseif ($item->error_message)
                                            <span class="bd-sync__warn">{{ $item->error_message }}</span>
                                        @elseif ($item->category)
                                            <span class="bd-sync__sub">{{ $item->category->name }}</span>
                                        @endif
                                    </div>

                                    <div class="bd-sync__actions">
                                        @if ($pending && $dupDoc)
                                            <a class="bd-sync__btn bd-sync__btn--small" href="{{ route('admin.library.edit', $dupDoc) }}">Lihat sumber yang ada</a>
                                            <form method="POST" action="{{ route('admin.source-sync.ignore', $item) }}" data-sync-action="ignore">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small">Abaikan</button>
                                            </form>
                                        @elseif ($pending)
                                            <form method="POST" action="{{ route('admin.source-sync.import', $item) }}" class="bd-sync__import" data-sync-action="import" novalidate>
                                                @csrf
                                                <select name="category_id" class="bd-sync__select" aria-label="Kategori untuk {{ $item->title }}" required>
                                                    <option value="">Pilih kategori</option>
                                                    @foreach ($categories as $category)
                                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="bd-sync__btn bd-sync__btn--primary bd-sync__btn--small" data-sync-import>Impor</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.source-sync.ignore', $item) }}" data-sync-action="ignore">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small">Abaikan</button>
                                            </form>
                                        @elseif ($existing)
                                            <a class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small" href="{{ route('admin.library.edit', $existing) }}">Buka di Pustaka @include('admin.source-sync.partials.icon', ['name' => 'external'])</a>
                                        @endif
                                    </div>
                                    <p class="bd-sync__error" data-sync-error role="alert" hidden></p>
                                </div>

                                <div class="bd-sync__detail" id="{{ $panel }}">
                                    <div inert>
                                        <dl class="bd-sync__facts">
                                            <div><dt>ID di connector</dt><dd><code>{{ $item->external_id }}</code> · {{ strtoupper($item->connector) }}</dd></div>
                                            @if ($item->remote_updated_at)
                                                <div><dt>Diperbarui di sumber</dt><dd>{{ $wib($item->remote_updated_at) }} WIB</dd></div>
                                            @endif
                                            @if (! empty($meta['doi']))
                                                <div><dt>DOI</dt><dd>{{ $meta['doi'] }}</dd></div>
                                            @endif
                                            @if (! empty($meta['publisher']))
                                                <div><dt>Penerbit</dt><dd>{{ $meta['publisher'] }}</dd></div>
                                            @endif
                                            @if (! empty($meta['language']))
                                                <div><dt>Bahasa</dt><dd>{{ strtoupper($meta['language']) }}</dd></div>
                                            @endif
                                            @if ($item->external_checksum)
                                                <div class="is-wide"><dt>Checksum</dt><dd><code>{{ $item->external_checksum }}</code>@if ($item->previous_checksum && $item->previous_checksum !== $item->external_checksum)<br><span class="bd-sync__sub">Sebelumnya <code>{{ $item->previous_checksum }}</code></span>@endif</dd></div>
                                            @endif
                                            <div class="is-wide">
                                                <dt>Di Pustaka</dt>
                                                <dd>
                                                    @if ($dupDoc)
                                                        <a href="{{ route('admin.library.edit', $dupDoc) }}">{{ $dupDoc->title }}</a> · {{ $dup['exact'] ? 'isi identik (checksum sama), tidak dibuat sumber kedua' : 'DOI sama; perbarui sumber itu secara manual bila perlu' }}
                                                    @elseif ($existing)
                                                        <a href="{{ route('admin.library.edit', $existing) }}">{{ $existing->title }}</a> · SRC-{{ str_pad((string) $existing->id, 4, '0', STR_PAD_LEFT) }} · {{ $existing->status }}@if (isset($existing->files_count) && $existing->files_count) · {{ $existing->files_count }} versi file @endif
                                                    @else
                                                        Belum ada. Impor membuat satu sumber baru berstatus Siap terbit, tidak langsung terbit.
                                                    @endif
                                                </dd>
                                            </div>
                                            @if ($item->status === 'changed')
                                                <div class="is-wide">
                                                    <dt>Perubahan</dt>
                                                    <dd>
                                                        Isi teks berubah (checksum berbeda). Impor memperbarui sumber yang ada dan memproses ulang potongan teksnya.
                                                        @foreach ($diff as $field => [$old, $new])
                                                            <span class="bd-sync__diff"><b>{{ $field }}</b> <del>{{ \Illuminate\Support\Str::limit((string) ($old ?: '—'), 90) }}</del> → <ins>{{ \Illuminate\Support\Str::limit((string) $new, 90) }}</ins></span>
                                                        @endforeach
                                                    </dd>
                                                </div>
                                            @endif
                                            @if (! empty($meta['abstract']))
                                                <div class="is-wide"><dt>Abstrak</dt><dd>{{ \Illuminate\Support\Str::limit($meta['abstract'], 420) }}</dd></div>
                                            @endif
                                        </dl>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    @if ($items->hasPages())
                        <nav class="bd-sync__pager" aria-label="Halaman antrean">
                            <p class="bd-sync__muted">{{ $items->firstItem() }}–{{ $items->lastItem() }} dari {{ $items->total() }} item</p>
                            <div>
                                @if ($items->onFirstPage())
                                    <span class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small" aria-disabled="true">Sebelumnya</span>
                                @else
                                    <a class="bd-sync__btn bd-sync__btn--small" href="{{ $items->previousPageUrl() }}" data-sync-nav rel="prev">Sebelumnya</a>
                                @endif
                                @if ($items->hasMorePages())
                                    <a class="bd-sync__btn bd-sync__btn--small" href="{{ $items->nextPageUrl() }}" data-sync-nav rel="next">Berikutnya</a>
                                @else
                                    <span class="bd-sync__btn bd-sync__btn--quiet bd-sync__btn--small" aria-disabled="true">Berikutnya</span>
                                @endif
                            </div>
                        </nav>
                    @endif
                @endif
            </div>
        </section>
    @else
        <div class="bd-sync__empty" data-sync-swap="list">
            <h3>Belum ada hasil scan</h3>
            <p>Klik Scan Sumber untuk mengambil daftar sumber dari connector. Pustaka tidak berubah sampai Anda memilih Impor.</p>
        </div>
    @endif

    @if ($runs->isNotEmpty())
        <section class="bd-sync__history" aria-labelledby="sync-history-title" data-sync-swap="history">
            <h2 id="sync-history-title">Riwayat sinkronisasi</h2>
            <ol class="bd-sync__runs">
                @foreach ($runs as $run)
                    <li>
                        <a href="{{ route('admin.source-sync.index', ['run' => $run->id]) }}" data-sync-nav @if ($selectedRun && $selectedRun->id === $run->id) aria-current="true" @endif>
                            <strong>#{{ $run->id }}</strong>
                            <span>{{ strtoupper($run->connector) }}</span>
                            <time datetime="{{ $run->created_at?->toIso8601String() }}">{{ $wib($run->created_at) }} WIB</time>
                            <span class="bd-sync__sub">{{ $run->discovered_count }} ditemukan · {{ $run->new_count }} baru · {{ $run->changed_count }} berubah · {{ $run->imported_count }} diimpor</span>
                            <span class="bd-sync__run-state bd-sync__run-state--{{ $run->status }}">{{ $runLabels[$run->status] ?? $run->status }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            @if ($runs->hasPages())
                <nav class="bd-sync__pager" aria-label="Halaman riwayat">
                    <p class="bd-sync__muted">Halaman {{ $runs->currentPage() }} dari {{ $runs->lastPage() }}</p>
                    <div>
                        @if (! $runs->onFirstPage())<a class="bd-sync__btn bd-sync__btn--small" href="{{ $runs->previousPageUrl() }}" data-sync-nav rel="prev">Sebelumnya</a>@endif
                        @if ($runs->hasMorePages())<a class="bd-sync__btn bd-sync__btn--small" href="{{ $runs->nextPageUrl() }}" data-sync-nav rel="next">Berikutnya</a>@endif
                    </div>
                </nav>
            @endif
        </section>
    @endif

    <dialog class="bd-sync__dialog" data-sync-confirm aria-labelledby="sync-confirm-title">
        <div>
            <h2 id="sync-confirm-title" data-sync-confirm-title>Konfirmasi</h2>
            <p data-sync-confirm-text></p>
            <div class="bd-sync__dialog-actions">
                <button type="button" class="bd-sync__btn" data-sync-confirm-cancel>Batal</button>
                <button type="button" class="bd-sync__btn bd-sync__btn--primary" data-sync-confirm-ok>Lanjutkan</button>
            </div>
        </div>
    </dialog>
</section>
@endsection
