@extends('layouts.admin')

@section('title', 'Library Internal')
@section('page_title', 'Library Internal')

@php
    $statusLabels = ['draft' => 'Draf', 'processing' => 'Diproses', 'ready' => 'Siap terbit', 'published' => 'Terbit', 'failed' => 'Gagal', 'archived' => 'Diarsipkan'];
    $sortLabels = ['newest' => 'Terbaru ditambahkan', 'oldest' => 'Terlama ditambahkan', 'updated' => 'Terakhir diperbarui', 'title' => 'Judul A–Z'];
    $fmt = fn (int $n): string => number_format($n, 0, ',', '.');
    $filtered = request()->hasAny(['search', 'category', 'status', 'type']) || in_array(request('sort'), ['oldest', 'updated', 'title'], true);
@endphp

@section('content')
<section class="admin-page bd-lib" data-lib>

    <header class="bd-lib__head">
        <div>
            <h1>Library Internal</h1>
            <p>Dokumen akademik yang menjadi dasar riset. Satu sumber bisa punya banyak versi file; yang lama tetap tersimpan.</p>
        </div>
        <div class="bd-lib__head-actions">
            <a href="{{ route('admin.categories.index') }}" class="bd-lib__btn bd-lib__btn--quiet">Kategori Riset</a>
            <a href="{{ route('admin.collections.index') }}" class="bd-lib__btn bd-lib__btn--quiet">Koleksi</a>
            <a href="{{ route('admin.library.create') }}" class="bd-lib__btn bd-lib__btn--primary">@include('admin.library.partials.icon', ['name' => 'plus']) Tambah Sumber</a>
        </div>
    </header>

    <dl class="bd-lib__stats" aria-label="Ringkasan pustaka" data-lib-swap="stats">
        <a class="bd-lib__stat" href="{{ route('admin.library.index') }}"><dt>Total Sumber</dt><dd>{{ $fmt($stats['total']) }}</dd></a>
        <a class="bd-lib__stat" href="{{ route('admin.library.index', ['status' => 'published']) }}"><dt>Sumber Terbit</dt><dd>{{ $fmt($stats['published']) }}</dd></a>
        <a class="bd-lib__stat" href="{{ route('admin.library.index', ['status' => 'processing']) }}"><dt>Sedang Diproses</dt><dd>{{ $fmt($stats['processing']) }}</dd></a>
        <a class="bd-lib__stat" href="{{ route('admin.categories.index') }}"><dt>Total Kategori</dt><dd>{{ $fmt($stats['categories']) }}</dd></a>
    </dl>

    <form method="GET" action="{{ route('admin.library.index') }}" class="bd-lib__filters" role="search" data-lib-filters>
        <label class="bd-lib__search">
            <span class="bd-lib__sr">Cari judul, DOI, atau nama file</span>
            @include('admin.library.partials.icon', ['name' => 'search'])
            <input class="bd-lib__input" type="search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Cari judul, DOI, atau nama file…" data-lib-search>
        </label>

        <select class="bd-lib__select" name="category" aria-label="Filter kategori" data-lib-auto>
            <option value="">Semua kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>

        <select class="bd-lib__select" name="status" aria-label="Filter status" data-lib-auto>
            <option value="">Semua status</option>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        @if ($types->isNotEmpty())
            <select class="bd-lib__select" name="type" aria-label="Filter jenis dokumen" data-lib-auto>
                <option value="">Semua jenis</option>
                @foreach ($types as $type)
                    <option value="{{ $type }}" @selected(request('type') === $type)>{{ strtoupper($type) }}</option>
                @endforeach
            </select>
        @endif

        <select class="bd-lib__select" name="sort" aria-label="Urutkan" data-lib-auto>
            @foreach ($sortLabels as $value => $label)
                <option value="{{ $value }}" @selected(request('sort', 'newest') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <button type="submit" class="bd-lib__btn">Cari</button>
        <span data-lib-swap="reset" style="display:contents">
            @if ($filtered)
                <a href="{{ route('admin.library.index') }}" class="bd-lib__btn bd-lib__btn--quiet" data-lib-reset>Atur ulang</a>
            @endif
        </span>
    </form>

    <div class="bd-lib__alert" data-lib-notice role="alert" hidden>
        <span data-lib-notice-text></span>
        <button type="button" class="bd-lib__btn bd-lib__btn--small" data-lib-retry>Coba lagi</button>
    </div>

    <div data-lib-swap="results" aria-live="polite">
    @if ($documents->isEmpty())
        <div class="bd-lib__empty">
            @if ($filtered)
                <h2>Tidak ada sumber yang cocok</h2>
                <p class="bd-lib__muted">Pencarian atau filter yang aktif tidak menemukan dokumen. Coba kata kunci lain atau atur ulang filter.</p>
                <a href="{{ route('admin.library.index') }}" class="bd-lib__btn">Atur ulang filter</a>
            @else
                <h2>Pustaka masih kosong</h2>
                <p class="bd-lib__muted">Mulai dengan satu PDF. Sistem memprosesnya menjadi potongan teks yang bisa dipakai riset sebelum sumber diterbitkan.</p>
                <a href="{{ route('admin.library.create') }}" class="bd-lib__btn bd-lib__btn--primary">@include('admin.library.partials.icon', ['name' => 'plus']) Tambah sumber pertama</a>
            @endif
        </div>
    @else
        <table class="bd-lib__table">
            <thead>
                <tr>
                    <th scope="col">Sumber</th>
                    <th scope="col" class="bd-lib__col-category">Kategori</th>
                    <th scope="col">Status</th>
                    <th scope="col">Versi</th>
                    <th scope="col" class="bd-lib__col-updated">Diperbarui</th>
                    <th scope="col"><span class="bd-lib__sr">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($documents as $document)
                    @php
                        $history = $histories[$document->id] ?? [];
                        $current = collect($history)->firstWhere('is_active', true) ?? ($history[0] ?? null);
                        $panel = 'versions-'.$document->id;
                        $count = count($history);
                    @endphp
                    <tr class="bd-lib__row" data-lib-row @if ($document->status === 'processing') data-lib-watch="{{ route('admin.library.ingestion-status', $document) }}" @endif>
                        <td>
                            <div class="bd-lib__source">
                                <span class="bd-lib__pdf" aria-hidden="true">PDF</span>
                                <div>
                                    <strong class="bd-lib__title"><a href="{{ route('admin.library.edit', $document) }}" title="{{ $document->title }}">{{ $document->title }}</a></strong>
                                    <span class="bd-lib__meta">
                                        {{ $current['name'] ?? 'Belum ada file' }}
                                        @if ($document->doi) · DOI {{ $document->doi }}@endif
                                        · SRC-{{ str_pad((string) $document->id, 4, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td class="bd-lib__col-category"><span class="bd-lib__cell-text">{{ $document->category?->name ?: '—' }}</span></td>
                        <td><span class="bd-lib__status bd-lib__status--{{ $document->status }}">{{ $statusLabels[$document->status] ?? ucfirst((string) $document->status) }}</span></td>
                        <td>
                            @if ($count)
                                <button type="button" class="bd-lib__toggle" aria-expanded="false" aria-controls="{{ $panel }}" data-lib-toggle>
                                    {{ $current['label'] }} <small>{{ $count }} {{ $count === 1 ? 'versi' : 'versi' }}</small>
                                    @include('admin.library.partials.icon', ['name' => 'chevron-down'])
                                    <span class="bd-lib__sr">Tampilkan riwayat versi {{ $document->title }}</span>
                                </button>
                            @else
                                <span class="bd-lib__cell-text">—</span>
                            @endif
                        </td>
                        <td class="bd-lib__col-updated"><time class="bd-lib__time" datetime="{{ $document->updated_at?->toIso8601String() }}" title="{{ $document->updated_at?->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB">{{ $document->updated_at?->locale('id')->diffForHumans() }}</time></td>
                        <td class="bd-lib__col-actions">
                            <div class="bd-lib__actions">
                                @if ($current)
                                    <button type="button" class="bd-lib__icon-btn" title="Lihat PDF" aria-label="Lihat PDF {{ $document->title }}" data-lib-view data-url="{{ route('admin.library.files.view', $current['id']) }}" data-download="{{ route('admin.library.files.download', $current['id']) }}" data-title="{{ $document->title }}" data-version="{{ $current['label'] }} · {{ $current['state_label'] }}">@include('admin.library.partials.icon', ['name' => 'eye'])</button>
                                    <a class="bd-lib__icon-btn" title="Unduh PDF" aria-label="Unduh PDF {{ $document->title }}" href="{{ route('admin.library.files.download', $current['id']) }}">@include('admin.library.partials.icon', ['name' => 'download'])</a>
                                @endif
                                <a class="bd-lib__manage" href="{{ route('admin.library.edit', $document) }}">Kelola</a>
                            </div>
                        </td>
                    </tr>
                    @if ($count)
                        <tr class="bd-lib__versions" id="{{ $panel }}" data-lib-panel>
                            <td colspan="6">
                                <div class="bd-lib__collapse"><div inert>
                                    @include('admin.library.partials.versions', ['document' => $document, 'history' => $history])
                                </div></div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        @if ($documents->hasPages())
            <nav class="bd-lib__pager" aria-label="Halaman daftar sumber">
                <p class="bd-lib__muted">Menampilkan {{ $fmt($documents->firstItem()) }}–{{ $fmt($documents->lastItem()) }} dari {{ $fmt($documents->total()) }} sumber</p>
                <div class="bd-lib__head-actions">
                    @if ($documents->onFirstPage())
                        <span class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" aria-disabled="true" style="opacity:.45">Sebelumnya</span>
                    @else
                        <a class="bd-lib__btn bd-lib__btn--small" href="{{ $documents->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                    @endif
                    <span class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" aria-current="page">Halaman {{ $documents->currentPage() }} dari {{ $documents->lastPage() }}</span>
                    @if ($documents->hasMorePages())
                        <a class="bd-lib__btn bd-lib__btn--small" href="{{ $documents->nextPageUrl() }}" rel="next">Berikutnya</a>
                    @else
                        <span class="bd-lib__btn bd-lib__btn--quiet bd-lib__btn--small" aria-disabled="true" style="opacity:.45">Berikutnya</span>
                    @endif
                </div>
            </nav>
        @endif
    @endif
    </div>

    @include('admin.library.partials.dialogs')
</section>
@endsection
